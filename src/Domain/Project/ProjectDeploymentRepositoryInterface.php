<?php

declare(strict_types=1);

namespace App\Domain\Project;

interface ProjectDeploymentRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): ProjectDeployment;

    public function findById(int $id): ?ProjectDeployment;

    /**
     * @return array<int, ProjectDeployment>
     */
    public function findByProjectId(int $projectId): array;

    /**
     * @param array<string, mixed> $data — status, deployed_by, deployed_at, notes
     */
    public function update(int $id, array $data): bool;
}
