<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Project\ProjectEnvironment;
use App\Domain\Project\ProjectEnvironmentRepositoryInterface;
use PDO;

final class MySqlProjectEnvironmentRepository extends BaseRepository implements ProjectEnvironmentRepositoryInterface
{
    public function findByProjectId(int $projectId): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_environments WHERE project_id = :project_id AND {{WORKSPACE_FILTER}} ORDER BY id ASC'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['project_id' => $projectId, 'workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $row): ProjectEnvironment => ProjectEnvironment::fromRow($row), $rows);
    }

    public function countByProjectId(int $projectId): int
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT COUNT(*) AS c FROM project_environments WHERE project_id = :project_id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['project_id' => $projectId, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? (int) $row['c'] : 0;
    }

    public function create(array $data): ProjectEnvironment
    {
        // INSERT ระบุ workspace_id ตรงจาก parameter (INSERT ไม่ใช้ applyWorkspaceScope ตาม pattern ระบบเดิม)
        $sql = 'INSERT INTO project_environments (project_id, workspace_id, environment, name, url, runtime, php_version,
                database_engine, deploy_path, credential_reference, created_by)
             VALUES (:project_id, :workspace_id, :environment, :name, :url, :runtime, :php_version,
                :database_engine, :deploy_path, :credential_reference, :created_by)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'project_id' => (int) $data['project_id'],
            'workspace_id' => (int) $data['workspace_id'],
            'environment' => (string) $data['environment'],
            'name' => (string) $data['name'],
            'url' => $data['url'],
            'runtime' => $data['runtime'],
            'php_version' => $data['php_version'],
            'database_engine' => $data['database_engine'],
            'deploy_path' => $data['deploy_path'],
            'credential_reference' => $data['credential_reference'],
            'created_by' => (int) $data['created_by'],
        ]);

        $id = (int) $this->db->lastInsertId();
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_environments WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new \RuntimeException('created environment row but re-read failed');
        }

        $this->assertWorkspaceMatch($row);
        return ProjectEnvironment::fromRow($row);
    }

    public function delete(int $id): bool
    {
        $sql = $this->applyWorkspaceScope(
            'DELETE FROM project_environments WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);

        return $stmt->rowCount() > 0;
    }
}
