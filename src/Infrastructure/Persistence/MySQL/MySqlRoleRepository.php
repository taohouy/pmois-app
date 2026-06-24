<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Identity\Role;
use App\Domain\Identity\RoleRepositoryInterface;
use PDO;

final class MySqlRoleRepository implements RoleRepositoryInterface
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findById(int $id): ?Role
    {
        $stmt = $this->db->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? Role::fromRow($row) : null;
    }

    public function findByCode(string $code): ?Role
    {
        $stmt = $this->db->prepare('SELECT * FROM roles WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();

        return $row !== false ? Role::fromRow($row) : null;
    }

    public function listAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM roles ORDER BY id');
        return array_map(static fn (array $r): Role => Role::fromRow($r), $stmt->fetchAll());
    }
}
