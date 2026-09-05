<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

use App\Infrastructure\Persistence\MySQL\MySqlDashboardRepository;

/**
 * DashboardService — read model assembly สำหรับ M2 Dashboards
 *
 * Endpoints ที่ครอบ (M2 Scope):
 *   Portfolio Dashboard / Workspace Dashboard / Parent (children) Dashboard / Project Dashboard
 *   Timeline / Recent Activities / Progress Summary / Health Summary / Portfolio Statistics
 *
 * หลักการ:
 *  - API First: payload ชุดเดียวใช้ทั้ง API และ (ภายหลัง) Web UI
 *  - Configuration over Hardcode: event sources ของ timeline เป็นค่า config ในคลาสนี้ (TIMELINE_SOURCES)
 *  - Backward Compatible: read-only ทั้งหมด ไม่มี schema change
 */
final class DashboardService
{
    public const DEFAULT_ACTIVITY_LIMIT = 10;
    public const DEFAULT_TIMELINE_LIMIT = 20;

    /**
     * Timeline event sources (config) — เพิ่ม/ลด source ได้โดยไม่แตะ controller
     * key = event_type, value = repository method
     */
    private const TIMELINE_SOURCES = [
        'status_update' => 'timelineStatusUpdates',
        'milestone_closed' => 'timelineClosedMilestones',
        'release' => 'timelineReleases',
        'revision' => 'timelineRevisions',
    ];

    public function __construct(
        private readonly MySqlDashboardRepository $repository,
        private readonly int $workspaceId,
    ) {
    }

    // ===== Workspace Dashboard =====

    /**
     * @return array<string, mixed>
     */
    public function workspaceDashboard(): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'projects' => $this->repository->workspaceProjectStats($this->workspaceId),
            'milestones' => $this->repository->workspaceMilestoneStats($this->workspaceId),
            'upcoming_milestones' => $this->repository->workspaceUpcomingMilestones($this->workspaceId, 5),
            'recent_releases' => $this->repository->workspaceRecentReleases($this->workspaceId, 5),
            'governance' => $this->repository->workspaceGovernanceStats($this->workspaceId),
            'recent_activities' => $this->recentActivities(self::DEFAULT_ACTIVITY_LIMIT),
            'timeline' => $this->workspaceTimeline(self::DEFAULT_TIMELINE_LIMIT),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function progressSummary(): array
    {
        $stats = $this->repository->workspaceProjectStats($this->workspaceId);
        $buckets = $this->repository->workspaceProgressBuckets($this->workspaceId);

        return [
            'workspace_id' => $this->workspaceId,
            'average_progress_percent' => $stats['avg_progress'] ?? 0,
            'average_profile_completeness_percent' => $stats['avg_completeness'] ?? 0,
            'buckets' => [
                '0-25' => $buckets['bucket_0_25'] ?? 0,
                '26-50' => $buckets['bucket_26_50'] ?? 0,
                '51-75' => $buckets['bucket_51_75'] ?? 0,
                '76-100' => $buckets['bucket_76_100'] ?? 0,
            ],
        ];
    }

    /**
     * Health Summary — แยกจาก Progress ชัดเจน (CTO Requirement #5 ของ M0 R6)
     *
     * @return array<string, mixed>
     */
    public function healthSummary(): array
    {
        $stats = $this->repository->workspaceProjectStats($this->workspaceId);

        return [
            'workspace_id' => $this->workspaceId,
            'total_projects' => $stats['total'] ?? 0,
            'green' => $stats['green'] ?? 0,
            'yellow' => $stats['yellow'] ?? 0,
            'red' => $stats['red'] ?? 0,
        ];
    }

