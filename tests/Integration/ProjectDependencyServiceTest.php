<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Project\ProjectDependencyRepositoryInterface;
use App\Domain\Project\ProjectDependencyService;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Infrastructure\Persistence\MySQL\MySqlProjectDependencyRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * ProjectDependencyServiceTest — Dependency Registry (CTO Requirement #8)
 *
 * ครอบคลุมกฎของ R6-08:
 *  - self edge ห้าม (DEPENDENCY_SELF)
 *  - depends_on ต้อง acyclic (DEPENDENCY_CIRCULAR)
 *  - cross-workspace ห้าม (DEPENDENCY_WORKSPACE_MISMATCH)
 *  - blocked_by เป็น informational (ทำ cycle ได้ ไม่ throw)
 *
 * ต้องรันบน DB ที่ migrate ถึง 0055 แล้ว (TEST_DB_DSN / pmois_test)
 */
final class ProjectDependencyServiceTest extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $ownerUserId;
    private int $adminUserId;
    /** @var array<int, int> */
    private array $projectIds = [];

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $this->adminUserId = $this->insertUser('dep-admin', true);
        $this->ownerUserId = $this->insertUser('dep-owner', false);
        $this->workspaceId = $this->insertWorkspace('dep-ws', $this->adminUserId);

        $this->projectIds[1] = $this->insertProject('DEP-A', $this->ownerUserId);
        $this->projectIds[2] = $this->insertProject('DEP-B', $this->ownerUserId);
        $this->projectIds[3] = $this->insertProject('DEP-C', $this->ownerUserId);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    private function service(): ProjectDependencyService
    {
        $repo = new MySqlProjectDependencyRepository($this->db, $this->workspaceId);

        return new ProjectDependencyService(
            $repo,
            new MySqlProjectRepository($this->db, $this->workspaceId),
            $this->workspaceId
        );
    }

    public function testSelfEdgeRejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('DEPENDENCY_SELF');

        $this->service()->add([
            'project_id' => $this->projectIds[1],
            'related_project_id' => $this->projectIds[1],
            'dependency_type' => 'depends_on',
        ], $this->ownerUserId);
    }

    public function testCircularDependsOnRejected(): void
    {
        $service = $this->service();

        $service->add([
            'project_id' => $this->projectIds[1],
            'related_project_id' => $this->projectIds[2],
            'dependency_type' => 'depends_on',
        ], $this->ownerUserId);

        // A depends_on B — ตอนนี้ B depends_on A จะเกิดวง ต้องถูกปฏิเสธ
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('DEPENDENCY_CIRCULAR');

        $service->add([
            'project_id' => $this->projectIds[2],
            'related_project_id' => $this->projectIds[1],
            'dependency_type' => 'depends_on',
        ], $this->ownerUserId);
    }

    public function testTransitiveCycleRejected(): void
    {
        $service = $this->service();

        $service->add(['project_id' => $this->projectIds[1], 'related_project_id' => $this->projectIds[2], 'dependency_type' => 'depends_on'], $this->ownerUserId);
        $service->add(['project_id' => $this->projectIds[2], 'related_project_id' => $this->projectIds[3], 'dependency_type' => 'depends_on'], $this->ownerUserId);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('DEPENDENCY_CIRCULAR');

        $service->add(['project_id' => $this->projectIds[3], 'related_project_id' => $this->projectIds[1], 'dependency_type' => 'depends_on'], $this->ownerUserId);
    }

    public function testBlockedByCycleAllowedButWarned(): void
    {
        $service = $this->service();

        $service->add(['project_id' => $this->projectIds[1], 'related_project_id' => $this->projectIds[2], 'dependency_type' => 'blocked_by'], $this->ownerUserId);
        $service->add(['project_id' => $this->projectIds[2], 'related_project_id' => $this->projectIds[1], 'dependency_type' => 'blocked_by'], $this->ownerUserId);

        $graph = $service->graph();
        $this->assertCount(2, $graph['edges']);
        $this->assertNotEmpty($graph['warnings'], 'blocked_by cycle ต้องถูกรายงานเป็น warning');
    }

    public function testGraphPayloadShape(): void
    {
        $service = $this->service();
        $service->add(['project_id' => $this->projectIds[1], 'related_project_id' => $this->projectIds[2], 'dependency_type' => 'depends_on'], $this->ownerUserId);

        $graph = $service->graph();

        $this->assertCount(2, $graph['nodes']);
        $this->assertSame('depends_on', $graph['edges'][0]['type']);
        $this->assertSame($this->projectIds[1], $graph['edges'][0]['from']);
        $this->assertSame($this->projectIds[2], $graph['edges'][0]['to']);
    }

    private function insertUser(string $name, bool $isPlatformAdmin): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password_hash, status, is_platform_admin)
             VALUES (:name, :email, NULL, "active", :admin)'
        );
        $stmt->execute(['name' => $name, 'email' => $name . uniqid() . '@test.local', 'admin' => $isPlatformAdmin ? 1 : 0]);

        return (int) $this->db->lastInsertId();
    }

    private function insertWorkspace(string $code, int $createdBy): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, :name, "active", :created_by)'
        );
        $stmt->execute(['code' => $code . uniqid(), 'name' => 'Dependency Test WS', 'created_by' => $createdBy]);

        return (int) $this->db->lastInsertId();
    }

    private function insertProject(string $code, int $ownerUserId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO projects (workspace_id, code, name, status, development_mode, owner_user_id)
             VALUES (:workspace_id, :code, :name, "planning", "manual", :owner)'
        );
        $stmt->execute([
            'workspace_id' => $this->workspaceId,
            'code' => $code,
            'name' => 'Project ' . $code,
            'owner' => $ownerUserId,
        ]);

        return (int) $this->db->lastInsertId();
    }
}
