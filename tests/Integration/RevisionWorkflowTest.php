<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Project\ProjectStatusUpdater;
use App\Domain\Project\RevisionService;
use App\Infrastructure\Persistence\MySQL\MySqlProjectStatusUpdateRepository;
use App\Infrastructure\Persistence\MySQL\MySqlRevisionRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * RevisionWorkflowTest — M3 Revision Management + CTO Review Workflow + Commit Tracking
 *
 * State machine: submitted → cto_approved/cto_rejected → committed
 * กฎตาม M0 R5: MILESTONE_NOT_OPEN, REVISION_NOT_APPROVED, dev_user XOR dev_ai
 * commit → PMO timeline row (idempotent)
 * รันบน DB ที่ migrate ครบ
 */
final class RevisionWorkflowTest extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $ownerUserId;
    private int $ctoUserId;
    private int $projectId;
    private RevisionService $service;

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $this->ownerUserId = $this->insertUser('rev-owner');
        $this->ctoUserId = $this->insertUser('rev-cto');
        $this->workspaceId = $this->insertWorkspace($this->ownerUserId);
        $this->projectId = $this->insertProject('REV-1');

        $revisionRepo = new MySqlRevisionRepository($this->db, $this->workspaceId);
        $this->service = new RevisionService(
            $revisionRepo,
            new \App\Infrastructure\Persistence\MySQL\MySqlMilestoneRepository($this->db, $this->workspaceId),
            new ProjectStatusUpdater(
                new MySqlProjectStatusUpdateRepository($this->db, $this->workspaceId),
                $revisionRepo
            ),
            $this->workspaceId
        );
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testSubmitDefaultsToSubmittedWithSubmitterAsDev(): void
    {
        $revision = $this->service->submit([
            'project_id' => $this->projectId,
            'summary' => 'Initial implementation',
        ], $this->ownerUserId);

        $this->assertSame('submitted', $revision->status);
        $this->assertSame($this->ownerUserId, $revision->devUserId, 'default dev = ผู้ submit');
        $this->assertNull($revision->devAiConsumerId);
    }

    public function testSubmitWithClosedMilestoneRejected(): void
    {
        $milestoneId = $this->seedMilestone('M1', 'closed');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('MILESTONE_NOT_OPEN');

        $this->service->submit([
            'project_id' => $this->projectId,
            'summary' => 'against closed milestone',
            'milestone_id' => $milestoneId,
        ], $this->ownerUserId);
    }

    public function testSubmitDevUserAndAiMutuallyExclusive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->submit([
            'project_id' => $this->projectId,
            'summary' => 'both dev',
            'dev_user_id' => $this->ctoUserId,
            'dev_ai_consumer_id' => 1,
        ], $this->ownerUserId);
    }

    public function testCommitBeforeApprovalRejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('REVISION_NOT_APPROVED');

        $revision = $this->service->submit(['project_id' => $this->projectId, 'summary' => 'work'], $this->ownerUserId);
        $this->service->commit($revision->id, 'abc123', null, 'success', $this->ownerUserId);
    }

    public function testFullHappyPathSubmitApproveCommit(): void
    {
        $revision = $this->service->submit([
            'project_id' => $this->projectId,
            'summary' => 'feature work',
            'test_result' => 'passed',
        ], $this->ownerUserId);

        $approved = $this->service->review($revision->id, 'approved', 'lgtm', $this->ctoUserId);
        $this->assertSame('cto_approved', $approved->status);

        $committed = $this->service->commit($revision->id, 'deadbeef', 'main', 'success', $this->ownerUserId);
        $this->assertSame('committed', $committed->status);
        $this->assertSame('deadbeef', $committed->commitHash);
        $this->assertNotNull($committed->committedAt);

        // PMO timeline row ถูกสร้าง (idempotency key = revision-commit-{id})
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM project_status_updates WHERE idempotency_key = 'revision-commit-{$revision->id}'");
        $stmt->execute();
        $this->assertSame(1, (int) $stmt->fetchColumn());

        // commit ซ้ำไม่ได้ (state machine terminal สำหรับ commit)
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('REVISION_NOT_APPROVED');
        $this->service->commit($revision->id, 'deadbeef2', null, 'success', $this->ownerUserId);
    }

    public function testRejectFlowIsTerminal(): void
    {
        $revision = $this->service->submit(['project_id' => $this->projectId, 'summary' => 'needs rework'], $this->ownerUserId);
        $rejected = $this->service->review($revision->id, 'rejected', 'please fix', $this->ctoUserId);
        $this->assertSame('cto_rejected', $rejected->status);

        // review ซ้ำไม่ได้
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('REVISION_ALREADY_REVIEWED');
        $this->service->review($revision->id, 'approved', null, $this->ctoUserId);
    }

    public function testReviewHistoryRecorded(): void
    {
        $revision = $this->service->submit(['project_id' => $this->projectId, 'summary' => 'work'], $this->ownerUserId);
        $this->service->review($revision->id, 'approved', 'looks good', $this->ctoUserId);

        $reviews = $this->service->getReviews($revision->id);
        $this->assertCount(1, $reviews);
        $this->assertSame('approved', $reviews[0]['decision']);
        $this->assertSame('looks good', $reviews[0]['review_note']);
        $this->assertSame('rev-cto', $reviews[0]['reviewer_name']);
    }

    public function testDoubleReviewRejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('REVISION_ALREADY_REVIEWED');

        $revision = $this->service->submit(['project_id' => $this->projectId, 'summary' => 'work'], $this->ownerUserId);
        $this->service->review($revision->id, 'approved', null, $this->ctoUserId);
        $this->service->review($revision->id, 'rejected', 'changed my mind', $this->ctoUserId);
    }

    private function seedMilestone(string $code, string $status): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO milestones (project_id, workspace_id, code, title, status, created_by)
             VALUES (:pid, :ws, :code, "M", :status, :by)'
        );
        $stmt->execute(['pid' => $this->projectId, 'ws' => $this->workspaceId, 'code' => $code, 'status' => $status, 'by' => $this->ownerUserId]);

        return (int) $this->db->lastInsertId();
    }

    private function insertUser(string $name): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES (:name, :email, NULL, "active", 0)');
        $stmt->execute(['name' => $name, 'email' => $name . uniqid() . '@test.local']);

        return (int) $this->db->lastInsertId();
    }

    private function insertWorkspace(int $createdBy): int
    {
        $stmt = $this->db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, "Rev Test WS", "active", :user)');
        $stmt->execute(['code' => 'rev-ws-' . uniqid(), 'user' => $createdBy]);

        return (int) $this->db->lastInsertId();
    }

    private function insertProject(string $code): int
    {
        $stmt = $this->db->prepare('INSERT INTO projects (workspace_id, code, name, status, development_mode, owner_user_id) VALUES (:ws, :code, "Rev Test Project", "active", "manual", :owner)');
        $stmt->execute(['ws' => $this->workspaceId, 'code' => $code, 'owner' => $this->ownerUserId]);

        return (int) $this->db->lastInsertId();
    }
}
