<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Project\ProjectMemberRepositoryInterface;
use App\Domain\Project\ProjectTeamAssignmentService;
use App\Infrastructure\Persistence\MySQL\MySqlProjectMemberAssignmentRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectMemberRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * ProjectTeamAssignmentServiceTest — Team Registry 2-layer model (CTO Requirement #1, R6-06 §1)
 *
 * Invariant ที่ต้องยืนยัน:
 *  - assign สร้าง ledger row + project_members projection
 *  - revoke mark ledger (history ไม่หาย) + ลบ projection
 *  - assign ซ้ำขณะ active -> ASSIGNMENT_ALREADY_ACTIVE
 * ต้องรันบน DB ที่ migrate ถึง 0055 แล้ว
 */
final class ProjectTeamAssignmentServiceTest extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $ownerUserId;
    private int $memberUserId;
    private int $roleId;
    private int $projectId;
    private ProjectTeamAssignmentService $service;

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $this->ownerUserId = $this->insertUser('team-owner');
        $this->memberUserId = $this->insertUser('team-member');
        $this->workspaceId = $this->insertWorkspace($this->ownerUserId);

        $roleIdStmt = $this->db->query("SELECT id FROM roles WHERE code = 'MEMBER' LIMIT 1");
        $this->roleId = (int) $roleIdStmt->fetchColumn();

        $stmt = $this->db->prepare('INSERT INTO projects (workspace_id, code, name, status, development_mode, owner_user_id) VALUES (:ws, "TEAM-1", "Team Test Project", "active", "manual", :owner)');
        $stmt->execute(['ws' => $this->workspaceId, 'owner' => $this->ownerUserId]);
        $this->projectId = (int) $this->db->lastInsertId();

        $this->service = new ProjectTeamAssignmentService(
            new MySqlProjectMemberAssignmentRepository($this->db, $this->workspaceId),
            new MySqlProjectMemberRepository($this->db, $this->workspaceId),
            $this->workspaceId
        );
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testAssignCreatesLedgerAndProjection(): void
    {
        $assignmentId = $this->service->assign($this->projectId, $this->memberUserId, $this->roleId, 'direct', null, $this->ownerUserId);

        // projection ต้องมี (PermissionResolver จะเห็นสิทธิ์)
        $this->assertSame($this->roleId, $this->projectionRoleId($this->memberUserId));

        // ledger active
        $active = $this->service->listByProject($this->projectId);
        $this->assertCount(1, $active);
        $this->assertSame($assignmentId, $active[0]->id);
        $this->assertTrue($active[0]->isActive());
    }

    public function testDuplicateActiveAssignmentRejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ASSIGNMENT_ALREADY_ACTIVE');

        $this->service->assign($this->projectId, $this->memberUserId, $this->roleId, 'direct', null, $this->ownerUserId);
        $this->service->assign($this->projectId, $this->memberUserId, $this->roleId, 'direct', null, $this->ownerUserId);
    }

    public function testRevokedAssignmentGoesToHistoryAndProjectionRemoved(): void
    {
        $assignmentId = $this->service->assign($this->projectId, $this->memberUserId, $this->roleId, 'direct', null, $this->ownerUserId);
        $this->assertTrue($this->service->revoke($assignmentId, $this->ownerUserId));

        // projection ถูกถอน — PermissionResolver ไม่เห็นสิทธิ์แล้ว
        $this->assertNull($this->projectionRoleId($this->memberUserId));

        // history ยังอยู่ครบ (never deleted)
        $history = $this->service->listByProject($this->projectId, true);
        $this->assertCount(1, $history);
        $this->assertFalse($history[0]->isActive());
        $this->assertSame($this->ownerUserId, $history[0]->revokedBy);

        // สามารถ assign ใหม่ได้หลัง revoke (สลับ role ได้ตาม lifecycle จริง)
        $newId = $this->service->assign($this->projectId, $this->memberUserId, $this->roleId, 'direct', null, $this->ownerUserId);
        $this->assertNotSame($assignmentId, $newId);
    }

    private function projectionRoleId(int $userId): ?int
    {
        $stmt = $this->db->prepare('SELECT role_id FROM project_members WHERE project_id = :project_id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['project_id' => $this->projectId, 'user_id' => $userId]);
        $roleId = $stmt->fetchColumn();

        return $roleId !== false ? (int) $roleId : null;
    }

    private function insertUser(string $name): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES (:name, :email, NULL, "active", 0)');
        $stmt->execute(['name' => $name, 'email' => $name . uniqid() . '@test.local']);

        return (int) $this->db->lastInsertId();
    }

    private function insertWorkspace(int $createdBy): int
    {
        $stmt = $this->db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, "Team Test WS", "active", :user)');
        $stmt->execute(['code' => 'team-ws-' . uniqid(), 'user' => $createdBy]);

        return (int) $this->db->lastInsertId();
    }
}
