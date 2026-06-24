<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Project\Project;
use App\Domain\Project\ProjectRepositoryInterface;
use RuntimeException;

/**
 * Pattern: Scoped Repository ตรง — projects มี column workspace_id อยู่ในตัวเอง
 * ใช้ applyWorkspaceScope() + assertWorkspaceMatch() ตามมาตรฐานของ BaseRepository
 */
final class MySqlProjectRepository extends BaseRepository implements ProjectRepositoryInterface
{
    public function findById(int $id): ?Project
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM projects WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);

        return Project::fromRow($row);
    }

    public function listByWorkspace(): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM projects WHERE {{WORKSPACE_FILTER}} ORDER BY created_at DESC'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll();

        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $row): Project => Project::fromRow($row), $rows);
    }

    public function create(
        string $code,
        string $name,
        ?string $description,
        int $ownerUserId
    ): Project {
        if ($this->workspaceId === null) {
            throw new RuntimeException('ต้องมี workspace context ก่อนสร้าง project');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO projects (workspace_id, code, name, description, status, owner_user_id)
             VALUES (:workspace_id, :code, :name, :description, :status, :owner_user_id)'
        );
        $stmt->execute([
            'workspace_id' => $this->workspaceId,
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'status' => 'planning',
            'owner_user_id' => $ownerUserId,
        ]);

        $newId = (int) $this->db->lastInsertId();
        $project = $this->findById($newId);

        if ($project === null) {
            throw new RuntimeException('สร้าง project สำเร็จแต่ดึงข้อมูลกลับไม่ได้ — ตรวจสอบ DB');
        }

        return $project;
    }

    public function updateStatus(int $id, string $status): bool
    {
        // ยืนยันก่อนว่า record นี้อยู่ใน workspace context จริง ก่อนแก้ไขใดๆ
        if ($this->findById($id) === null) {
            return false;
        }

        $sql = $this->applyWorkspaceScope(
            'UPDATE projects SET status = :status WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'status' => $status,
            'id' => $id,
            'workspace_id' => $this->workspaceId,
        ]);
    }
}
