<?php

declare(strict_types=1);

namespace App\Domain\Project;

final class ProjectTechStack
{
    public function __construct(
        public readonly int $id,
        public readonly int $projectId,
        public readonly int $workspaceId,
        public readonly string $layer,
        public readonly string $name,
        public readonly ?string $version,
        public readonly ?string $notes,
        public readonly string $status,
        public readonly int $addedBy,
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
            layer: (string) $row['layer'],
            name: (string) $row['name'],
            version: $row['version'] !== null ? (string) $row['version'] : null,
            notes: $row['notes'] !== null ? (string) $row['notes'] : null,
            status: (string) $row['status'],
            addedBy: (int) $row['added_by'],
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
            'layer' => $this->layer,
            'name' => $this->name,
            'version' => $this->version,
            'notes' => $this->notes,
            'status' => $this->status,
        ];
    }
}
