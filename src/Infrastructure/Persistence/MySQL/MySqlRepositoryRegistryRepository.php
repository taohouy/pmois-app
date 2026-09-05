<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Project\RepositoryRegistry;
use App\Domain\Project\RepositoryRegistryRepositoryInterface;
use PDO;
use RuntimeException;

final class MySqlRepositoryRegistryRepository extends BaseRepository implements RepositoryRegistryRepositoryInterface
{
    public function findByProjectId(int $projectId): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM repositories WHERE project_id = :project_id AND {{WORKSPACE_FILTER}} ORDER BY id ASC'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['project_id' => $projectId, 'workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $row): RepositoryRegistry => RepositoryRegistry::fromRow($row), $rows);
    }

    public function findById(int $id): ?RepositoryRegistry
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM repositories WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);
        return RepositoryRegistry::fromRow($row);
    }

    public function findByUrl(string $repositoryUrl): ?RepositoryRegistry
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM repositories WHERE repository_url = :repository_url AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['repository_url' => $repositoryUrl, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);
        return RepositoryRegistry::fromRow($row);
    }

    public function create(array $data): RepositoryRegistry
    {
        $stmt = $this->db->prepare(
            'INSERT INTO repositories (project_id, workspace_id, git_provider_id, repository_type, repository_name,
                repository_url, default_branch, development_branch, release_branch, production_branch,
                credential_reference, created_by)
             VALUES (:project_id, :workspace_id, :git_provider_id, :repository_type, :repository_name,
                :repository_url, :default_branch, :development_branch, :release_branch, :production_branch,
                :credential_reference, :created_by)'
        );
        $stmt->execute([
            'project_id' => (int) $data['project_id'],
            'workspace_id' => (int) $data['workspace_id'],
            'git_provider_id' => $data['git_provider_id'],
            'repository_type' => $data['repository_type'],
            'repository_name' => (string) $data['repository_name'],
            'repository_url' => (string) $data['repository_url'],
            'default_branch' => (string) $data['default_branch'],
            'development_branch' => $data['development_branch'],
            'release_branch' => $data['release_branch'],
            'production_branch' => $data['production_branch'],
            'credential_reference' => $data['credential_reference'],
            'created_by' => (int) $data['created_by'],
        ]);

        $repository = $this->findById((int) $this->db->lastInsertId());
        if ($repository === null) {
            throw new RuntimeException('created repository but re-read failed');
        }
        return $repository;
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
            'UPDATE repositories SET ' . implode(', ', $assignments) . ' WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }
}
