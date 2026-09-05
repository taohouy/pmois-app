<?php

declare(strict_types=1);

namespace App\Domain\Project;

interface ProjectEnvironmentRepositoryInterface
{
    /**
     * @return array<int, ProjectEnvironment>
     */
    public function findByProjectId(int $projectId): array;

    public function countByProjectId(int $projectId): int;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): ProjectEnvironment;

    public function delete(int $id): bool;
}
