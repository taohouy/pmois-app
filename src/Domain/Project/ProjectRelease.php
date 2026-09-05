<?php

declare(strict_types=1);

namespace App\Domain\Project;

final class ProjectRelease
{
    public function __construct(
        public readonly int $id,
        public readonly int $projectId,
        public readonly int $workspaceId,
        public readonly string $releaseType,
        public readonly string $versionLabel,
        public readonly string $status,
        public readonly ?int $repositoryId,
        public readonly ?int $environmentId,
        public readonly ?int $milestoneId,
        public readonly ?string $releaseNotes,
        public readonly ?int $releasedBy,
        public readonly ?string $releasedAt,
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
            releaseType: (string) $row['release_type'],
            versionLabel: (string) $row['version_label'],
            status: (string) $row['status'],
            repositoryId: $row['repository_id'] !== null ? (int) $row['repository_id'] : null,
            environmentId: $row['environment_id'] !== null ? (int) $row['environment_id'] : null,
            milestoneId: $row['milestone_id'] !== null ? (int) $row['milestone_id'] : null,
            releaseNotes: $row['release_notes'] !== null ? (string) $row['release_notes'] : null,
            releasedBy: $row['released_by'] !== null ? (int) $row['released_by'] : null,
            releasedAt: $row['released_at'] !== null ? (string) $row['released_at'] : null,
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
            'release_type' => $this->releaseType,
            'version_label' => $this->versionLabel,
            'status' => $this->status,
            'repository_id' => $this->repositoryId,
            'environment_id' => $this->environmentId,
            'milestone_id' => $this->milestoneId,
            'release_notes' => $this->releaseNotes,
            'released_by' => $this->releasedBy,
            'released_at' => $this->releasedAt,
        ];
    }
}
