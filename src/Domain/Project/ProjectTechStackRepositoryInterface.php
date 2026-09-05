<?php

declare(strict_types=1);

namespace App\Domain\Project;

interface ProjectTechStackRepositoryInterface
{
    /**
     * @return array<int, ProjectTechStack>
     */
    public function findByProjectId(int $projectId): array;

    public function countByProjectId(int $projectId): int;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): ProjectTechStack;

    public function delete(int $id): bool;
}
