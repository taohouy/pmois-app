<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Identity\RolePermissionRepositoryInterface;
use PDO;

final class MySqlRolePermissionRepository implements RolePermissionRepositoryInterface
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function listByRole(int $roleId): array
    {
        $stmt = $this->db->prepare(
            'SELECT permission_code FROM role_permissions WHERE role_id = :role_id'
        );
        $stmt->execute(['role_id' => $roleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function roleHasPermission(int $roleId, string $permissionCode): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM role_permissions WHERE role_id = :role_id AND permission_code = :code LIMIT 1'
        );
        $stmt->execute(['role_id' => $roleId, 'code' => $permissionCode]);
        return $stmt->fetch() !== false;
    }

    public function addPermission(int $roleId, string $permissionCode): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO role_permissions (role_id, permission_code) VALUES (:role_id, :code)
             ON DUPLICATE KEY UPDATE permission_code = permission_code'
        );
        $stmt->execute(['role_id' => $roleId, 'code' => $permissionCode]);
    }

    public function removePermission(int $roleId, string $permissionCode): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM role_permissions WHERE role_id = :role_id AND permission_code = :code'
        );
        return $stmt->execute(['role_id' => $roleId, 'code' => $permissionCode]);
    }
}
