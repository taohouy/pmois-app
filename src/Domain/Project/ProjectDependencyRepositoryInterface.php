<?php

declare(strict_types=1);

namespace App\Domain\Project;

interface ProjectDependencyRepositoryInterface
{
    /**
     * @return array<int, ProjectDependency>
     */
    public function findByProjectId(int $projectId, ?string $dependencyType = null): array;

    /**
     * @return array<int, ProjectDependency>
     */
    public function findByWorkspaceId(): array;

    public function findEdge(int $projectId, int $relatedProjectId, string $dependencyType): ?ProjectDependency;

    /**
     * Directed edges ออกจาก project (project_id = X) ตาม type — ใช้โดย cycle detection
     *
     * @return array<int, ProjectDependency>
     */
    public function findEdgesFrom(int $projectId, string $dependencyType): array;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): ProjectDependency;

    public function delete(int $id): bool;
}
