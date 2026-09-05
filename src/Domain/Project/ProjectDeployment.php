<?php

declare(strict_types=1);

namespace App\Domain\Project;

final class ProjectDeployment
{
    public function __construct(
        public readonly int $id,
        public readonly int $projectId,
        public readonly int $workspaceId,
        public readonly ?int $releaseId,
        public readonly ?int $environmentId,
        public readonly string $status,
        public readonly ?string $notes,
        public readonly ?int $deployedBy,
        public readonly ?string $deployedAt,
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
            releaseId: $row['release_id'] !== null ? (int) $row['release_id'] : null,
            environmentId: $row['environment_id'] !== null ? (int) $row['environment_id'] : null,
            status: (string) $row['status'],
            notes: $row['notes'] !== null ? (string) $row['notes'] : null,
            deployedBy: $row['deployed_by'] !== null ? (int) $row['deployed_by'] : null,
            deployedAt: $row['deployed_at'] !== null ? (string) $row['deployed_at'] : null,
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
            'release_id' => $this->releaseId,
            'environment_id' => $this->environmentId,
            'status' => $this->status,
            'notes' => $this->notes,
            'deployed_by' => $this->deployedBy,
            'deployed_at' => $this->deployedAt,
        ];
    }
}
