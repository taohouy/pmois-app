<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

interface WorkspaceMemberRepositoryInterface
{
    /**
     * คืน role_id ของ user ใน workspace -- ใช้โดย PermissionResolver เป็น fallback
     * หลัง project role override (ถ้ามี)
     */
    public function findRoleIdForUser(int $workspaceId, int $userId): ?int;

    public function addMember(int $workspaceId, int $userId, int $roleId): void;

    public function updateRole(int $workspaceId, int $userId, int $roleId): bool;

    public function removeMember(int $workspaceId, int $userId): bool;

    /**
     * @return array<int, array{user_id: int, role_id: int, status: string}>
     */
    public function listMembers(int $workspaceId): array;
}
