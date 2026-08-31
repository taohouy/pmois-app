<?php

declare(strict_types=1);

namespace App\Domain\Project;

final class ProjectStructureHistory
{
    public function __construct(
        public readonly int $id,
        public readonly int $projectId,
        public readonly string $changeType,
        public readonly ?int $fromWorkspaceId,
        public readonly ?int $toWorkspaceId,
        public readonly ?int $fromParentProjectId,
        public readonly ?int $toParentProjectId,
        public readonly ?string $reason,
        public readonly int $changedBy,
        public readonly string $createdAt,
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
            changeType: (string) $row['change_type'],
            fromWorkspaceId: $row['from_workspace_id'] !== null ? (int) $row['from_workspace_id'] : null,
            toWorkspaceId: $row['to_workspace_id'] !== null ? (int) $row['to_workspace_id'] : null,
            fromParentProjectId: $row['from_parent_project_id'] !== null ? (int) $row['from_parent_project_id'] : null,
            toParentProjectId: $row['to_parent_project_id'] !== null ? (int) $row['to_parent_project_id'] : null,
            reason: $row['reason'] !== null ? (string) $row['reason'] : null,
            changedBy: (int) $row['changed_by'],
            createdAt: (string) $row['created_at'],
        );
    }
}