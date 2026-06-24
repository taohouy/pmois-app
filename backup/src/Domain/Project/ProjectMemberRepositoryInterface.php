<?php

declare(strict_types=1);

namespace App\Domain\Project;

interface ProjectMemberRepositoryInterface
{
    /**
     * คืน role_id ของ user ใน project นี้ ถ้าเป็นสมาชิก (project-level override)
     * ใช้โดย PermissionResolver เป็นลำดับแรกก่อน fallback ไป workspace role
     */
    public function findRoleIdForUser(int $projectId, int $userId): ?int;

    public function addMember(int $projectId, int $userId, int $roleId): void;

    public function removeMember(int $projectId, int $userId): bool;

    /**
     * @return array<int, array{user_id: int, role_id: int}>
     */
    public function listMembers(int $projectId): array;
}
