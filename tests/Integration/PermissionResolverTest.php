<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Identity\PermissionResolver;
use App\Infrastructure\Persistence\MySQL\MySqlProjectMemberRepository;
use App\Infrastructure\Persistence\MySQL\MySqlWorkspaceMemberRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * PermissionResolverTest
 *
 * ครอบคลุม 4 เคสตาม Phase 0 Specification Package v0.1 หมวด 1.3 (Definition of Done #3):
 *   1. ไม่มี role เลยทั้ง workspace/project -> false
 *   2. มีแค่ workspace role -> ใช้ permission ของ workspace role
 *   3. มี project role override -> ใช้ permission ของ project role (ไม่ใช่ workspace role)
 *   4. workspace.create -> bypass ทั้งหมด เช็คแค่ is_platform_admin
 *
 * ⚠️ ยังไม่ได้รันจริง (ไม่มี PHP/MySQL ในสิ่งแวดล้อมนี้) -- โครงสร้าง test ถูกต้องตาม
 * PHPUnit convention แต่ต้องรันยืนยันบนเครื่อง local ก่อนเชื่อว่าผ่าน
 */
final class PermissionResolverTest extends TestCase
{
    private PDO $db;
    private PermissionResolver $resolver;

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();
        $this->resolver = new PermissionResolver($this->db);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testUserWithNoMembershipHasNoPermission(): void
    {
        $userId = $this->seedUser(false);
        $workspaceId = $this->seedWorkspace($userId);

        $allowed = $this->resolver->can($userId, $workspaceId, null, 'project.view');

        $this->assertFalse($allowed, 'User ที่ไม่ใช่สมาชิก workspace เลย ต้องไม่มี permission ใดๆ');
    }

    public function testWorkspaceRoleGrantsPermissionWhenNoProjectOverride(): void
    {
        $userId = $this->seedUser(false);
        $workspaceId = $this->seedWorkspace($userId);
        $roleId = $this->seedRoleWithPermission('project.view');

        $wsMemberRepo = new MySqlWorkspaceMemberRepository($this->db, $workspaceId);
        $wsMemberRepo->addMember($workspaceId, $userId, $roleId);

        $allowed = $this->resolver->can($userId, $workspaceId, null, 'project.view');

        $this->assertTrue($allowed, 'Workspace role ต้องให้สิทธิ์ได้เมื่อไม่มี project ระบุ');
    }

    public function testProjectRoleOverridesWorkspaceRole(): void
    {
        $userId = $this->seedUser(false);
        $workspaceId = $this->seedWorkspace($userId);
        $projectId = $this->seedProject($workspaceId, $userId);

        $workspaceRoleId = $this->seedRoleWithPermission('project.view'); // workspace role: view เท่านั้น
        $projectRoleId = $this->seedRoleWithPermission('project.delete'); // project role: delete ได้

        (new MySqlWorkspaceMemberRepository($this->db, $workspaceId))
            ->addMember($workspaceId, $userId, $workspaceRoleId);

        (new MySqlProjectMemberRepository($this->db, $workspaceId))
            ->addMember($projectId, $userId, $projectRoleId);

        // ต้องใช้สิทธิ์ของ project role (delete) ไม่ใช่ workspace role (view)
        $this->assertTrue($this->resolver->can($userId, $workspaceId, $projectId, 'project.delete'));
        $this->assertFalse(
            $this->resolver->can($userId, $workspaceId, $projectId, 'project.view'),
            'เมื่อมี project role override แล้ว ต้อง "ไม่" fallback ไปรวม permission ของ workspace role อีก'
        );
    }

    public function testWorkspaceCreateBypassesRoleSystemEntirely(): void
    {
        $platformAdminId = $this->seedUser(true);
        $normalUserId = $this->seedUser(false);

        $this->assertTrue($this->resolver->can($platformAdminId, null, null, 'workspace.create'));
        $this->assertFalse($this->resolver->can($normalUserId, null, null, 'workspace.create'));
    }

    // ===== Seed helpers =====

    private function seedUser(bool $isPlatformAdmin): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO users (name, email, password_hash, status, is_platform_admin)
             VALUES ('Test', :email, 'hash', 'active', :flag)"
        );
        // ✅ แก้: cast bool -> int ก่อน bind (PDO แปลง PHP false เป็น string ว่าง ไม่ใช่ 0)
        $stmt->execute(['email' => 'u-' . uniqid() . '@example.com', 'flag' => $isPlatformAdmin ? 1 : 0]);
        return (int) $this->db->lastInsertId();
    }

    private function seedWorkspace(int $createdBy): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO workspaces (code, name, status, created_by)
             VALUES (:code, :code, 'active', :created_by)"
        );
        $stmt->execute(['code' => 'WS-' . uniqid(), 'created_by' => $createdBy]);
        return (int) $this->db->lastInsertId();
    }

    private function seedProject(int $workspaceId, int $ownerId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO projects (workspace_id, code, name, status, owner_user_id)
             VALUES (:workspace_id, :code, :code, 'planning', :owner_id)"
        );
        $stmt->execute(['workspace_id' => $workspaceId, 'code' => 'P-' . uniqid(), 'owner_id' => $ownerId]);
        return (int) $this->db->lastInsertId();
    }

    private function seedRoleWithPermission(string $permissionCode): int
    {
        $stmt = $this->db->prepare("INSERT INTO roles (code, name) VALUES (:code, :code)");
        $stmt->execute(['code' => 'ROLE-' . uniqid()]);
        $roleId = (int) $this->db->lastInsertId();

        $stmt2 = $this->db->prepare(
            'INSERT INTO role_permissions (role_id, permission_code) VALUES (:role_id, :code)'
        );
        $stmt2->execute(['role_id' => $roleId, 'code' => $permissionCode]);

        return $roleId;
    }
}
