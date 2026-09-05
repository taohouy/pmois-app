<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use PDO;

/**
 * Analytics read model (M7 — read-only, ไม่มี schema change)
 *
 * ทุก query workspace-scoped ด้วยพารามิเตอร์ที่ bind ชัดเจน;
 * portfolio-level (cross-workspace) อยู่ในเมธอดแยก เรียกผ่าน is_platform_admin path เท่านั้น
 */
final class MySqlAnalyticsRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    // ===== Milestone Analytics =====

    /**
     * @return array<string, int|float>
     */
    public function milestoneStats(int $workspaceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = 'open'), 0) AS open,
                    COALESCE(SUM(status = 'closed'), 0) AS closed,
                    COALESCE(SUM(status = 'closed' AND planned_date IS NOT NULL
                              AND DATE(closed_at) <= planned_date), 0) AS closed_on_time,
                    COALESCE(SUM(status = 'closed' AND planned_date IS NOT NULL
                              AND DATE(closed_at) > planned_date), 0) AS closed_late,
                    COALESCE(SUM(status = 'open' AND planned_date IS NOT NULL AND planned_date < CURDATE()), 0) AS overdue_open
             FROM milestones WHERE workspace_id = :workspace_id"
        );
        $stmt->execute(['workspace_id' => $workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? array_map('intval', $row) : [];
    }

    /**
     * Monthly closed-milestone trend (สำหรับ chart) — ใหม่→เก่า
     *
     * @return array<int, array<string, mixed>>
     */
    public function milestoneMonthlyTrend(int $workspaceId, int $months): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(closed_at, '%Y-%m') AS month, COUNT(*) AS closed
             FROM milestones
             WHERE workspace_id = :workspace_id AND status = 'closed' AND closed_at IS NOT NULL
             GROUP BY month ORDER BY month DESC LIMIT :lim"
        );
        $stmt->bindValue('workspace_id', $workspaceId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $months, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function upcomingMilestonesPerProject(int $workspaceId, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.id AS project_id, p.code AS project_code, COUNT(*) AS upcoming,
                    MIN(m.planned_date) AS next_date
             FROM milestones m JOIN projects p ON p.id = m.project_id
             WHERE m.workspace_id = :workspace_id AND m.status = 'open' AND m.planned_date IS NOT NULL
             GROUP BY p.id, p.code ORDER BY next_date ASC LIMIT :lim"
        );
        $stmt->bindValue('workspace_id', $workspaceId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ===== CTO / Dev Productivity =====

    /**
     * CTO productivity: จำนวน review + average turnaround (ชั่วโมง จาก submit → review)
     *
     * @return array<int, array<string, mixed>>
     */
    public function reviewerStats(int $workspaceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT rr.reviewed_by AS user_id, u.name AS user_name, COUNT(*) AS reviews,
                    ROUND(AVG(TIMESTAMPDIFF(HOUR, r.submitted_at, rr.reviewed_at)), 1) AS avg_review_hours
             FROM revision_reviews rr
             JOIN revisions r ON r.id = rr.revision_id
             JOIN users u ON u.id = rr.reviewed_by
             WHERE r.workspace_id = :workspace_id
             GROUP BY rr.reviewed_by, u.name ORDER BY reviews DESC"
        );
        $stmt->execute(['workspace_id' => $workspaceId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Dev productivity: submissions, commits, commit rate, avg cycle time (submit → commit, ชั่วโมง)
     * รวม AI attribution แยกคอลัมน์ (dev_ai_consumer_id)
     *
     * @return array<int, array<string, mixed>>
     */
    public function developerStats(int $workspaceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(r.dev_user_id, r.submitted_by) AS user_id,
                    u.name AS user_name,
                    COUNT(*) AS submissions,
                    COALESCE(SUM(r.status = 'committed'), 0) AS commits,
                    COALESCE(SUM(r.dev_ai_consumer_id IS NOT NULL), 0) AS ai_attributed,
                    ROUND(AVG(CASE WHEN r.status = 'committed' THEN
                        TIMESTAMPDIFF(HOUR, r.submitted_at, r.committed_at) END), 1) AS avg_cycle_hours
             FROM revisions r
             LEFT JOIN users u ON u.id = COALESCE(r.dev_user_id, r.submitted_by)
             WHERE r.workspace_id = :workspace_id
             GROUP BY COALESCE(r.dev_user_id, r.submitted_by), u.name ORDER BY submissions DESC"
        );
        $stmt->execute(['workspace_id' => $workspaceId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Review turnaround รวมของ workspace (ชั่วโมง)
     */
    public function avgReviewTurnaroundHours(int $workspaceId): ?float
    {
        $stmt = $this->db->prepare(
            "SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, r.submitted_at, rr.reviewed_at)), 1)
             FROM revision_reviews rr JOIN revisions r ON r.id = rr.revision_id
             WHERE r.workspace_id = :workspace_id"
        );
        $stmt->execute(['workspace_id' => $workspaceId]);
        $value = $stmt->fetchColumn();

        return $value !== false && $value !== null ? (float) $value : null;
    }

    // ===== Project Health Analytics =====

    /**
     * Projects ที่ต้องเฝ้าดู (yellow/red) พร้อมเหตุผลเชิงข้อมูล: overdue milestones,
     * open known issues (critical/high), failed deployments ล่าสุด
     *
     * @return array<int, array<string, mixed>>
     */
    public function atRiskProjects(int $workspaceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.id, p.code, p.name, p.health, p.progress_percent,
                    (SELECT COUNT(*) FROM milestones m
                      WHERE m.project_id = p.id AND m.status = 'open' AND m.planned_date < CURDATE()) AS overdue_milestones,
                    (SELECT COUNT(*) FROM knowledge_entries k
                      WHERE k.project_id = p.id AND k.entry_type = 'known_issue'
                        AND k.status IN ('open', 'workaround') AND k.severity IN ('high', 'critical')) AS critical_known_issues,
                    (SELECT COUNT(*) FROM project_deployments d
                      WHERE d.project_id = p.id AND d.status = 'failed') AS failed_deployments
             FROM projects p
             WHERE p.workspace_id = :workspace_id AND p.health IN ('yellow', 'red') AND p.status = 'active'
             ORDER BY FIELD(p.health, 'red', 'yellow'), overdue_milestones DESC"
        );
        $stmt->execute(['workspace_id' => $workspaceId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ===== Dependency Analytics =====

    /**
     * Projects ที่ถูกพึ่งพามากที่สุด (most depended-on) และที่พึ่งพาผู้อื่นมากที่สุด
     *
     * @return array<int, array<string, mixed>>
     */
    public function dependencyLeaders(int $workspaceId): array
    {
        $depended = $this->db->prepare(
            "SELECT p.id AS project_id, p.code, COUNT(*) AS depended_on_count
             FROM project_dependencies d JOIN projects p ON p.id = d.related_project_id
             WHERE d.workspace_id = :workspace_id AND d.dependency_type = 'depends_on'
             GROUP BY p.id, p.code ORDER BY depended_on_count DESC LIMIT 5"
        );
        $depended->execute(['workspace_id' => $workspaceId]);

        $depending = $this->db->prepare(
            "SELECT p.id AS project_id, p.code, COUNT(*) AS depends_on_count
             FROM project_dependencies d JOIN projects p ON p.id = d.project_id
             WHERE d.workspace_id = :workspace_id AND d.dependency_type = 'depends_on'
             GROUP BY p.id, p.code ORDER BY depends_on_count DESC LIMIT 5"
        );
        $depending->execute(['workspace_id' => $workspaceId]);

        return [
            'most_depended_on' => $depended->fetchAll(PDO::FETCH_ASSOC),
            'most_depending' => $depending->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    // ===== Portfolio (cross-workspace — platform admin) =====

    /**
     * @return array<int, array<string, mixed>>
     */
    public function portfolioWorkspaceKpis(): array
    {
        $stmt = $this->db->query(
            "SELECT w.id, w.name,
                    COUNT(p.id) AS projects,
                    COALESCE(ROUND(AVG(p.progress_percent)), 0) AS avg_progress,
                    COALESCE(SUM(p.health = 'green'), 0) AS green,
                    COALESCE(SUM(p.health = 'yellow'), 0) AS yellow,
                    COALESCE(SUM(p.health = 'red'), 0) AS red,
                    COALESCE(SUM(p.development_mode = 'ai_dev_auto'), 0) AS ai_dev_auto
             FROM workspaces w LEFT JOIN projects p ON p.workspace_id = w.id
             GROUP BY w.id, w.name ORDER BY w.id"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
