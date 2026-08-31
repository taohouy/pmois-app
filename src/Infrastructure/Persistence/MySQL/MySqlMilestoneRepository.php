<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Project\Milestone;
use App\Domain\Project\MilestoneRepositoryInterface;
use PDO;

final class MySqlMilestoneRepository implements MilestoneRepositoryInterface
{
    public function __construct(private readonly PDO $db, private readonly int $workspaceId)
    {
    }

    public function findById(int $id): ?Milestone
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM milestones WHERE id = :id AND workspace_id = :workspace_id LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch();

        return $row !== false ? Milestone::fromRow($row) : null;
    }

    public function findByProjectId(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM milestones WHERE project_id = :project_id AND workspace_id = :workspace_id ORDER BY id ASC'
        );
        $stmt->execute(['project_id' => $projectId, 'workspace_id' => $this->workspaceId]);

        $milestones = [];
        foreach ($stmt->fetchAll() as $row) {
            $milestones[] = Milestone::fromRow($row);
        }
        return $milestones;
    }

    public function findByProjectIdAndCode(int $projectId, string $code): ?Milestone
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM milestones WHERE project_id = :project_id AND workspace_id = :workspace_id AND code = :code LIMIT 1'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'workspace_id' => $this->workspaceId,
            'code' => $code,
        ]);
        $row = $stmt->fetch();

        return $row !== false ? Milestone::fromRow($row) : null;
    }

    public function create(Milestone $milestone): int
    {
        $sql = <<<SQL
            INSERT INTO milestones (
                project_id, workspace_id, code, title, status, planned_date, created_by
            ) VALUES (
                :project_id, :workspace_id, :code, :title, :status, :planned_date, :created_by
            )
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'project_id' => $milestone->projectId,
            'workspace_id' => $milestone->workspaceId,
            'code' => $milestone->code,
            'title' => $milestone->title,
            'status' => $milestone->status,
            'planned_date' => $milestone->plannedDate,
            'created_by' => $milestone->createdBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(Milestone $milestone): bool
    {
        $sql = <<<SQL
            UPDATE milestones
            SET title = :title, status = :status, planned_date = :planned_date,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND workspace_id = :workspace_id
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $milestone->id,
            'workspace_id' => $milestone->workspaceId,
            'title' => $milestone->title,
            'status' => $milestone->status,
            'planned_date' => $milestone->plannedDate,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function close(int $id, int $closedBy): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE milestones SET status = "closed", closed_by = :closed_by, closed_at = NOW() WHERE id = :id AND workspace_id = :workspace_id'
        );
        $stmt->execute([
            'id' => $id,
            'workspace_id' => $this->workspaceId,
            'closed_by' => $closedBy,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function open(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE milestones SET status = "open", closed_by = NULL, closed_at = NULL WHERE id = :id AND workspace_id = :workspace_id'
        );
        $stmt->execute([
            'id' => $id,
            'workspace_id' => $this->workspaceId,
        ]);

        return $stmt->rowCount() > 0;
    }
}