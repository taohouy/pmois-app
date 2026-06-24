<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Identity\PermissionResolver;
use App\Domain\Knowledge\KnowledgeLinkService;
use App\Infrastructure\Persistence\MySQL\MySqlKnowledgeLinkRepository;
use App\Infrastructure\Persistence\MySQL\MySqlWorkspaceMemberRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * KnowledgeLinkServiceTest
 *
 * ครอบคลุม Dynamic Permission Resolution ตาม CTO Decision (Phase 2 Specification หมวด 2.5)
 */
final class KnowledgeLinkServiceTest extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private KnowledgeLinkService $service;

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $userId = $this->seedUser();
        $this->workspaceId = $this->seedWorkspace($userId);
        $this->userId = $userId;

        $linkRepo = new MySqlKnowledgeLinkRepository($this->db, $this->workspaceId);
        $resolver = new PermissionResolver($this->db);
        $this->service = new KnowledgeLinkService($linkRepo, $resolver);
    }

    private int $userId;

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testUnknownEntityTypeThrowsError(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/ไม่รู้จัก entity_type/');

        $this->service->resolvePermissionFor('some_future_entity_that_does_not_exist_yet');
    }

    public function testKnownEntityTypeResolvesToCorrectPermission(): void
    {
        $this->assertSame('decision_register.view', $this->service->resolvePermissionFor('decision_register'));
        $this->assertSame('rfc.view', $this->service->resolvePermissionFor('rfc'));
        $this->assertSame('governance_record.view', $this->service->resolvePermissionFor('governance_record'));
    }

    public function testAttachmentEntityTypeUsesKnowledgeArticlePermission(): void
    {
        // ตาม CTO Decision -- attachment ไม่มี permission code แยก ใช้ของ knowledge_article ร่วม
        $this->assertSame('knowledge_article.view', $this->service->resolvePermissionFor('attachment'));
    }

    public function testUserWithoutPermissionCannotListLinks(): void
    {
        // user นี้ไม่ได้เป็นสมาชิก workspace เลย -- ไม่มี permission ใดๆ
        $strangerId = $this->seedUser();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/FORBIDDEN/');

        $this->service->listLinksForEntity('decision_register', 1, $strangerId, $this->workspaceId);
    }

    public function testUserWithPermissionCanListLinks(): void
    {
        $roleId = $this->seedRoleWithPermission('decision_register.view');
        (new MySqlWorkspaceMemberRepository($this->db, $this->workspaceId))
            ->addMember($this->workspaceId, $this->userId, $roleId);

        $links = $this->service->listLinksForEntity('decision_register', 1, $this->userId, $this->workspaceId);

        $this->assertIsArray($links); // ไม่ throw = ผ่าน permission check (ผลลัพธ์ว่างได้ เพราะยังไม่มี link จริง)
    }

    private function seedUser(): int
    {
        $stmt = $this->db->prepare("INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES ('Test', :email, 'hash', 'active', 0)");
        $stmt->execute(['email' => 'kls-' . uniqid() . '@example.com']);
        return (int) $this->db->lastInsertId();
    }

    private function seedWorkspace(int $createdBy): int
    {
        $stmt = $this->db->prepare("INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, :code, 'active', :created_by)");
        $stmt->execute(['code' => 'WS-KLS-' . uniqid(), 'created_by' => $createdBy]);
        return (int) $this->db->lastInsertId();
    }

    private function seedRoleWithPermission(string $permissionCode): int
    {
        $stmt = $this->db->prepare("INSERT INTO roles (code, name) VALUES (:code, :code)");
        $stmt->execute(['code' => 'ROLE-' . uniqid()]);
        $roleId = (int) $this->db->lastInsertId();

        $stmt2 = $this->db->prepare('INSERT INTO role_permissions (role_id, permission_code) VALUES (:role_id, :code)');
        $stmt2->execute(['role_id' => $roleId, 'code' => $permissionCode]);

        return $roleId;
    }
}
