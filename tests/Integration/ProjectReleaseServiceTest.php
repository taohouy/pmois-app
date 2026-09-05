<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Project\ProjectReleaseService;
use App\Infrastructure\Persistence\MySQL\MySqlProjectReleaseRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * ProjectReleaseServiceTest — Release Registry state machine (CTO Requirement #9, R6-09 §3)
 *
 * planned -> in_progress -> released -> rolled_back / cancelled
 * ต้องรันบน DB ที่ migrate ถึง 0055 แล้ว
 */
final class ProjectReleaseServiceTest extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $userId;
    private int $projectId;

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $stmt = $this->db->prepare('INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES ("rel-admin", :email, NULL, "active", 1)');
        $stmt->execute(['email' => 'rel-' . uniqid() . '@test.local']);
        $this->userId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, "Release Test WS", "active", :user)');
        $stmt->execute(['code' => 'rel-ws-' . uniqid(), 'user' => $this->userId]);
        $this->workspaceId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare('INSERT INTO projects (workspace_id, code, name, status, development_mode, owner_user_id) VALUES (:ws, :code, "Release Test Project", "active", "manual", :user)');
        $stmt->execute(['ws' => $this->workspaceId, 'code' => 'REL-1', 'user' => $this->userId]);
        $this->projectId = (int) $this->db->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    private function service(): ProjectReleaseService
    {
        return new ProjectReleaseService(
            new MySqlProjectReleaseRepository($this->db, $this->workspaceId),
            $this->workspaceId
        );
    }

    public function testCreateDefaultsToPlanned(): void
    {
        $release = $this->service()->create([
            'project_id' => $this->projectId,
            'release_type' => 'beta',
            'version_label' => '0.1.0-beta1',
        ], $this->userId);

        $this->assertSame('planned', $release->status);
        $this->assertSame('beta', $release->releaseType);
    }

    public function testDuplicateVersionRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $service = $this->service();
        $service->create(['project_id' => $this->projectId, 'version_label' => '1.0.0'], $this->userId);
        $service->create(['project_id' => $this->projectId, 'version_label' => '1.0.0'], $this->userId);
    }

    public function testFullHappyPathToReleased(): void
    {
        $service = $this->service();
        $release = $service->create(['project_id' => $this->projectId, 'release_type' => 'production', 'version_label' => '1.0.0'], $this->userId);

        $release = $service->transition($release->id, 'in_progress', $this->userId);
        $release = $service->transition($release->id, 'released', $this->userId);

        $this->assertSame('released', $release->status);
        $this->assertNotNull($release->releasedBy);
        $this->assertNotNull($release->releasedAt);
    }

    public function testInvalidTransitionRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $service = $this->service();
        $release = $service->create(['project_id' => $this->projectId, 'version_label' => '2.0.0'], $this->userId);

        // planned -> released ไม่อนุญาต (ต้องผ่าน in_progress)
        $service->transition($release->id, 'released', $this->userId);
    }

    public function testRolledBackIsTerminal(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $service = $this->service();
        $release = $service->create(['project_id' => $this->projectId, 'version_label' => '3.0.0'], $this->userId);
        $service->transition($release->id, 'in_progress', $this->userId);
        $release = $service->transition($release->id, 'released', $this->userId);
        $release = $service->transition($release->id, 'rolled_back', $this->userId);

        $this->assertSame('rolled_back', $release->status);

        // rolled_back เป็น terminal state — transition ต่อไม่ได้
        $service->transition($release->id, 'in_progress', $this->userId);
    }
}
