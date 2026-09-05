<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Project\ProjectDependency;
use App\Domain\Project\ProjectDependencyRepositoryInterface;
use PDO;

final class MySqlProjectDependencyRepository extends BaseRepository implements ProjectDependencyRepositoryInterface
{
    public function findByProjectId(int $projectId, ?string $dependencyType = null): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_dependencies WHERE (project_id = :project_id OR related_project_id = :project_id)
             ' . ($dependencyType !== null ? 'AND dependency_type = :dependency_type' : '') . '
             AND {{WORKSPACE_FILTER}} ORDER BY id ASC'
        );
        $stmt = $this->db->prepare($sql);
        $params = ['project_id' => $projectId, 'workspace_id' => $this->workspaceId];
        if ($dependencyType !== null) {
            $params['dependency_type'] = $dependencyType;
        }
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $row): ProjectDependency => ProjectDependency::fromRow($row), $rows);
    }

    public function findEdgesFrom(int $projectId, string $dependencyType): array
    {
        // directed query — ใช้โดย cycle detection (ทิศทางสำคัญ ห้ามจับทั้งสองฝั่ง)
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_dependencies WHERE project_id = :project_id
             AND dependency_type = :dependency_type AND {{WORKSPACE_FILTER}} ORDER BY id ASC'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'project_id' => $projectId,
            'dependency_type' => $dependencyType,
            'workspace_id' => $this->workspaceId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $row): ProjectDependency => ProjectDependency::fromRow($row), $rows);
    }

    public function findByWorkspaceId(): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_dependencies WHERE {{WORKSPACE_FILTER}} ORDER BY id ASC'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $row): ProjectDependency => ProjectDependency::fromRow($row), $rows);
    }

    public function findEdge(int $projectId, int $relatedProjectId, string $dependencyType): ?ProjectDependency
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_dependencies
             WHERE project_id = :project_id AND related_project_id = :related_project_id
             AND dependency_type = :dependency_type AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'project_id' => $projectId,
            'related_project_id' => $relatedProjectId,
            'dependency_type' => $dependencyType,
            'workspace_id' => $this->workspaceId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);
        return ProjectDependency::fromRow($row);
    }

    public function create(array $data): ProjectDependency
    {
        // INSERT ระบุ workspace_id ตรงจาก parameter (INSERT ไม่ใช้ applyWorkspaceScope ตาม pattern ระบบเดิม)
        $sql = 'INSERT INTO project_dependencies (workspace_id, project_id, related_project_id, dependency_type, note, created_by)
             VALUES (:workspace_id, :project_id, :related_project_id, :dependency_type, :note, :created_by)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'workspace_id' => (int) $data['workspace_id'],
            'project_id' => (int) $data['project_id'],
            'related_project_id' => (int) $data['related_project_id'],
            'dependency_type' => (string) $data['dependency_type'],
            'note' => $data['note'],
            'created_by' => (int) $data['created_by'],
        ]);

        $id = (int) $this->db->lastInsertId();
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_dependencies WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new \RuntimeException('created dependency edge but re-read failed');
        }

        $this->assertWorkspaceMatch($row);
        return ProjectDependency::fromRow($row);
    }

    public function delete(int $id): bool
    {
        $sql = $this->applyWorkspaceScope(
            'DELETE FROM project_dependencies WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);

        return $stmt->rowCount() > 0;
    }
}
