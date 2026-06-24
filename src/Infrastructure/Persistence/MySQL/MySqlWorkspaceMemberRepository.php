<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Workspace\WorkspaceMemberRepositoryInterface;

/**
 * Pattern: Scoped ตรง -- workspace_members มี column workspace_id ในตัวเอง
 * extends BaseRepository เหมือน MySqlProjectRepository
 */
final class MySqlWorkspaceMemberRepository extends BaseRepository implements WorkspaceMemberRepositoryInterface
{
    public function findRoleIdForUser(int $workspaceId, int $userId): ?int
    {
        // เมธอดนี้รับ workspaceId เป็น parameter ตรง (ไม่ใช้ $this->workspaceId)
        // เพราะ PermissionResolver เรียกแบบ stateless ไม่ผ่าน request context เสมอไป
        $stmt = $this->db->prepare(
            'SELECT role_id FROM workspace_members
             WHERE workspace_id = :workspace_id AND user_id = :user_id AND status = :status
             LIMIT 1'
        );
        $stmt->execute([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'status' => 'active',
        ]);
        $row = $stmt->fetch();

        return $row !== false ? (int) $row['role_id'] : null;
    }

    public function addMember(int $workspaceId, int $userId, int $roleId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO workspace_members (workspace_id, user_id, role_id, status)
             VALUES (:workspace_id, :user_id, :role_id, :status)
             ON DUPLICATE KEY UPDATE role_id = :role_id_update, status = :status_update'
        );
        $stmt->execute([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'role_id' => $roleId,
            'status' => 'active',
            'role_id_update' => $roleId,
            'status_update' => 'active',
        ]);
    }

    public function updateRole(int $workspaceId, int $userId, int $roleId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE workspace_members SET role_id = :role_id
             WHERE workspace_id = :workspace_id AND user_id = :user_id'
        );
        return $stmt->execute([
            'role_id' => $roleId,
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
        ]);
    }

    public function removeMember(int $workspaceId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE workspace_members SET status = 'removed'
             WHERE workspace_id = :workspace_id AND user_id = :user_id"
        );
        return $stmt->execute(['workspace_id' => $workspaceId, 'user_id' => $userId]);
    }

    public function listMembers(int $workspaceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT user_id, role_id, status FROM workspace_members
             WHERE workspace_id = :workspace_id AND status = 'active'"
        );
        $stmt->execute(['workspace_id' => $workspaceId]);
        return $stmt->fetchAll();
    }
}
