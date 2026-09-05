<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use PDO;

/**
 * Dashboard read model (M2 — read-only aggregation)
 *
 * หลักการ:
 *  - อ่านอย่างเดียว — ไม่มี write ใดๆ (Backward Compatible: ไม่แตะตารางเดิม)
 *  - ทุก query workspace-scoped ด้วย parameter ที่ bind ชัดเจน (ไม่มี string interpolation ของค่า)
 *  - Portfolio (cross-workspace) ใช้เมธอดแยก เรียกได้เฉพาะผ่าน is_platform_admin path
 *
 * หมายเหตุ: ไม่ extends BaseRepository เพราะ aggregation ครอบหลายตาราง —
 * workspace scoping ทำด้วยพารามิเตอร์ :workspace_id ใน WHERE/JOIN ทุก query แทน
 */
final class MySqlDashboardRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    // ===== Workspace level =====

    /**
     * @return array<string, mixed>
     */
    public function workspaceProjectStats(int $workspaceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = 'planning'), 0) AS planning,
                    COALESCE(SUM(status = 'active'), 0) AS active,
                    COALESCE(SUM(status = 'on_hold'), 0) AS on_hold,
                    COALESCE(SUM(status = 'closed'), 0) AS closed,
                    COALESCE(SUM(health = 'green'), 0) AS green,
                    COALESCE(SUM(health = 'yellow'), 0) AS yellow,
                    COALESCE(SUM(health = 'red'), 0) AS red,
                    COALESCE(SUM(development_mode = 'manual'), 0) AS manual,
                    COALESCE(SUM(development_mode = 'ai_assisted'), 0) AS ai_assisted,
                    COALESCE(SUM(development_mode = 'ai_dev_auto'), 0) AS ai_dev_auto,
                    COALESCE(ROUND(AVG(progress_percent)), 0) AS avg_progress,
                    COALESCE(ROUND(AVG(profile_completeness_percent)), 0) AS avg_completeness
             FROM projects WHERE workspace_id = :workspace_id"
        );
        $stmt->execute(['workspace_id' => $workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? array_map('intval', $row) : [];
    }

    /**
     * @return array<string, int>
     */
    public function workspaceMilestoneStats(int $workspaceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = 'open'), 0) AS open,
                    COALESCE(SUM(status = 'closed'), 0) AS closed
             FROM milestones WHERE workspace_id = :workspace_id"
        );
        $stmt->execute(['workspace_id' => $workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? array_map('intval', $row) : ['total' => 0, 'open' => 0, 'closed' => 0];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function workspaceUpcomingMilestones(int $workspaceId, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.id, m.project_id, p.code AS project_code, m.code, m.title, m.planned_date
             FROM milestones m
             JOIN projects p ON p.id = m.project_id
             WHERE m.workspace_id = :workspace_id AND m.status = 'open' AND m.planned_date IS NOT NULL
             ORDER BY m.planned_date ASC, m.id ASC
             LIMIT :lim"
        );
        $stmt->bindValue('workspace_id', $workspaceId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function workspaceRecentReleases(int $workspaceId, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT r.id, r.project_id, p.code AS project_code, r.release_type, r.version_label, r.status, r.released_at, r.created_at
             FROM project_releases r
             JOIN projects p ON p.id = r.project_id
             WHERE r.workspace_id = :workspace_id
             ORDER BY COALESCE(r.released_at, r.created_at) DESC, r.id DESC
             LIMIT :lim"
        );
        $stmt->bindValue('workspace_id', $workspaceId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, int>
     */
    public function workspaceGovernanceStats(int $workspaceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(adoption_status = 'compliant'), 0) AS compliant,
                    COALESCE(SUM(adoption_status = 'in_progress'), 0) AS in_progress,
                    COALESCE(SUM(adoption_status = 'non_compliant'), 0) AS non_compliant,
                    COALESCE(SUM(adoption_status = 'retired'), 0) AS retired
             FROM governance_adoptions WHERE workspace_id = :workspace_id"
        );
        $stmt->execute(['workspace_id' => $workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? array_map('intval', $row) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentActivities(?int $workspaceId, int $limit): array
    {
        $sql = "SELECT a.id, a.workspace_id, a.action, a.entity_type, a.entity_id, a.user_id, u.name AS user_name, a.created_at
                FROM audit_trails a
                LEFT JOIN users u ON u.id = a.user_id";
        $params = [];

        if ($workspaceId !== null) {
            $sql .= " WHERE a.workspace_id = :workspace_id";
            $params['workspace_id'] = $workspaceId;
        }

        $sql .= " ORDER BY a.created_at DESC, a.id DESC LIMIT :lim";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Progress summary buckets ของ workspace (0-25 / 26-50 / 51-75 / 76-100)
     *
     * @return array<string, int>
     */
    public function workspaceProgressBuckets(int $workspaceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(progress_percent <= 25), 0) AS bucket_0_25,
                    COALESCE(SUM(progress_percent BETWEEN 26 AND 50), 0) AS bucket_26_50,
                    COALESCE(SUM(progress_percent BETWEEN 51 AND 75), 0) AS bucket_51_75,
                    COALESCE(SUM(progress_percent >= 76), 0) AS bucket_76_100
             FROM projects WHERE workspace_id = :workspace_id"
        );
        $stmt->execute(['workspace_id' => $workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? array_map('intval', $row) : [];
    }

    // ===== Portfolio level (is_platform_admin เท่านั้น) =====

    /**
     * @return array<int, array<string, mixed>>
     */
    public function portfolioWorkspaceRows(): array
    {
        $stmt = $this->db->query(
            "SELECT w.id, w.name, w.status,
                    COUNT(p.id) AS projects,
                    COALESCE(ROUND(AVG(p.progress_percent)), 0) AS avg_progress,
                    COALESCE(SUM(p.health = 'green'), 0) AS green,
                    COALESCE(SUM(p.health = 'yellow'), 0) AS yellow,
                    COALESCE(SUM(p.health = 'red'), 0) AS red
             FROM workspaces w
             LEFT JOIN projects p ON p.workspace_id = w.id
             GROUP BY w.id, w.name, w.status
             ORDER BY w.id ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>
     */
    public function portfolioTotals(): array
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) AS total_projects,
                    COALESCE(SUM(status = 'planning'), 0) AS planning,
                    COALESCE(SUM(status = 'active'), 0) AS active,
                    COALESCE(SUM(status = 'on_hold'), 0) AS on_hold,
                    COALESCE(SUM(status = 'closed'), 0) AS closed,
                    COALESCE(SUM(health = 'green'), 0) AS green,
                    COALESCE(SUM(health = 'yellow'), 0) AS yellow,
                    COALESCE(SUM(health = 'red'), 0) AS red,
                    COALESCE(SUM(development_mode = 'manual'), 0) AS manual,
                    COALESCE(SUM(development_mode = 'ai_assisted'), 0) AS ai_assisted,
                    COALESCE(SUM(development_mode = 'ai_dev_auto'), 0) AS ai_dev_auto,
                    COALESCE(ROUND(AVG(progress_percent)), 0) AS avg_progress
             FROM projects"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $totals = $row !== false ? array_map('intval', $row) : [];

        $ws = $this->db->query('SELECT COUNT(*) AS c FROM workspaces')->fetch(PDO::FETCH_ASSOC);
        $totals['workspaces'] = $ws !== false ? (int) $ws['c'] : 0;

        return $totals;
    }

    /**
     * @return array<string, int>
     */
    public function portfolioDependencyStats(): array
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(dependency_type = 'depends_on'), 0) AS depends_on,
                    COALESCE(SUM(dependency_type = 'blocked_by'), 0) AS blocked_by
             FROM project_dependencies"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? array_map('intval', $row) : [];
    }

    // ===== Project level =====

    /**
     * @return array<string, mixed>|null
     */
    public function projectRow(int $projectId, int $workspaceId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, workspace_id, parent_project_id, source_template_id, code, abbreviation, name, description,
                    status, development_mode, progress_percent, health, profile_completeness_percent, owner_user_id
             FROM projects WHERE id = :id AND workspace_id = :workspace_id LIMIT 1'
        );
        $stmt->execute(['id' => $projectId, 'workspace_id' => $workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @return array<string, int>
     */
    public function projectMilestoneStats(int $projectId): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = 'open'), 0) AS open,
                    COALESCE(SUM(status = 'closed'), 0) AS closed
             FROM milestones WHERE project_id = :project_id"
        );
        $stmt->execute(['project_id' => $projectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? array_map('intval', $row) : [];
    }

    /**
     * @return array<string, mixed>|null next open milestone ตาม planned_date
     */
    public function projectNextMilestone(int $projectId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, code, title, planned_date FROM milestones
             WHERE project_id = :project_id AND status = 'open' AND planned_date IS NOT NULL
             ORDER BY planned_date ASC, id ASC LIMIT 1"
        );
        $stmt->execute(['project_id' => $projectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @return array<string, int>
     */
    public function projectTeamStats(int $projectId): array
    {
        $humans = $this->db->prepare('SELECT COUNT(*) FROM project_member_assignments WHERE project_id = :pid AND revoked_at IS NULL');
        $humans->execute(['pid' => $projectId]);

        $ai = $this->db->prepare('SELECT COUNT(*) FROM project_ai_assignments WHERE project_id = :pid AND revoked_at IS NULL');
        $ai->execute(['pid' => $projectId]);

        return ['humans' => (int) $humans->fetchColumn(), 'ai_agents' => (int) $ai->fetchColumn()];
    }

    /**
     * @return array<string, mixed>
     */
    public function projectReleaseStats(int $projectId): array
    {
        $total = $this->db->prepare('SELECT COUNT(*) FROM project_releases WHERE project_id = :pid');
        $total->execute(['pid' => $projectId]);

        $latest = $this->db->prepare(
            'SELECT release_type, version_label, status, COALESCE(released_at, created_at) AS at
             FROM project_releases WHERE project_id = :pid
             ORDER BY COALESCE(released_at, created_at) DESC, id DESC LIMIT 1'
        );
        $latest->execute(['pid' => $projectId]);
        $latestRow = $latest->fetch(PDO::FETCH_ASSOC);

        return [
            'total' => (int) $total->fetchColumn(),
            'latest' => $latestRow !== false ? $latestRow : null,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function projectEnvironmentStats(int $projectId): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(environment = 'development'), 0) AS development,
                    COALESCE(SUM(environment = 'uat'), 0) AS uat,
                    COALESCE(SUM(environment = 'production'), 0) AS production
             FROM project_environments WHERE project_id = :project_id"
        );
        $stmt->execute(['project_id' => $projectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? array_map('intval', $row) : [];
    }

    /**
     * @return array<string, int>
     */
    public function projectDependencyStats(int $projectId): array
    {
        $out = $this->db->prepare("SELECT COUNT(*) FROM project_dependencies WHERE project_id = :pid AND dependency_type = 'depends_on'");
        $out->execute(['pid' => $projectId]);

        $in = $this->db->prepare("SELECT COUNT(*) FROM project_dependencies WHERE related_project_id = :pid AND dependency_type = 'depends_on'");
        $in->execute(['pid' => $projectId]);

        $blocked = $this->db->prepare("SELECT COUNT(*) FROM project_dependencies WHERE project_id = :pid AND dependency_type = 'blocked_by'");
        $blocked->execute(['pid' => $projectId]);

        return [
            'depends_on' => (int) $out->fetchColumn(),
            'depended_on_by' => (int) $in->fetchColumn(),
            'blocked_by' => (int) $blocked->fetchColumn(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function projectGovernanceStats(int $projectId): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS active_adoptions,
                    COALESCE(SUM(adoption_status = 'non_compliant'), 0) AS non_compliant
             FROM governance_adoptions WHERE project_id = :project_id AND status = 'active'"
        );
        $stmt->execute(['project_id' => $projectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? array_map('intval', $row) : [];
    }

    /**
     * @return array<string, int>
     */
    public function projectRevisionStats(int $projectId): array
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(status = 'submitted'), 0) AS submitted,
                    COALESCE(SUM(status = 'cto_approved'), 0) AS cto_approved,
                    COALESCE(SUM(status = 'cto_rejected'), 0) AS cto_rejected,
                    COALESCE(SUM(status = 'committed'), 0) AS committed
             FROM revisions WHERE project_id = :project_id"
        );
        $stmt->execute(['project_id' => $projectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? array_map('intval', $row) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function projectChildren(int $projectId, int $workspaceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, code, name, status, health, progress_percent, development_mode
             FROM projects WHERE parent_project_id = :parent_id AND workspace_id = :workspace_id
             ORDER BY id ASC'
        );
        $stmt->execute(['parent_id' => $projectId, 'workspace_id' => $workspaceId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countRepositories(int $projectId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM repositories WHERE project_id = :pid');
        $stmt->execute(['pid' => $projectId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Project Activity History — audit rows ที่อ้าง project นี้ (entity_type='project')
     * รวม action ที่ entity เป็นลูกของ project โดยตรง (entity_type อื่นที่ entity_id ชี้ตารางอื่น
     * จะไม่ถูก merge เพื่อหลีกเลี่ยง false positive — ดู project dashboard/timeline สำหรับ event รายชนิด)
     *
     * @return array<int, array<string, mixed>>
     */
    public function projectActivities(int $projectId, int $workspaceId, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.id, a.action, a.entity_type, a.entity_id, a.user_id, u.name AS user_name, a.created_at, a.after_value
             FROM audit_trails a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.workspace_id = :workspace_id AND a.entity_type = 'project' AND a.entity_id = :project_id
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT :lim"
        );
        $stmt->bindValue('workspace_id', $workspaceId, PDO::PARAM_INT);
        $stmt->bindValue('project_id', $projectId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Audit API (M5) — audit logs ของ workspace พร้อม filters
     *
     * @return array<int, array<string, mixed>>
     */
    public function auditLogs(int $workspaceId, int $limit, ?string $entityType, ?int $entityId): array
    {
        $conditions = [];
        $params = ['workspace_id' => $workspaceId];

        if ($entityType !== null) {
            $conditions[] = 'a.entity_type = :entity_type';
            $params['entity_type'] = $entityType;
        }
        if ($entityId !== null) {
            $conditions[] = 'a.entity_id = :entity_id';
            $params['entity_id'] = $entityId;
        }

        $stmt = $this->db->prepare(
            "SELECT a.id, a.workspace_id, a.user_id, u.name AS user_name, a.action, a.entity_type, a.entity_id,
                    a.before_value, a.after_value, a.ip_address, a.created_at
             FROM audit_trails a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.workspace_id = :workspace_id
             " . ($conditions !== [] ? 'AND ' . implode(' AND ', $conditions) : '') . "
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT :lim"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ===== Timeline (read model — ต่อ event source) =====

    /**
     * @return array<int, array<string, mixed>>
     */
    public function timelineStatusUpdates(int $projectId, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, report_date AS occurred_at, overall_status AS detail, summary
             FROM project_status_updates WHERE project_id = :pid ORDER BY report_date DESC, id DESC LIMIT :lim"
        );
        $stmt->bindValue('pid', $projectId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function timelineClosedMilestones(int $projectId, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, closed_at AS occurred_at, code, title FROM milestones
             WHERE project_id = :pid AND status = 'closed' AND closed_at IS NOT NULL
             ORDER BY closed_at DESC, id DESC LIMIT :lim"
        );
        $stmt->bindValue('pid', $projectId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function timelineReleases(int $projectId, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, COALESCE(released_at, created_at) AS occurred_at, release_type, version_label, status
             FROM project_releases WHERE project_id = :pid
             ORDER BY COALESCE(released_at, created_at) DESC, id DESC LIMIT :lim"
        );
        $stmt->bindValue('pid', $projectId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function timelineRevisions(int $projectId, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, submitted_at AS occurred_at, status, summary FROM revisions
             WHERE project_id = :pid ORDER BY submitted_at DESC, id DESC LIMIT :lim"
        );
        $stmt->bindValue('pid', $projectId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Workspace-wide timeline: milestones/releases/status updates ล่าสุดทุก project ใน workspace
     *
     * @return array<int, array<string, mixed>>
     */
    public function workspaceTimeline(int $workspaceId, int $limit): array
    {
        $stmt = $this->db->prepare(
            "(SELECT 'milestone_closed' AS event_type, m.closed_at AS occurred_at, p.code AS project_code, m.code, m.title AS detail
              FROM milestones m JOIN projects p ON p.id = m.project_id
              WHERE m.workspace_id = :workspace_id AND m.status = 'closed' AND m.closed_at IS NOT NULL)
             UNION ALL
             (SELECT 'release', COALESCE(r.released_at, r.created_at), p.code, r.release_type, r.version_label
              FROM project_releases r JOIN projects p ON p.id = r.project_id
              WHERE r.workspace_id = :workspace_id)
             UNION ALL
             (SELECT 'status_update', s.report_date, p.code, s.overall_status, s.summary
              FROM project_status_updates s JOIN projects p ON p.id = s.project_id
              WHERE s.workspace_id = :workspace_id)
             ORDER BY occurred_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue('workspace_id', $workspaceId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
