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
        int $ownerUserId,
        int $workspaceId,
        ?int $parentProjectId = null,
        string $developmentMode = 'manual',
        ?string $abbreviation = null,
    ): Project {
        if ($this->workspaceId === null) {
            throw new RuntimeException('ต้องมี workspace context ก่อนสร้าง project');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO projects (workspace_id, parent_project_id, code, abbreviation, name, description, status, development_mode, owner_user_id)
             VALUES (:workspace_id, :parent_project_id, :code, :abbreviation, :name, :description, :status, :development_mode, :owner_user_id)'
        );
        $stmt->execute([
            'workspace_id' => $workspaceId,
            'parent_project_id' => $parentProjectId,
            'code' => $code,
            'abbreviation' => $abbreviation,
            'name' => $name,
            'description' => $description,
            'status' => 'planning',
            'development_mode' => $developmentMode,
            'owner_user_id' => $ownerUserId,
        ]);

        $newId = (int) $this->db->lastInsertId();
        $project = $this->findById($newId);

        if ($project === null) {
            throw new RuntimeException('สร้าง project สำเร็จแต่ดึงข้อมูลกลับไม่ได้ — ตรวจสอบ DB');
        }

        return $project;
    }

    public function updateWorkspace(int $id, int $newWorkspaceId): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $sql = $this->applyWorkspaceScope(
            'UPDATE projects SET workspace_id = :new_workspace_id WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'new_workspace_id' => $newWorkspaceId,
            'id' => $id,
            'workspace_id' => $this->workspaceId,
        ]);
    }

    public function updateParent(int $id, ?int $parentProjectId): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        // Prevent circular reference
        if ($parentProjectId !== null) {
            $current = $parentProjectId;
            while ($current !== null) {
                $parent = $this->findById($current);
                if ($parent !== null && $parent->parentProjectId !== null) {
                    $current = $parent->parentProjectId;
                } else {
                    $current = null;
                }
            }
        }

        $sql = $this->applyWorkspaceScope(
            'UPDATE projects SET parent_project_id = :parent_project_id WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'parent_project_id' => $parentProjectId,
            'id' => $id,
            'workspace_id' => $this->workspaceId,
        ]);
    }

    public function updateProgress(int $id, int $progressPercent, string $health): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $sql = $this->applyWorkspaceScope(
            'UPDATE projects SET progress_percent = :progress_percent, health = :health WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'progress_percent' => $progressPercent,
            'health' => $health,
            'id' => $id,
            'workspace_id' => $this->workspaceId,
        ]);
    }

    public function updateCurrentMilestone(int $id, ?int $milestoneId): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $sql = $this->applyWorkspaceScope(
            'UPDATE projects SET current_milestone_id = :milestone_id WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'milestone_id' => $milestoneId,
            'id' => $id,
            'workspace_id' => $this->workspaceId,
        ]);
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