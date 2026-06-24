<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Workspace\Workspace;
use App\Domain\Workspace\WorkspaceRepositoryInterface;
use PDO;

/**
 * Pattern: Top-level Repository — ไม่ extends BaseRepository
 * เพราะ Workspace คือขอบเขตบนสุด ไม่มี "workspace ของ workspace" ให้ scope
 *
 * คำสั่ง workspace.create ถูกควบคุมสิทธิ์ที่ Controller ด้วย users.is_platform_admin
 * ตรงๆ ไม่ผ่าน PermissionResolver ปกติ (ดู Foundation Module Design หมวด 2.3)
 */
final class MySqlWorkspaceRepository implements WorkspaceRepositoryInterface
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findById(int $id): ?Workspace
    {
        $stmt = $this->db->prepare('SELECT * FROM workspaces WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? Workspace::fromRow($row) : null;
    }

    public function findByCode(string $code): ?Workspace
    {
        $stmt = $this->db->prepare('SELECT * FROM workspaces WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();

        return $row !== false ? Workspace::fromRow($row) : null;
    }

    public function listAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM workspaces ORDER BY created_at DESC');
        return array_map(
            static fn (array $row): Workspace => Workspace::fromRow($row),
            $stmt->fetchAll()
        );
    }

    public function create(
        string $code,
        string $name,
        ?string $description,
        int $createdByUserId
    ): Workspace {
        $stmt = $this->db->prepare(
            'INSERT INTO workspaces (code, name, description, status, created_by)
             VALUES (:code, :name, :description, :status, :created_by)'
        );
        $stmt->execute([
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'status' => 'active',
            'created_by' => $createdByUserId,
        ]);

        $newId = (int) $this->db->lastInsertId();
        $workspace = $this->findById($newId);

        if ($workspace === null) {
            throw new \RuntimeException('สร้าง workspace สำเร็จแต่ดึงข้อมูลกลับไม่ได้ — ตรวจสอบ DB');
        }

        return $workspace;
    }

    public function update(int $id, string $name, ?string $description, string $status): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE workspaces SET name = :name, description = :description, status = :status
             WHERE id = :id'
        );
        return $stmt->execute([
            'name' => $name,
            'description' => $description,
            'status' => $status,
            'id' => $id,
        ]);
    }
}
