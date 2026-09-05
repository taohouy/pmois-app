<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Project\ProjectRelease;
use App\Domain\Project\ProjectReleaseRepositoryInterface;
use PDO;

final class MySqlProjectReleaseRepository extends BaseRepository implements ProjectReleaseRepositoryInterface
{
    public function findByProjectId(int $projectId): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_releases WHERE project_id = :project_id AND {{WORKSPACE_FILTER}} ORDER BY id ASC'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['project_id' => $projectId, 'workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $row): ProjectRelease => ProjectRelease::fromRow($row), $rows);
    }

    public function findById(int $id): ?ProjectRelease
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_releases WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);
        return ProjectRelease::fromRow($row);
    }

    public function findByProjectAndVersion(int $projectId, string $versionLabel): ?ProjectRelease
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_releases WHERE project_id = :project_id AND version_label = :version_label
             AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'project_id' => $projectId,
            'version_label' => $versionLabel,
            'workspace_id' => $this->workspaceId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);
        return ProjectRelease::fromRow($row);
    }

    public function create(array $data): ProjectRelease
    {
        // INSERT ระบุ workspace_id ตรงจาก parameter (INSERT ไม่ใช้ applyWorkspaceScope ตาม pattern ระบบเดิม)
        $sql = 'INSERT INTO project_releases (project_id, workspace_id, release_type, version_label, status,
                repository_id, environment_id, milestone_id, release_notes, created_by)
             VALUES (:project_id, :workspace_id, :release_type, :version_label, :status,
                :repository_id, :environment_id, :milestone_id, :release_notes, :created_by)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'project_id' => (int) $data['project_id'],
            'workspace_id' => (int) $data['workspace_id'],
            'release_type' => (string) $data['release_type'],
            'version_label' => (string) $data['version_label'],
            'status' => (string) $data['status'],
            'repository_id' => $data['repository_id'],
            'environment_id' => $data['environment_id'],
            'milestone_id' => $data['milestone_id'],
            'release_notes' => $data['release_notes'],
            'created_by' => (int) $data['created_by'],
        ]);

        $release = $this->findById((int) $this->db->lastInsertId());
        if ($release === null) {
            throw new \RuntimeException('created release but re-read failed');
        }
        return $release;
    }

    public function update(int $id, array $data): bool
    {
        $assignments = [];
        $params = ['id' => $id, 'workspace_id' => $this->workspaceId];
        foreach ($data as $column => $value) {
            $assignments[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        $sql = $this->applyWorkspaceScope(
            'UPDATE project_releases SET ' . implode(', ', $assignments) . ' WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }
}
