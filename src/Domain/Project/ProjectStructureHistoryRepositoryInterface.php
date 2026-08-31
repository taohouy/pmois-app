<?php

declare(strict_types=1);

namespace App\Domain\Project;

interface ProjectStructureHistoryRepositoryInterface
{
    public function findByProjectId(int $projectId): array;
    public function create(ProjectStructureHistory $history): int;
}