    /**
     * Portfolio Statistics (workspace-level มุมมองเดียว)
     *
     * @return array<string, mixed>
     */
    public function statistics(): array
    {
        $stats = $this->repository->workspaceProjectStats($this->workspaceId);
        $milestones = $this->repository->workspaceMilestoneStats($this->workspaceId);
        $governance = $this->repository->workspaceGovernanceStats($this->workspaceId);

        return [
            'workspace_id' => $this->workspaceId,
            'projects' => [
                'total' => $stats['total'] ?? 0,
                'by_status' => [
                    'planning' => $stats['planning'] ?? 0,
                    'active' => $stats['active'] ?? 0,
                    'on_hold' => $stats['on_hold'] ?? 0,
                    'closed' => $stats['closed'] ?? 0,
                ],
                'by_development_mode' => [
                    'manual' => $stats['manual'] ?? 0,
                    'ai_assisted' => $stats['ai_assisted'] ?? 0,
                    'ai_dev_auto' => $stats['ai_dev_auto'] ?? 0,
                ],
            ],
            'milestones' => $milestones,
            'governance' => $governance,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentActivities(int $limit): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'action' => $row['action'],
            'entity_type' => $row['entity_type'],
            'entity_id' => $row['entity_id'] !== null ? (int) $row['entity_id'] : null,
            'user_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null,
            'user_name' => $row['user_name'],
            'created_at' => $row['created_at'],
        ], $this->repository->recentActivities($this->workspaceId, $limit));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function workspaceTimeline(int $limit): array
    {
        return $this->repository->workspaceTimeline($this->workspaceId, $limit);
    }

    // ===== Project Dashboard (+ Parent roll-up) =====

    /**
     * @return array<string, mixed>|null null = project ไม่อยู่ใน workspace นี้ (fail-closed)
     */
    public function projectDashboard(int $projectId): ?array
    {
        $project = $this->repository->projectRow($projectId, $this->workspaceId);
        if ($project === null) {
            return null;
        }

        $milestones = $this->repository->projectMilestoneStats($projectId);
        $children = $this->repository->projectChildren($projectId, $this->workspaceId);

        $dashboard = [
            'project' => [
                'id' => (int) $project['id'],
                'code' => $project['code'],
                'name' => $project['name'],
                'status' => $project['status'],
                'health' => $project['health'],
                'progress_percent' => (int) $project['progress_percent'],
                'profile_completeness_percent' => (int) $project['profile_completeness_percent'],
                'development_mode' => $project['development_mode'],
                'parent_project_id' => $project['parent_project_id'] !== null ? (int) $project['parent_project_id'] : null,
            ],
            'milestones' => [
                'total' => $milestones['total'],
                'open' => $milestones['open'],
                'closed' => $milestones['closed'],
                'next' => $this->repository->projectNextMilestone($projectId),
            ],
            'team' => $this->repository->projectTeamStats($projectId),
            'releases' => $this->repository->projectReleaseStats($projectId),
            'environments' => $this->repository->projectEnvironmentStats($projectId),
            'repositories' => ['total' => $this->repository->countRepositories($projectId)],
            'dependencies' => $this->repository->projectDependencyStats($projectId),
            'governance' => $this->repository->projectGovernanceStats($projectId),
            'revisions' => $this->repository->projectRevisionStats($projectId),
            'timeline' => $this->projectTimeline($projectId, self::DEFAULT_TIMELINE_LIMIT),
        ];

        // Parent Project Dashboard: roll-up ของ children เมื่อเป็น parent
        if (count($children) > 0) {
            $dashboard['children'] = $children;
            $dashboard['children_rollup'] = [
                'total' => count($children),
                'avg_progress_percent' => count($children) > 0
                    ? (int) round(array_sum(array_map(static fn ($c) => (int) $c['progress_percent'], $children)) / count($children))
                    : 0,
                'health' => [
                    'green' => count(array_filter($children, static fn ($c) => $c['health'] === 'green')),
                    'yellow' => count(array_filter($children, static fn ($c) => $c['health'] === 'yellow')),
                    'red' => count(array_filter($children, static fn ($c) => $c['health'] === 'red')),
                ],
                'by_status' => [
                    'planning' => count(array_filter($children, static fn ($c) => $c['status'] === 'planning')),
                    'active' => count(array_filter($children, static fn ($c) => $c['status'] === 'active')),
                    'on_hold' => count(array_filter($children, static fn ($c) => $c['status'] === 'on_hold')),
                    'closed' => count(array_filter($children, static fn ($c) => $c['status'] === 'closed')),
                ],
            ];
        }

        return $dashboard;
    }

