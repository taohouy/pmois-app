<?php

declare(strict_types=1);

namespace App\Domain\Project;

final class Project
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly ?int $parentProjectId,
        public readonly ?int $sourceTemplateId,
        public readonly string $code,
        public readonly string $abbreviation,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $startDate,
        public readonly ?string $endDate,
        public readonly string $status,
        public readonly ?string $developmentMode,
        public readonly ?int $currentMilestoneId,
        public readonly int $progressPercent,
        public readonly string $health,
        public readonly int $profileCompletenessPercent,
        public readonly int $ownerUserId,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $archivedAt,
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
            parentProjectId: $row['parent_project_id'] !== null ? (int) $row['parent_project_id'] : null,
            sourceTemplateId: isset($row['source_template_id']) && $row['source_template_id'] !== null ? (int) $row['source_template_id'] : null,
            code: (string) $row['code'],
            abbreviation: (string) $row['abbreviation'],
            name: (string) $row['name'],
            description: $row['description'] !== null ? (string) $row['description'] : null,
            startDate: $row['start_date'] !== null ? (string) $row['start_date'] : null,
            endDate: $row['end_date'] !== null ? (string) $row['end_date'] : null,
            status: (string) $row['status'],
            developmentMode: $row['development_mode'] !== null ? (string) $row['development_mode'] : null,
            currentMilestoneId: $row['current_milestone_id'] !== null ? (int) $row['current_milestone_id'] : null,
            progressPercent: (int) $row['progress_percent'],
            health: (string) $row['health'],
            profileCompletenessPercent: (int) $row['profile_completeness_percent'],
            ownerUserId: (int) $row['owner_user_id'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
            archivedAt: $row['archived_at'] !== null ? (string) $row['archived_at'] : null,
        );
    }
}
