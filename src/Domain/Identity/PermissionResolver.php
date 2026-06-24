<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Infrastructure\Persistence\MySQL\MySqlProjectMemberRepository;
use App\Infrastructure\Persistence\MySQL\MySqlWorkspaceMemberRepository;
use PDO;

/**
 * PermissionResolver
 *
 * Algorithm ตาม Phase 0 Specification Package v0.1 หมวด 5.4:
 *  1. workspace.create เป็นทางลัดพิเศษ เช็คแค่ users.is_platform_admin ไม่ดู role เลย
 *  2. ถ้ามี projectId: เช็ค project_members ก่อน (override) ถ้ามี role ใช้ค่านี้
 *  3. ถ้าไม่มี project role: fallback ไปใช้ workspace_members
 *  4. ถ้าไม่ใช่สมาชิกเลยทั้ง 2 ระดับ: false
 *
 * หมายเหตุสำคัญ: Segregation of Duties ของ RFC (RFC creator ห้าม review ของตัวเอง)
 * "ไม่ได้" เช็คในคลาสนี้ -- เป็นกฎเฉพาะของ RfcService ที่ต้องเช็คเพิ่มหลังจากคลาสนี้
 * อนุญาตแล้วว่ามี permission rfc.review (ดู Phase 0 Planning v0.1 หมวด 2.4 และ 5.1)
 */
final class PermissionResolver
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function isPlatformAdmin(int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT is_platform_admin FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();

        return $row !== false && (bool) $row['is_platform_admin'];
    }

    public function can(
        int $userId,
        ?int $workspaceId,
        ?int $projectId,
        string $permissionCode
    ): bool {
        // ทางลัดพิเศษ -- ไม่เกี่ยวกับ workspace/project role เลย
        if ($permissionCode === 'workspace.create') {
            return $this->isPlatformAdmin($userId);
        }

        if ($workspaceId === null) {
            return false;
        }

        $roleId = null;

        if ($projectId !== null) {
            $projectMemberRepo = new MySqlProjectMemberRepository($this->db, $workspaceId);
            $roleId = $projectMemberRepo->findRoleIdForUser($projectId, $userId);
        }

        if ($roleId === null) {
            // workspace_members ไม่มี project context จึง pass workspaceId เป็น null
            // ในตัว constructor ก็ได้ เพราะ findRoleIdForUser รับ workspaceId เป็น parameter ตรงอยู่แล้ว
            $workspaceMemberRepo = new MySqlWorkspaceMemberRepository($this->db, $workspaceId);
            $roleId = $workspaceMemberRepo->findRoleIdForUser($workspaceId, $userId);
        }

        if ($roleId === null) {
            return false; // ไม่ใช่สมาชิกเลยทั้ง 2 ระดับ
        }

        return $this->roleHasPermission($roleId, $permissionCode);
    }

    private function roleHasPermission(int $roleId, string $permissionCode): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM role_permissions WHERE role_id = :role_id AND permission_code = :code LIMIT 1'
        );
        $stmt->execute(['role_id' => $roleId, 'code' => $permissionCode]);

        return $stmt->fetch() !== false;
    }
}
