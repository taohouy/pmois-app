<?php

declare(strict_types=1);

namespace App\Domain\Project;

use App\Domain\Registry\GitProviderRepositoryInterface;
use RuntimeException;

/**
 * RepositoryRegistryService — GitLab Repository Registry (M0-Design/Revision6/R6-07)
 *
 * GitLab เท่านั้นตาม CTO Constraint — git_providers seed มีแค่ gitlab
 * Manual registration: CTO/Dev กรอก URL + branch ของ repo ที่มีอยู่แล้ว
 */
final class RepositoryRegistryService
{
    private const TYPES = ['main', 'supporting', 'docs', 'test', 'infra', 'custom'];

    public function __construct(
        private readonly RepositoryRegistryRepositoryInterface $repository,
        private readonly GitProviderRepositoryInterface $gitProviderRepository,
        private readonly int $workspaceId,
    ) {
    }

    /**
     * @return array<int, RepositoryRegistry>
     */
    public function listByProject(int $projectId): array
    {
        return $this->repository->findByProjectId($projectId);
    }

    /**
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException VALIDATION_ERROR
     */
    public function register(array $data, int $createdBy, ?int $workspaceDefaultGitProviderId): RepositoryRegistry
    {
        $errors = [];

        if (empty($data['project_id'])) {
            $errors[] = 'project_id is required';
        }
        if (empty($data['repository_url'])) {
            $errors[] = 'repository_url is required';
        } elseif (filter_var($data['repository_url'], FILTER_VALIDATE_URL) === false) {
            $errors[] = 'repository_url must be a valid URL';
        } elseif (str_contains((string) $data['repository_url'], 'github.com')
            || str_contains((string) $data['repository_url'], 'dev.azure.com')
            || str_contains((string) $data['repository_url'], 'bitbucket.org')) {
            // CTO Constraint: GitLab only — reject non-GitLab hosts early
            $errors[] = 'repository_url must be a GitLab URL (GitLab is the only supported source control)';
        }
        if (empty($data['repository_name'])) {
            $errors[] = 'repository_name is required';
        }
        if (isset($data['repository_type']) && !in_array($data['repository_type'], self::TYPES, true)) {
            $errors[] = 'repository_type must be one of: ' . implode(', ', self::TYPES);
        }

        if ($errors !== []) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        if ($this->repository->findByUrl((string) $data['repository_url']) !== null) {
            throw new RuntimeException('repository_url already registered');
        }

        $gitProviderId = $data['git_provider_id'] ?? $workspaceDefaultGitProviderId;
        if ($gitProviderId === null) {
            // fallback ตาม R6-05 §2: workspace default -> gitlab
            $gitlab = $this->gitProviderRepository->findByCode('gitlab');
            $gitProviderId = $gitlab?->id;
        } else {
            $provider = $this->gitProviderRepository->findById((int) $gitProviderId);
            if ($provider === null) {
                throw new \InvalidArgumentException('git_provider_id does not exist');
            }
        }

        $row = [
            'project_id' => (int) $data['project_id'],
            'workspace_id' => $this->workspaceId,
            'git_provider_id' => $gitProviderId,
            'repository_type' => $data['repository_type'] ?? 'main',
            'repository_name' => (string) $data['repository_name'],
            'repository_url' => (string) $data['repository_url'],
            'default_branch' => $data['default_branch'] ?? 'main',
            'development_branch' => $data['development_branch'] ?? null,
            'release_branch' => $data['release_branch'] ?? null,
            'production_branch' => $data['production_branch'] ?? null,
            'credential_reference' => $data['credential_reference'] ?? null,
            'created_by' => $createdBy,
        ];

        return $this->repository->create($row);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $allowed = ['repository_name', 'default_branch', 'development_branch', 'release_branch', 'production_branch', 'repository_status'];
        $filtered = array_intersect_key($data, array_flip($allowed));
        if ($filtered === []) {
            throw new \InvalidArgumentException('no updatable fields supplied');
        }
        return $this->repository->update($id, $filtered);
    }
}
