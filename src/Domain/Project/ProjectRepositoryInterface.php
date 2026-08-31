<?php

declare(strict_types=1);

namespace App\Domain\Project;

interface ProjectRepositoryInterface
{
    public function findById(int $id): ?Project;

    /**
     * @return array<int, Project>
     */
    public function listByWorkspace(): array;

    public function create(
        string $code,
        string $name,
        ?string $description,
        int $ownerUserId,
        int $workspaceId,
        ?int $parentProjectId = null,
        string $developmentMode = 'manual',
        ?string $abbreviation = null,
    ): Project;

    public function updateStatus(int $id, string $status): bool;
    public function updateWorkspace(int $id, int $newWorkspaceId): bool;
    public function updateParent(int $id, ?int $parentProjectId): bool;
    public function updateProgress(int $id, int $progressPercent, string $health): bool;
    public function updateCurrentMilestone(int $id, ?int $milestoneId): bool;
}
