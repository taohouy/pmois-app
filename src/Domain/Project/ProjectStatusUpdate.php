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
        public readonly int $submittedBy,
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
            submittedBy: (int) $row['submitted_by'],
        );
    }
}
