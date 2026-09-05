<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Project\ProjectDeployment;
use App\Domain\Project\ProjectDeploymentRepositoryInterface;
use PDO;

final class MySqlProjectDeploymentRepository extends BaseRepository implements ProjectDeploymentRepositoryInterface
{
    public function create(array $data): ProjectDeployment
    {
        // INSERT ระบุ workspace_id ตรงจาก parameter (ตาม pattern ระบบเดิม)
        $sql = 'INSERT INTO project_deployments (project_id, workspace_id, release_id, environment_id, status, notes, created_by)
                VALUES (:project_id, :workspace_id, :release_id, :environment_id, :status, :notes, :created_by)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'project_id' => (int) $data['project_id'],
            'workspace_id' => (int) $data['workspace_id'],
            'release_id' => $data['release_id'],
            'environment_id' => $data['environment_id'],
            'status' => 'pending',
            'notes' => $data['notes'],
            'created_by' => (int) $data['created_by'],
        ]);

        $deployment = $this->findById((int) $this->db->lastInsertId());
        if ($deployment === null) {
            throw new \RuntimeException('created deployment but re-read failed');
        }

        return $deployment;
    }

    public function findById(int $id): ?ProjectDeployment
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_deployments WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);
        return ProjectDeployment::fromRow($row);
    }

    public function findByProjectId(int $projectId): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_deployments WHERE project_id = :project_id AND {{WORKSPACE_FILTER}} ORDER BY id DESC'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['project_id' => $projectId, 'workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $row): ProjectDeployment => ProjectDeployment::fromRow($row), $rows);
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
            'UPDATE project_deployments SET ' . implode(', ', $assignments) . ' WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }
}
