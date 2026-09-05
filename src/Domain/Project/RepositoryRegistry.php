<?php

declare(strict_types=1);

namespace App\Domain\Project;

final class RepositoryRegistry
{
    public function __construct(
        public readonly int $id,
        public readonly int $projectId,
        public readonly int $workspaceId,
        public readonly ?int $gitProviderId,
        public readonly string $repositoryType,
        public readonly string $repositoryName,
        public readonly string $repositoryUrl,
        public readonly string $defaultBranch,
        public readonly ?string $developmentBranch,
        public readonly ?string $releaseBranch,
        public readonly ?string $productionBranch,
        public readonly string $repositoryStatus,
        public readonly ?string $credentialReference,
        public readonly int $createdBy,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            projectId: (int) $row['project_id'],
            workspaceId: (int) $row['workspace_id'],
            gitProviderId: $row['git_provider_id'] !== null ? (int) $row['git_provider_id'] : null,
            repositoryType: (string) $row['repository_type'],
            repositoryName: (string) $row['repository_name'],
            repositoryUrl: (string) ($row['repository_url'] ?? $row['gitlab_url']),
            defaultBranch: (string) $row['default_branch'],
            developmentBranch: $row['development_branch'] !== null ? (string) $row['development_branch'] : null,
            releaseBranch: $row['release_branch'] !== null ? (string) $row['release_branch'] : null,
            productionBranch: $row['production_branch'] !== null ? (string) $row['production_branch'] : null,
            repositoryStatus: (string) $row['repository_status'],
            credentialReference: $row['credential_reference'] !== null ? (string) $row['credential_reference'] : null,
            createdBy: (int) $row['created_by'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->projectId,
            'git_provider_id' => $this->gitProviderId,
            'repository_type' => $this->repositoryType,
            'repository_name' => $this->repositoryName,
            'repository_url' => $this->repositoryUrl,
            'default_branch' => $this->defaultBranch,
            'development_branch' => $this->developmentBranch,
            'release_branch' => $this->releaseBranch,
            'production_branch' => $this->productionBranch,
            'repository_status' => $this->repositoryStatus,
        ];
    }
}
