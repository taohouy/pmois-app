<?php

declare(strict_types=1);

namespace App\Domain\Project;

/** Pattern: Scoped ตรง */
interface ProjectStatusUpdateRepositoryInterface
{
    public function create(
        int $projectId, string $reportDate, string $overallStatus, string $summary,
        ?string $keyAchievements, ?string $keyIssues, ?string $nextSteps, int $submittedByUserId
    ): ProjectStatusUpdate;

    /** ดึงรายงานล่าสุดสุด 1 ฉบับต่อ project ของทุก project ใน workspace -- ใช้โดย AI Context */
    public function latestPerProject(): array;
}
