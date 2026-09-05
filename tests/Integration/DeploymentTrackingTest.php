<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Project\DeploymentService;
use App\Infrastructure\Persistence\MySQL\MySqlProjectDeploymentRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * DeploymentTrackingTest — M3 Deployment Tracking
 * State machine: pending → in_progress → deployed → rolled_back / failed
 * รันบน DB ที่ migrate ครบ (รวม 0059)
 */
final class DeploymentTrackingTest extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $userId;
    private int $projectId;
    private DeploymentService $service;

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $this->userId = $this->insertUser('dep-owner');
        $stmt = $this->db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, "Dep Test WS", "active", :user)');
        $stmt->execute(['code' => 'dep-ws-' . uniqid(), 'user' => $this->userId]);
        $this->workspaceId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare('INSERT INTO projects (workspace_id, code, name, status, development_mode, owner_user_id) VALUES (:ws, "DEP-T", "Dep Test Project", "active", "manual", :user)');
        $stmt->execute(['ws' => $this->workspaceId, 'user' => $this->userId]);
        $this->projectId = (int) $this->db->lastInsertId();

        $this->service = new DeploymentService(
            new MySqlProjectDeploymentRepository($this->db, $this->workspaceId),
            $this->workspaceId
        );
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testCreateDefaultsToPending(): void
    {
        $deployment = $this->service->create(['project_id' => $this->projectId, 'notes' => 'first deploy'], $this->userId);

        $this->assertSame('pending', $deployment->status);
        $this->assertCount(1, $this->service->listByProject($this->projectId));
    }

    public function testHappyPathToDeployed(): void
    {
        $deployment = $this->service->create(['project_id' => $this->projectId], $this->userId);
        $deployment = $this->service->transition($deployment->id, 'in_progress', $this->userId);
        $deployment = $this->service->transition($deployment->id, 'deployed', $this->userId);

        $this->assertSame('deployed', $deployment->status);
        $this->assertNotNull($deployment->deployedBy);
        $this->assertNotNull($deployment->deployedAt);
    }

    public function testInvalidTransitionRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $deployment = $this->service->create(['project_id' => $this->projectId], $this->userId);
        // pending → deployed ไม่อนุญาต (ต้องผ่าน in_progress)
        $this->service->transition($deployment->id, 'deployed', $this->userId);
    }

    public function testRolledBackIsTerminal(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $deployment = $this->service->create(['project_id' => $this->projectId], $this->userId);
        $this->service->transition($deployment->id, 'in_progress', $this->userId);
        $deployment = $this->service->transition($deployment->id, 'deployed', $this->userId);
        $deployment = $this->service->transition($deployment->id, 'rolled_back', $this->userId);

        $this->assertSame('rolled_back', $deployment->status);
        $this->service->transition($deployment->id, 'in_progress', $this->userId);
    }

    private function insertUser(string $name): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES (:name, :email, NULL, "active", 0)');
        $stmt->execute(['name' => $name, 'email' => $name . uniqid() . '@test.local']);

        return (int) $this->db->lastInsertId();
    }
}
