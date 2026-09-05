<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Dashboard\DashboardService;
use App\Infrastructure\Persistence\MySQL\MySqlDashboardRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * DashboardTest — M2 Portfolio Dashboard (read model)
 *
 * ครอบคลุม M2 Scope: Workspace Dashboard, Project/Parent Dashboard, Timeline,
 * Recent Activities, Progress Summary, Health Summary, Portfolio Statistics
 * + workspace isolation (fail-closed)
 *
 * รันบน DB ที่ migrate ครบ (ไม่ต้องมี migration ใหม่ — M2 read-only)
 */
final class DashboardTest extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $otherWorkspaceId;
    private int $ownerUserId;
    private int $ctoUserId;
    private int $devUserId;
    private int $parentProjectId;
    private int $childProjectId;
    private DashboardService $service;

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $this->ownerUserId = $this->insertUser('dash-owner', true);
        $this->ctoUserId = $this->insertUser('dash-cto');
        $this->devUserId = $this->insertUser('dash-dev');
        $this->workspaceId = $this->insertWorkspace('dash-ws', $this->ownerUserId);
        $this->otherWorkspaceId = $this->insertWorkspace('dash-other', $this->ownerUserId);

        $this->parentProjectId = $this->insertProject($this->workspaceId, 'DASH-P', 'active', 'green', 60);
        $this->childProjectId = $this->insertProject($this->workspaceId, 'DASH-C', 'active', 'red', 20, $this->parentProjectId);
        $this->insertProject($this->workspaceId, 'DASH-Q', 'planning', 'yellow', 45);
        // project ในอีก workspace — ใช้ตรวจ isolation
        $this->insertProject($this->otherWorkspaceId, 'DASH-X', 'active', 'green', 90);

        $this->seedTeam($this->parentProjectId);
        $this->seedMilestone($this->parentProjectId, 'M1', 'Sign-off', 'closed');
        $this->seedMilestone($this->parentProjectId, 'M2', 'UAT', 'open', date('Y-m-d', time() + 86400 * 7));
        $this->seedRelease($this->parentProjectId, 'beta', '0.9.0-beta1', 'released');
        $this->seedStatusUpdate($this->parentProjectId, 'on_track');
        $this->seedAudit($this->workspaceId, 'project_created', 'project', $this->parentProjectId);
        $this->seedAudit($this->otherWorkspaceId, 'other_ws_action', 'project', 1);

        $this->service = new DashboardService(
            new MySqlDashboardRepository($this->db),
            $this->workspaceId
        );
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    // ===== Workspace Dashboard =====

    public function testWorkspaceDashboardAggregates(): void
    {
        $dashboard = $this->service->workspaceDashboard();

        // มี 3 projects ใน workspace นี้ (DASH-X อีก workspace ต้องไม่ถูกนับ)
        $this->assertSame(3, $dashboard['projects']['total']);
        $this->assertSame(2, $dashboard['projects']['active']);
        $this->assertSame(1, $dashboard['projects']['planning']);
        $this->assertSame(1, $dashboard['projects']['green']);
        $this->assertSame(1, $dashboard['projects']['yellow']);
        $this->assertSame(1, $dashboard['projects']['red']);
        // avg(60,20,45) = 42 (rounded)
        $this->assertSame(42, $dashboard['projects']['avg_progress']);

        $this->assertSame(2, $dashboard['milestones']['total']);
        $this->assertSame(1, $dashboard['milestones']['open']);
        $this->assertSame(1, $dashboard['milestones']['closed']);
        $this->assertCount(1, $dashboard['upcoming_milestones']);
        $this->assertSame('M2', $dashboard['upcoming_milestones'][0]['code']);
        $this->assertCount(1, $dashboard['recent_releases']);
    }

    public function testWorkspaceDashboardIsolatedFromOtherWorkspace(): void
    {
        $other = new DashboardService(new MySqlDashboardRepository($this->db), $this->otherWorkspaceId);
        $dashboard = $other->workspaceDashboard();

        $this->assertSame(1, $dashboard['projects']['total'], 'workspace อื่นต้องเห็นเฉพาะ project ของตัวเอง');
        $this->assertSame(90, $dashboard['projects']['avg_progress']);
    }

    // ===== Progress / Health Summary (แยกกันชัดเจน) =====

    public function testProgressSummaryBuckets(): void
    {
        $summary = $this->service->progressSummary();

        $this->assertSame(42, $summary['average_progress_percent']);
        $this->assertSame(1, $summary['buckets']['0-25']);    // 20
        $this->assertSame(1, $summary['buckets']['26-50']);   // 45
        $this->assertSame(1, $summary['buckets']['51-75']);   // 60
        $this->assertSame(0, $summary['buckets']['76-100']);
    }

    public function testHealthSummarySeparateFromProgress(): void
    {
        $health = $this->service->healthSummary();

        $this->assertSame(3, $health['total_projects']);
        $this->assertSame(1, $health['green']);
        $this->assertSame(1, $health['yellow']);
        $this->assertSame(1, $health['red']);
        // health summary ต้องไม่มี progress fields (แยก concept ตาม M0 R6)
        $this->assertArrayNotHasKey('average_progress_percent', $health);
    }

    public function testStatisticsPayload(): void
    {
        $stats = $this->service->statistics();

        $this->assertSame(3, $stats['projects']['total']);
        $modeSum = $stats['projects']['by_development_mode']['manual']
            + $stats['projects']['by_development_mode']['ai_assisted']
            + $stats['projects']['by_development_mode']['ai_dev_auto'];
        $this->assertSame(3, $modeSum, 'ทุก project ต้องถูกนับใน development mode ใด mode หนึ่ง');
        $this->assertArrayHasKey('governance', $stats);
    }

    // ===== Recent Activities =====

    public function testRecentActivitiesWorkspaceScoped(): void
    {
        $activities = $this->service->recentActivities(10);

        $this->assertNotEmpty($activities);
        foreach ($activities as $activity) {
            $this->assertNotSame('other_ws_action', $activity['action'], 'activity ของ workspace อื่นห้ามหลุดมา');
        }
    }

    // ===== Project Dashboard + Parent roll-up =====

    public function testProjectDashboardPayload(): void
    {
        $dashboard = $this->service->projectDashboard($this->parentProjectId);
        $this->assertNotNull($dashboard);

        $this->assertSame('DASH-P', $dashboard['project']['code']);
        $this->assertSame(60, $dashboard['project']['progress_percent']);
        $this->assertSame(2, $dashboard['milestones']['total']);
        $this->assertSame('M2', $dashboard['milestones']['next']['code']);
        $this->assertSame(1, $dashboard['team']['humans'], 'team ledger ที่ seed ไว้ต้องถูกนับ');
        $this->assertSame(1, $dashboard['releases']['total']);
        $this->assertSame('0.9.0-beta1', $dashboard['releases']['latest']['version_label']);
        $this->assertArrayHasKey('governance', $dashboard);
        $this->assertArrayHasKey('revisions', $dashboard);
        $this->assertArrayHasKey('timeline', $dashboard);
    }

    public function testParentDashboardIncludesChildrenRollup(): void
    {
        $dashboard = $this->service->projectDashboard($this->parentProjectId);

        $this->assertArrayHasKey('children', $dashboard, 'parent ต้องมี children roll-up');
        $this->assertSame(1, $dashboard['children_rollup']['total']);
        $this->assertSame(20, $dashboard['children_rollup']['avg_progress_percent']);
        $this->assertSame(1, $dashboard['children_rollup']['health']['red']);
    }

    public function testChildDashboardHasNoChildrenSection(): void
    {
        $dashboard = $this->service->projectDashboard($this->childProjectId);

        $this->assertNotNull($dashboard);
        $this->assertArrayNotHasKey('children', $dashboard, 'leaf project ต้องไม่มี children section');
    }

    public function testProjectDashboardFailClosedForOtherWorkspace(): void
    {
        $otherProjectId = $this->db->query("SELECT id FROM projects WHERE code = 'DASH-X'")->fetchColumn();

        $this->assertNull($this->service->projectDashboard((int) $otherProjectId), 'project ต่าง workspace ต้อง fail-closed');
    }

    // ===== Timeline =====

    public function testProjectTimelineMergesEventSources(): void
    {
        $timeline = $this->service->projectTimeline($this->parentProjectId, 20);

        $types = array_column($timeline, 'event_type');
        $this->assertContains('status_update', $types);
        $this->assertContains('milestone_closed', $types);
        $this->assertContains('release', $types);

        // เรียงจากใหม่ไปเก่า
        $occurred = array_column($timeline, 'occurred_at');
        $sorted = $occurred;
        rsort($sorted);
        $this->assertSame($sorted, $occurred, 'timeline ต้องเรียงจากใหม่ไปเก่า');
    }

    public function testProjectTimelineFailClosedForOtherWorkspace(): void
    {
        $otherProjectId = (int) $this->db->query("SELECT id FROM projects WHERE code = 'DASH-X'")->fetchColumn();

        $this->assertSame([], $this->service->projectTimeline($otherProjectId, 20));
    }

    public function testWorkspaceTimelineMergesAcrossProjects(): void
    {
        $timeline = $this->service->workspaceTimeline(50);

        $this->assertNotEmpty($timeline);
        $types = array_unique(array_column($timeline, 'event_type'));
        $this->assertContains('milestone_closed', $types);
        $this->assertContains('release', $types);
    }

    // ===== Portfolio (is_platform_admin path — service level) =====

    public function testPortfolioDashboardCrossWorkspace(): void
    {
        $portfolio = $this->service->portfolioDashboard();

        // เห็นทุก workspace (รวม other workspace ที่มี DASH-X)
        $this->assertSame(2, $portfolio['totals']['workspaces']);
        $this->assertSame(4, $portfolio['totals']['total_projects']);
        $this->assertCount(2, $portfolio['workspaces']);
        $this->assertArrayHasKey('dependencies', $portfolio);
        $this->assertArrayHasKey('recent_activities', $portfolio);
    }

    // ===== helpers =====

    private function insertUser(string $name, bool $isAdmin = false): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES (:name, :email, NULL, "active", :admin)');
        $stmt->execute(['name' => $name, 'email' => $name . uniqid() . '@test.local', 'admin' => $isAdmin ? 1 : 0]);

        return (int) $this->db->lastInsertId();
    }

    private function insertWorkspace(string $code, int $createdBy): int
    {
        $stmt = $this->db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, :name, "active", :user)');
        $stmt->execute(['code' => $code . '-' . uniqid(), 'name' => 'Dashboard Test WS', 'user' => $createdBy]);

        return (int) $this->db->lastInsertId();
    }

    private function insertProject(int $workspaceId, string $code, string $status, string $health, int $progress, ?int $parentId = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO projects (workspace_id, parent_project_id, code, name, status, health, progress_percent, development_mode, owner_user_id)
             VALUES (:ws, :parent, :code, :name, :status, :health, :progress, "ai_dev_auto", :owner)'
        );
        $stmt->execute([
            'ws' => $workspaceId,
            'parent' => $parentId,
            'code' => $code,
            'name' => 'Project ' . $code,
            'status' => $status,
            'health' => $health,
            'progress' => $progress,
            'owner' => $this->ownerUserId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function seedTeam(int $projectId): void
    {
        $memberRole = (int) $this->db->query("SELECT id FROM roles WHERE code = 'MEMBER' LIMIT 1")->fetchColumn();
        $stmt = $this->db->prepare(
            'INSERT INTO project_member_assignments (project_id, workspace_id, user_id, role_id, assignment_source, assigned_by)
             VALUES (:pid, :ws, :user, :role, "direct", :by)'
        );
        $stmt->execute(['pid' => $projectId, 'ws' => $this->workspaceId, 'user' => $this->ctoUserId, 'role' => $memberRole, 'by' => $this->ownerUserId]);
    }

    private function seedMilestone(int $projectId, string $code, string $title, string $status, ?string $plannedDate = null): void
    {
        $closedAt = $status === 'closed' ? date('Y-m-d H:i:s', time() - 86400) : null;
        $stmt = $this->db->prepare(
            'INSERT INTO milestones (project_id, workspace_id, code, title, status, planned_date, closed_by, closed_at, created_by)
             VALUES (:pid, :ws, :code, :title, :status, :planned, :closed_by, :closed_at, :by)'
        );
        $stmt->execute([
            'pid' => $projectId,
            'ws' => $this->workspaceId,
            'code' => $code,
            'title' => $title,
            'status' => $status,
            'planned' => $plannedDate,
            'closed_by' => $closedAt !== null ? $this->ctoUserId : null,
            'closed_at' => $closedAt,
            'by' => $this->ownerUserId,
        ]);
    }

    private function seedRelease(int $projectId, string $type, string $version, string $status): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO project_releases (project_id, workspace_id, release_type, version_label, status, released_by, released_at, created_by)
             VALUES (:pid, :ws, :type, :version, :status, :by, :released_at, :by)'
        );
        $stmt->execute([
            'pid' => $projectId,
            'ws' => $this->workspaceId,
            'type' => $type,
            'version' => $version,
            'status' => $status,
            'by' => $this->ownerUserId,
            'released_at' => date('Y-m-d H:i:s', time() - 3600),
        ]);
    }

    private function seedStatusUpdate(int $projectId, string $overallStatus): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO project_status_updates (project_id, workspace_id, report_date, overall_status, summary, submitted_by)
             VALUES (:pid, :ws, :report_date, :status, "summary text", :by)'
        );
        $stmt->execute([
            'pid' => $projectId,
            'ws' => $this->workspaceId,
            'report_date' => date('Y-m-d'),
            'status' => $overallStatus,
            'by' => $this->ownerUserId,
        ]);
    }

    private function seedAudit(int $workspaceId, string $action, string $entityType, int $entityId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_trails (workspace_id, user_id, action, entity_type, entity_id) VALUES (:ws, :user, :action, :entity_type, :entity_id)'
        );
        $stmt->execute(['ws' => $workspaceId, 'user' => $this->ownerUserId, 'action' => $action, 'entity_type' => $entityType, 'entity_id' => $entityId]);
    }
}
