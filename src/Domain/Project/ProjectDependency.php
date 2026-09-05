<?php

declare(strict_types=1);

namespace App\Domain\Project;

final class ProjectDependency
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly int $projectId,
        public readonly int $relatedProjectId,
        public readonly string $dependencyType,
        public readonly ?string $note,
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
            workspaceId: (int) $row['workspace_id'],
            projectId: (int) $row['project_id'],
            relatedProjectId: (int) $row['related_project_id'],
            dependencyType: (string) $row['dependency_type'],
            note: $row['note'] !== null ? (string) $row['note'] : null,
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
            'related_project_id' => $this->relatedProjectId,
            'dependency_type' => $this->dependencyType,
            'note' => $this->note,
        ];
    }
}
