<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Project\ProjectTechStack;
use App\Domain\Project\ProjectTechStackRepositoryInterface;
use PDO;

final class MySqlProjectTechStackRepository extends BaseRepository implements ProjectTechStackRepositoryInterface
{
    public function findByProjectId(int $projectId): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_technology_stack WHERE project_id = :project_id AND {{WORKSPACE_FILTER}} ORDER BY id ASC'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['project_id' => $projectId, 'workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $row): ProjectTechStack => ProjectTechStack::fromRow($row), $rows);
    }

    public function countByProjectId(int $projectId): int
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT COUNT(*) AS c FROM project_technology_stack WHERE project_id = :project_id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['project_id' => $projectId, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? (int) $row['c'] : 0;
    }

    public function create(array $data): ProjectTechStack
    {
        // INSERT ระบุ workspace_id ตรงจาก parameter (INSERT ไม่ใช้ applyWorkspaceScope ตาม pattern ระบบเดิม)
        $sql = 'INSERT INTO project_technology_stack (project_id, workspace_id, layer, name, version, notes, status, added_by)
             VALUES (:project_id, :workspace_id, :layer, :name, :version, :notes, :status, :added_by)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'project_id' => (int) $data['project_id'],
            'workspace_id' => (int) $data['workspace_id'],
            'layer' => (string) $data['layer'],
            'name' => (string) $data['name'],
            'version' => $data['version'],
            'notes' => $data['notes'],
            'status' => (string) $data['status'],
            'added_by' => (int) $data['added_by'],
        ]);

        $id = (int) $this->db->lastInsertId();
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_technology_stack WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new \RuntimeException('created tech stack row but re-read failed');
        }

        $this->assertWorkspaceMatch($row);
        return ProjectTechStack::fromRow($row);
    }

    public function delete(int $id): bool
    {
        $sql = $this->applyWorkspaceScope(
            'DELETE FROM project_technology_stack WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);

        return $stmt->rowCount() > 0;
    }
}
