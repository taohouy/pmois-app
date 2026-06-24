<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Pattern: Top-level (global)
 */
interface RolePermissionRepositoryInterface
{
    /**
     * @return array<int, string>
     */
    public function listByRole(int $roleId): array;

    public function roleHasPermission(int $roleId, string $permissionCode): bool;

    public function addPermission(int $roleId, string $permissionCode): void;

    public function removePermission(int $roleId, string $permissionCode): bool;
}
