<?php

declare(strict_types=1);

namespace App\Domain\Project;

final class ProjectStatusUpdate
{
    public function __construct(
        public readonly int $id,
        public readonly int $projectId,
        public readonly string $reportDate,
        public readonly string $overallStatus,
        public readonly string $summary,
        public readonly ?string $keyAchievements,
        public readonly ?string $keyIssues,
        public readonly ?string $nextSteps,
        public readonly int $submittedBy,
        public readonly string $submittedAt,
        public readonly ?string $idempotencyKey,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            projectId: (int) $row['project_id'],
            reportDate: (string) $row['report_date'],
            overallStatus: (string) $row['overall_status'],
            summary: (string) $row['summary'],
            keyAchievements: $row['key_achievements'] !== null ? (string) $row['key_achievements'] : null,
            keyIssues: $row['key_issues'] !== null ? (string) $row['key_issues'] : null,
            nextSteps: $row['next_steps'] !== null ? (string) $row['next_steps'] : null,
            submittedBy: (int) $row['submitted_by'],
            submittedAt: (string) $row['submitted_at'],
            idempotencyKey: $row['idempotency_key'] !== null ? (string) $row['idempotency_key'] : null,
        );
    }
}
