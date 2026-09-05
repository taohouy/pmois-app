<?php

declare(strict_types=1);

namespace App\Domain\Project;

interface ProjectReleaseRepositoryInterface
{
    /**
     * @return array<int, ProjectRelease>
     */
    public function findByProjectId(int $projectId): array;

    public function findById(int $id): ?ProjectRelease;

    public function findByProjectAndVersion(int $projectId, string $versionLabel): ?ProjectRelease;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): ProjectRelease;

    /**
     * @param array<string, mixed> $data — status, released_by, released_at, release_notes
     */
    public function update(int $id, array $data): bool;
}
