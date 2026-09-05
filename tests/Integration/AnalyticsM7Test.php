<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Analytics\AnalyticsService;
use App\Domain\Project\ProjectDependencyService;
use App\Infrastructure\Persistence\MySQL\MySqlAnalyticsRepository;
use App\Infrastructure\Persistence\MySQL\MySqlDashboardRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectDependencyRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * AnalyticsM7Test — M7 Portfolio Analytics (read model)
 *
 * ครอบคลุม: KPI Dashboard (milestone on-time rate, review turnaround),
 * Milestone Analytics (stats + monthly trend), CTO/Dev Productivity,
 * Health Analytics (at-risk + signals), Dependency leaders,
 * Portfolio (cross-workspace), workspace isolation
 * รันบน DB ที่ migrate ครบ — ไม่มี migration ใหม่ (read-only)
 */
final class AnalyticsM7Test extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $otherWorkspaceId;
    private int $ownerUserId;
    private int $ctoUserId;
    private int $devUserId;
    private int $projectId;
    private AnalyticsService $service;

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $this->ownerUserId = $this->insertUser('a7-owner');
        $this->ctoUserId = $this->insertUser('a7-cto');
        $this->devUserId = $this->insertUser('a7-dev');
        $this->workspaceId = $this->insertWorkspace('a7-ws');
        $this->otherWorkspaceId = $this->insertWorkspace('a7-other');

        $this->projectId = $this->insertProject($this->workspaceId, 'A7-1', 'active', 'yellow', 50);
        $this->insertProject($this->workspaceId, 'A7-2', 'active', 'green', 80);
        $this->insertProject($this->otherWorkspaceId, 'A7-X', 'active', 'green', 100);

        $analyticsRepo = new MySqlAnalyticsRepository($this->db);
        $this->service = new AnalyticsService(
            $analyticsRepo,
            new MySqlDashboardRepository($this->db),
            new ProjectDependencyService(
                new MySqlProjectDependencyRepository($this->db, $this->workspaceId),
                new MySqlProjectRepository($this->db, $this->workspaceId),
                $this->workspaceId
            ),
            $this->workspaceId
        );
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    // ===== KPI Dashboard =====

    public function testKpisMilestoneOnTimeRate(): void
    {
        // closed on-time (closed ก่อน planned), closed late, open overdue
        $this->seedMilestone($this->projectId, 'M1', 'closed', date('Y-m-d', time() + 86400 * 5), date('Y-m-d H:i:s', time() - 86400));
        $this->seedMilestone($this->projectId, 'M2', 'closed', date('Y-m-d', time() - 86400 * 2), date('Y-m-d H:i:s', time() - 86400));
        $this->seedMilestone($this->projectId, 'M3', 'open', date('Y-m-d', time() - 86400), null);

        $kpis = $this->service->kpis();

        $this->assertSame(50, $kpis['milestone_on_time_rate_percent'], '1 on-time จาก 2 closed = 50%');
        $this->assertSame(1, $kpis['overdue_open_milestones']);
        $this->assertSame(2, $kpis['projects']['total'], 'workspace isolation — project อีก workspace ไม่ถูกนับ');
    }

    public function testKpisOnTimeRateNullWhenNoClosedMilestones(): void
    {
        $kpis = $this->service->kpis();
        $this->assertNull($kpis['milestone_on_time_rate_percent']);
    }

    // ===== CTO / Dev Productivity =====

    public function testReviewTurnaroundAndReviewerStats(): void
    {
        $revisionId = $this->seedRevision('submitted', date('Y-m-d H:i:s', time() - 3600 * 10));
        $this->seedReview($revisionId, 'approved', date('Y-m-d H:i:s', time() - 3600 * 6)); // turnaround 4h

        $prod = $this->service->productivity();

        $this->assertSame(4.0, $prod['avg_review_turnaround_hours']);
        $this->assertCount(1, $prod['reviewers']);
        $this->assertSame($this->ctoUserId, (int) $prod['reviewers'][0]['user_id']);
        $this->assertSame(1, (int) $prod['reviewers'][0]['reviews']);
        $this->assertSame(4.0, (float) $prod['reviewers'][0]['avg_review_hours']);
    }

    public function testDeveloperStatsAndCommitRate(): void
    {
        $committed = $this->seedRevision('committed', date('Y-m-d H:i:s', time() - 86400));
        // ใช้เวลาจาก PHP (ไม่ใช่ MySQL NOW()) ให้ timezone สอดคล้องกับ submitted_at ที่ seed ด้วย PHP
        $this->db->prepare('UPDATE revisions SET committed_at = :t WHERE id = :id')
            ->execute(['id' => $committed, 't' => date('Y-m-d H:i:s', time() - 43200)]);
        $this->seedRevision('submitted', date('Y-m-d H:i:s', time() - 3600));

        $prod = $this->service->productivity();
        $devs = $prod['developers'];

        $this->assertNotEmpty($devs);
        $mine = array_values(array_filter($devs, fn ($d) => (int) $d['user_id'] === $this->devUserId));
        $this->assertNotEmpty($mine);
        $this->assertSame(2, (int) $mine[0]['submissions']);
        $this->assertSame(1, (int) $mine[0]['commits']);
        $this->assertSame(12.0, (float) $mine[0]['avg_cycle_hours']);
    }

    // ===== Health Analytics =====

    public function testHealthAnalyticsAtRiskWithSignals(): void
    {
        // yellow project + overdue milestone + critical known issue
        $this->seedMilestone($this->projectId, 'M1', 'open', date('Y-m-d', time() - 86400 * 3), null);
        $this->seedKnowledgeEntry($this->projectId, 'known_issue', 'critical');
        $this->seedDependency($this->projectId, $this->insertProject($this->workspaceId, 'A7-3', 'active', 'green', 10));

        $health = $this->service->healthAnalytics();

        $this->assertSame(3, $health['distribution']['green'] + $health['distribution']['yellow'] + $health['distribution']['red'], 'sanity: รวมทุก health = จำนวน project ใน workspace');
        $this->assertNotEmpty($health['at_risk_projects']);
        $atRisk = $health['at_risk_projects'][0];
        $this->assertSame('A7-1', $atRisk['code']);
        $this->assertSame(1, $atRisk['signals']['overdue_milestones']);
        $this->assertSame(1, $atRisk['signals']['critical_known_issues']);
    }

    // ===== Dependency Analytics =====

    public function testDependencyLeaders(): void
    {
        $depended = $this->insertProject($this->workspaceId, 'A7-BASE', 'active', 'green', 100);
        $this->seedDependency($this->projectId, $depended);
        $this->seedDependency($this->insertProject($this->workspaceId, 'A7-4', 'active', 'green', 10), $depended);

        $leaders = $this->service->dependencyAnalytics()['leaders'];

        $this->assertNotEmpty($leaders['most_depended_on']);
        $this->assertSame('A7-BASE', $leaders['most_depended_on'][0]['code']);
        $this->assertSame(2, (int) $leaders['most_depended_on'][0]['depended_on_count']);
    }

    // ===== Milestone Analytics (trend) =====

    public function testMilestoneMonthlyTrend(): void
    {
        $this->seedMilestone($this->projectId, 'M1', 'closed', null, date('Y-m-d H:i:s'));
        $trend = $this->service->milestoneAnalytics()['monthly_closed_trend'];

        $this->assertNotEmpty($trend);
        $this->assertSame(date('Y-m'), $trend[0]['month']);
    }

    // ===== Portfolio (cross-workspace) =====

    public function testPortfolioAnalyticsCoversAllWorkspaces(): void
    {
        $portfolio = $this->service->portfolioAnalytics();

        $this->assertCount(2, $portfolio['workspace_kpis']);
        $totalProjects = array_sum(array_map(static fn ($w) => $w['projects'], $portfolio['workspace_kpis']));
        $this->assertSame(3, $totalProjects, 'portfolio เห็นทุก workspace (3 projects)');
    }

    // ===== helpers =====

    private function seedMilestone(int $projectId, string $code, string $status, ?string $plannedDate, ?string $closedAt): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO milestones (project_id, workspace_id, code, title, status, planned_date, closed_by, closed_at, created_by)
             VALUES (:pid, :ws, :code, "M", :status, :planned, :closed_by, :closed_at, :by)'
        );
        $stmt->execute([
            'pid' => $projectId, 'ws' => $this->workspaceId, 'code' => $code, 'status' => $status,
            'planned' => $plannedDate,
            'closed_by' => $closedAt !== null ? $this->ctoUserId : null,
            'closed_at' => $closedAt, 'by' => $this->ownerUserId,
        ]);
    }

    private function seedRevision(string $status, string $submittedAt): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO revisions (project_id, workspace_id, status, summary, dev_user_id, submitted_by, submitted_at, committed_at)
             VALUES (:pid, :ws, :status, "work", :dev, :by, :submitted, :committed)'
        );
        $stmt->execute([
            'pid' => $this->projectId, 'ws' => $this->workspaceId, 'status' => $status,
            'dev' => $this->devUserId, 'by' => $this->devUserId, 'submitted' => $submittedAt,
            'committed' => $status === 'committed' ? date('Y-m-d H:i:s', strtotime($submittedAt) + 43200) : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function seedReview(int $revisionId, string $decision, string $reviewedAt): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO revision_reviews (revision_id, decision, reviewed_by, reviewed_at) VALUES (:rid, :decision, :by, :at)'
        );
        $stmt->execute(['rid' => $revisionId, 'decision' => $decision, 'by' => $this->ctoUserId, 'at' => $reviewedAt]);
    }

    private function seedKnowledgeEntry(int $projectId, string $type, string $severity): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO knowledge_entries (workspace_id, project_id, entry_type, title, body, status, severity, created_by)
             VALUES (:ws, :pid, :type, "issue", "body", "open", :severity, :by)'
        );
        $stmt->execute(['ws' => $this->workspaceId, 'pid' => $projectId, 'type' => $type, 'severity' => $severity, 'by' => $this->ownerUserId]);
    }

    private function seedDependency(int $projectId, int $relatedProjectId): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO project_dependencies (workspace_id, project_id, related_project_id, dependency_type, created_by)
             VALUES (:ws, :pid, :rid, 'depends_on', :by)"
        );
        $stmt->execute(['ws' => $this->workspaceId, 'pid' => $projectId, 'rid' => $relatedProjectId, 'by' => $this->ownerUserId]);
    }

    private function insertUser(string $name): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES (:name, :email, NULL, "active", 0)');
        $stmt->execute(['name' => $name, 'email' => $name . uniqid() . '@test.local']);

        return (int) $this->db->lastInsertId();
    }

    private function insertWorkspace(string $code): int
    {
        $stmt = $this->db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, "A7 Test WS", "active", :user)');
        $stmt->execute(['code' => $code . '-' . uniqid(), 'user' => $this->ownerUserId]);

        return (int) $this->db->lastInsertId();
    }

    private function insertProject(int $workspaceId, string $code, string $status, string $health, int $progress, ?int $parentId = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO projects (workspace_id, parent_project_id, code, name, status, health, progress_percent, development_mode, owner_user_id)
             VALUES (:ws, :parent, :code, :name, :status, :health, :progress, "manual", :owner)'
        );
        $stmt->execute([
            'ws' => $workspaceId, 'parent' => $parentId, 'code' => $code, 'name' => 'Project ' . $code,
            'status' => $status, 'health' => $health, 'progress' => $progress, 'owner' => $this->ownerUserId,
        ]);

        return (int) $this->db->lastInsertId();
    }
}