    /**
     * Timeline ของ project — merge event sources ตาม config, เรียงเวลาจากใหม่ไปเก่า
     *
     * @return array<int, array<string, mixed>>
     */
    public function projectTimeline(int $projectId, int $limit): array
    {
        // ตรวจว่า project อยู่ใน workspace นี้ (fail-closed)
        if ($this->repository->projectRow($projectId, $this->workspaceId) === null) {
            return [];
        }

        $events = [];
        $perSourceLimit = max(1, (int) ceil($limit / max(1, count(self::TIMELINE_SOURCES))));

        foreach (self::TIMELINE_SOURCES as $eventType => $method) {
            foreach ($this->repository->{$method}($projectId, $perSourceLimit) as $row) {
                $events[] = [
                    'event_type' => $eventType,
                    'occurred_at' => $row['occurred_at'],
                    'detail' => $this->timelineDetail($eventType, $row),
                ];
            }
        }

        usort($events, static fn (array $a, array $b): int => strcmp((string) $b['occurred_at'], (string) $a['occurred_at']));

        return array_slice($events, 0, $limit);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function timelineDetail(string $eventType, array $row): string
    {
        return match ($eventType) {
            'status_update' => sprintf('%s — %s', (string) $row['detail'], mb_substr((string) ($row['summary'] ?? ''), 0, 120)),
            'milestone_closed' => sprintf('%s: %s', (string) $row['code'], (string) $row['title']),
            'release' => sprintf('%s %s (%s)', (string) $row['release_type'], (string) $row['version_label'], (string) $row['status']),
            'revision' => sprintf('%s — %s', (string) $row['status'], mb_substr((string) ($row['summary'] ?? ''), 0, 120)),
            default => '',
        };
    }

    /**
     * Project Activity History (M3) — audit rows ของ project นี้, fail-closed ต่าง workspace
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function projectActivities(int $projectId, int $limit): ?array
    {
        if ($this->repository->projectRow($projectId, $this->workspaceId) === null) {
            return null;
        }

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'action' => $row['action'],
            'entity_type' => $row['entity_type'],
            'entity_id' => $row['entity_id'] !== null ? (int) $row['entity_id'] : null,
            'user_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null,
            'user_name' => $row['user_name'],
            'created_at' => $row['created_at'],
        ], $this->repository->projectActivities($projectId, $this->workspaceId, $limit));
    }

    /**
     * Audit API (M5) — workspace audit logs พร้อม filters
     *
     * @return array<int, array<string, mixed>>
     */
    public function auditLogs(int $limit, ?string $entityType, ?int $entityId): array
    {
        return $this->repository->auditLogs($this->workspaceId, $limit, $entityType, $entityId);
    }

    // ===== Portfolio Dashboard (cross-workspace — is_platform_admin เท่านั้น) =====

    /**
     * @return array<string, mixed>
     */
    public function portfolioDashboard(): array
    {
        $totals = $this->repository->portfolioTotals();
        $dependencyStats = $this->repository->portfolioDependencyStats();

        $workspaces = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'status' => $row['status'],
            'projects' => (int) $row['projects'],
            'avg_progress_percent' => (int) $row['avg_progress'],
            'health' => [
                'green' => (int) $row['green'],
                'yellow' => (int) $row['yellow'],
                'red' => (int) $row['red'],
            ],
        ], $this->repository->portfolioWorkspaceRows());

        return [
            'totals' => $totals,
            'workspaces' => $workspaces,
            'dependencies' => $dependencyStats,
            'recent_activities' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'workspace_id' => $row['workspace_id'] !== null ? (int) $row['workspace_id'] : null,
                'action' => $row['action'],
                'entity_type' => $row['entity_type'],
                'entity_id' => $row['entity_id'] !== null ? (int) $row['entity_id'] : null,
                'user_name' => $row['user_name'],
                'created_at' => $row['created_at'],
            ], $this->repository->recentActivities(null, self::DEFAULT_ACTIVITY_LIMIT)),
        ];
    }
}
