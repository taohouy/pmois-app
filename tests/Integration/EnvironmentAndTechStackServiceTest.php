<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Project\EnvironmentService;
use App\Domain\Project\TechStackService;
use App\Infrastructure\Persistence\MySQL\MySqlProjectEnvironmentRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectTechStackRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * EnvironmentServiceTest + TechStackServiceTest — CTO Requirement #6/#7 (R6-10)
 *
 * จุดหลัก:
 *  - environment ห้ามเก็บ secret (field ที่หน้าตาเป็น secret ต้องถูก reject)
 *  - tech stack: layer/status enum validation
 * ต้องรันบน DB ที่ migrate ถึง 0055 แล้ว
 */
final class EnvironmentAndTechStackServiceTest extends TestCase
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

        $stmt = $this->db->prepare('INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES ("env-admin", :email, NULL, "active", 1)');
        $stmt->execute(['email' => 'env-' . uniqid() . '@test.local']);
        $this->userId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, "Env Test WS", "active", :user)');
        $stmt->execute(['code' => 'env-ws-' . uniqid(), 'user' => $this->userId]);
        $this->workspaceId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare('INSERT INTO projects (workspace_id, code, name, status, development_mode, owner_user_id) VALUES (:ws, "ENV-1", "Env Test Project", "active", "manual", :user)');
        $stmt->execute(['ws' => $this->workspaceId, 'user' => $this->userId]);
        $this->projectId = (int) $this->db->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testEnvironmentCreatedWithAllFields(): void
    {
        $service = new EnvironmentService(new MySqlProjectEnvironmentRepository($this->db, $this->workspaceId), $this->workspaceId);

        $env = $service->add($this->projectId, [
            'environment' => 'uat',
            'name' => 'UAT-1',
            'url' => 'https://uat.example.com',
            'runtime' => 'PHP-FPM',
            'php_version' => '8.2',
            'database_engine' => 'MySQL 8.0',
            'deploy_path' => '/var/www/pmois-uat',
            'credential_reference' => 'ENV_UAT_DEPLOY_KEY', // pointer เท่านั้น
        ], $this->userId);

        $this->assertSame('uat', $env->environment);
        $this->assertSame('8.2', $env->phpVersion);
        $this->assertCount(1, $service->listByProject($this->projectId));
    }

    public function testEnvironmentRejectsSecretLikeFields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('never store secrets');

        $service = new EnvironmentService(new MySqlProjectEnvironmentRepository($this->db, $this->workspaceId), $this->workspaceId);
        $service->add($this->projectId, [
            'environment' => 'production',
            'name' => 'PROD-1',
            'password' => 'hunter2', // ❌ ห้าม
        ], $this->userId);
    }

    public function testEnvironmentRejectsInvalidTier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('environment must be one of');

        $service = new EnvironmentService(new MySqlProjectEnvironmentRepository($this->db, $this->workspaceId), $this->workspaceId);
        $service->add($this->projectId, ['environment' => 'staging-unknown', 'name' => 'X'], $this->userId);
    }

    public function testTechStackAddAndList(): void
    {
        $service = new TechStackService(new MySqlProjectTechStackRepository($this->db, $this->workspaceId), $this->workspaceId);

        $service->add($this->projectId, ['layer' => 'language', 'name' => 'PHP', 'version' => '8.2'], $this->userId);
        $service->add($this->projectId, ['layer' => 'framework', 'name' => 'Slim'], $this->userId);
        $service->add($this->projectId, ['layer' => 'database', 'name' => 'MySQL', 'version' => '8.0'], $this->userId);

        $entries = $service->listByProject($this->projectId);
        $this->assertCount(3, $entries);
        $this->assertSame('PHP', $entries[0]->name);
    }

    public function testTechStackInvalidLayerRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('layer must be one of');

        $service = new TechStackService(new MySqlProjectTechStackRepository($this->db, $this->workspaceId), $this->workspaceId);
        $service->add($this->projectId, ['layer' => 'quantum', 'name' => 'PHP'], $this->userId);
    }
}
