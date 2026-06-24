<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Project\ProjectStatusUpdate;
use App\Domain\Project\ProjectStatusUpdateRepositoryInterface;
use RuntimeException;

final class MySqlProjectStatusUpdateRepository extends BaseRepository implements ProjectStatusUpdateRepositoryInterface
{
    public function create(
        int $projectId, string $reportDate, string $overallStatus, string $summary,
        ?string $keyAchievements, ?string $keyIssues, ?string $nextSteps,
        int $submittedByUserId, ?string $idempotencyKey = null
    ): ProjectStatusUpdate {
        if ($this->workspaceId === null) {
            throw new RuntimeException('ต้องมี workspace context ก่อนสร้าง project status update');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO project_status_updates
                (project_id, workspace_id, report_date, overall_status, summary,
                 key_achievements, key_issues, next_steps, submitted_by, idempotency_key)
             VALUES
                (:project_id, :workspace_id, :report_date, :status, :summary,
                 :achievements, :issues, :next_steps, :submitted_by, :idempotency_key)'
        );
        $stmt->execute([
            'project_id' => $projectId, 'workspace_id' => $this->workspaceId, 'report_date' => $reportDate,
            'status' => $overallStatus, 'summary' => $summary, 'achievements' => $keyAchievements,
            'issues' => $keyIssues, 'next_steps' => $nextSteps, 'submitted_by' => $submittedByUserId,
            'idempotency_key' => $idempotencyKey,
        ]);

        $stmt2 = $this->db->prepare('SELECT * FROM project_status_updates WHERE id = :id');
        $stmt2->execute(['id' => $this->db->lastInsertId()]);
        $row = $stmt2->fetch();

        if ($row === false) {
            throw new RuntimeException('สร้าง status update สำเร็จแต่ดึงข้อมูลกลับไม่ได้');
        }

        return ProjectStatusUpdate::fromRow($row);
    }

    public function findByIdempotencyKey(string $key): ?ProjectStatusUpdate
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_status_updates WHERE idempotency_key = :key AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['key' => $key, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);
        return ProjectStatusUpdate::fromRow($row);
    }

    public function findLatestByProject(int $projectId): ?ProjectStatusUpdate
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_status_updates
             WHERE project_id = :project_id AND {{WORKSPACE_FILTER}}
             ORDER BY report_date DESC, id DESC
             LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['project_id' => $projectId, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);
        return ProjectStatusUpdate::fromRow($row);
    }

    public function listByProject(int $projectId): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_status_updates
             WHERE project_id = :project_id AND {{WORKSPACE_FILTER}}
             ORDER BY report_date DESC, id DESC'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['project_id' => $projectId, 'workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll();

        $this->assertWorkspaceMatchAll($rows);
        return array_map(static fn (array $r): ProjectStatusUpdate => ProjectStatusUpdate::fromRow($r), $rows);
    }

    public function latestPerProject(): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT psu.* FROM project_status_updates psu
             INNER JOIN (
                 SELECT project_id, MAX(report_date) AS max_date
                 FROM project_status_updates
                 WHERE workspace_id = :workspace_id
                 GROUP BY project_id
             ) latest ON latest.project_id = psu.project_id AND latest.max_date = psu.report_date
             WHERE {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll();
        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $r): ProjectStatusUpdate => ProjectStatusUpdate::fromRow($r), $rows);
    }
}
