<?php

declare(strict_types=1);

namespace App\Domain\Project;

final class Milestone
{
    public function __construct(
        public readonly int $id,
        public readonly int $projectId,
        public readonly int $workspaceId,
        public readonly string $code,
        public readonly string $title,
        public readonly string $status,
        public readonly ?string $plannedDate,
        public readonly ?int $closedBy,
        public readonly ?string $closedAt,
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
            code: (string) $row['code'],
            title: (string) $row['title'],
            status: (string) $row['status'],
            plannedDate: $row['planned_date'] !== null ? (string) $row['planned_date'] : null,
            closedBy: $row['closed_by'] !== null ? (int) $row['closed_by'] : null,
            closedAt: $row['closed_at'] !== null ? (string) $row['closed_at'] : null,
            createdBy: (int) $row['created_by'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}