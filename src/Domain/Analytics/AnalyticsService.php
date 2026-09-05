<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use App\Domain\Project\ProjectDependencyService;
use App\Infrastructure\Persistence\MySQL\MySqlAnalyticsRepository;
use App\Infrastructure\Persistence\MySQL\MySqlDashboardRepository;

/**
 * AnalyticsService — M7 Portfolio Analytics (read model)
 *
 * KPI definitions (config — ปรับสูตรได้ที่เดียว):
 *  - Milestone on-time rate = closed_on_time / (closed_on_time + closed_late)
 *  - Review turnaround = avg hours (revision submitted → review decision)
 *  - Commit rate = committed revisions / total revisions
 *  - At-risk projects = health yellow/red + สาเหตุเชิงข้อมูล (overdue milestones,
 *    critical known issues, failed deployments)
 */
final class AnalyticsService
{
    public function __construct(
        private readonly MySqlAnalyticsRepository $analyticsRepository,
        private readonly MySqlDashboardRepository $dashboardRepository,
        private readonly ProjectDependencyService $dependencyService,
        private readonly int $workspaceId,
    ) {
    }

    /**
     * KPI Dashboard — ตัวชี้วัดหลักของ workspace
     *
     * @return array<string, mixed>
     */
    public function kpis(): array
    {
        $projects = $this->dashboardRepository->workspaceProjectStats($this->workspaceId);
        $milestones = $this->analyticsRepository->milestoneStats($this->workspaceId);
        $turnaround = $this->analyticsRepository->avgReviewTurnaroundHours($this->workspaceId);

        $closedTotal = ($milestones['closed_on_time'] ?? 0) + ($milestones['closed_late'] ?? 0);

        return [
            'workspace_id' => $this->workspaceId,
            'projects' => [
                'total' => $projects['total'] ?? 0,
                'avg_progress' => $projects['avg_progress'] ?? 0,
                'green' => $projects['green'] ?? 0,
                'yellow' => $projects['yellow'] ?? 0,
                'red' => $projects['red'] ?? 0,
            ],
            'milestone_on_time_rate_percent' => $closedTotal > 0
                ? (int) round(($milestones['closed_on_time'] / $closedTotal) * 100)
                : null,
            'overdue_open_milestones' => $milestones['overdue_open'] ?? 0,
            'avg_review_turnaround_hours' => $turnaround,
        ];
    }

    /**
     * Milestone Analytics + monthly trend (chart-ready)
     *
     * @return array<string, mixed>
     */
    public function milestoneAnalytics(int $trendMonths = 12): array
    {
        return [
            'stats' => $this->analyticsRepository->milestoneStats($this->workspaceId),
            'monthly_closed_trend' => $this->analyticsRepository->milestoneMonthlyTrend($this->workspaceId, $trendMonths),
            'upcoming_per_project' => $this->analyticsRepository->upcomingMilestonesPerProject($this->workspaceId, 10),
        ];
    }

    /**
     * CTO / Dev Productivity Metrics
     *
     * @return array<string, mixed>
     */
    public function productivity(): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'reviewers' => $this->analyticsRepository->reviewerStats($this->workspaceId),
            'developers' => $this->analyticsRepository->developerStats($this->workspaceId),
            'avg_review_turnaround_hours' => $this->analyticsRepository->avgReviewTurnaroundHours($this->workspaceId),
        ];
    }

    /**
     * Project Health Analytics — distribution + at-risk list พร้อมเหตุผล
     *
     * @return array<string, mixed>
     */
    public function healthAnalytics(): array
    {
        $projects = $this->dashboardRepository->workspaceProjectStats($this->workspaceId);
        $atRisk = $this->analyticsRepository->atRiskProjects($this->workspaceId);

        return [
            'workspace_id' => $this->workspaceId,
            'distribution' => [
                'green' => $projects['green'] ?? 0,
                'yellow' => $projects['yellow'] ?? 0,
                'red' => $projects['red'] ?? 0,
            ],
            'at_risk_projects' => array_map(static fn (array $row): array => [
                'project_id' => (int) $row['id'],
                'code' => $row['code'],
                'name' => $row['name'],
                'health' => $row['health'],
                'progress_percent' => (int) $row['progress_percent'],
                'signals' => [
                    'overdue_milestones' => (int) $row['overdue_milestones'],
                    'critical_known_issues' => (int) $row['critical_known_issues'],
                    'failed_deployments' => (int) $row['failed_deployments'],
                ],
            ], $atRisk),
        ];
    }

    /**
     * Dependency Analytics — graph + leaders (most depended-on / most depending)
     *
     * @return array<string, mixed>
     */
    public function dependencyAnalytics(): array
    {
        return [
            'graph' => $this->dependencyService->graph(),
            'leaders' => $this->analyticsRepository->dependencyLeaders($this->workspaceId),
        ];
    }

    /**
     * Workspace Analytics — สรุปรวมของ workspace
     *
     * @return array<string, mixed>
     */
    public function workspaceAnalytics(): array
    {
        return [
            'kpis' => $this->kpis(),
            'milestones' => $this->milestoneAnalytics(),
            'health' => $this->healthAnalytics(),
            'productivity' => $this->productivity(),
            'dependencies' => $this->dependencyAnalytics(),
        ];
    }

    /**
     * Portfolio Analytics Dashboard (cross-workspace — is_platform_admin เท่านั้น)
     *
     * @return array<string, mixed>
     */
    public function portfolioAnalytics(): array
    {
        return [
            'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'workspace_kpis' => array_map(static fn (array $row): array => [
                'workspace_id' => (int) $row['id'],
                'name' => $row['name'],
                'projects' => (int) $row['projects'],
                'avg_progress_percent' => (int) $row['avg_progress'],
                'health' => ['green' => (int) $row['green'], 'yellow' => (int) $row['yellow'], 'red' => (int) $row['red']],
                'ai_dev_auto_projects' => (int) $row['ai_dev_auto'],
            ], $this->analyticsRepository->portfolioWorkspaceKpis()),
        ];
    }

    /**
     * Report — รวมทุก analytics ของ workspace เป็นชุดเดียว (สำหรับ export/print)
     *
     * @return array<string, mixed>
     */
    public function report(): array
    {
        return [
            'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'workspace_id' => $this->workspaceId,
            'kpis' => $this->kpis(),
            'milestones' => $this->milestoneAnalytics(),
            'health' => $this->healthAnalytics(),
            'productivity' => $this->productivity(),
            'dependencies' => $this->dependencyAnalytics(),
        ];
    }
}
