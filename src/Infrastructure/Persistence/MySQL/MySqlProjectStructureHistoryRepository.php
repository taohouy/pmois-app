<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Project\ProjectStructureHistory;
use App\Domain\Project\ProjectStructureHistoryRepositoryInterface;
use PDO;

final class MySqlProjectStructureHistoryRepository implements ProjectStructureHistoryRepositoryInterface
{
    public function __construct(private readonly PDO $db, private readonly int $workspaceId)
    {
    }

    public function findByProjectId(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM project_structure_history WHERE project_id = :project_id ORDER BY created_at DESC'
        );
        $stmt->execute(['project_id' => $projectId]);

        $history = [];
        foreach ($stmt->fetchAll() as $row) {
            $history[] = ProjectStructureHistory::fromRow($row);
        }
        return $history;
    }

    public function create(ProjectStructureHistory $history): int
    {
        $sql = <<<SQL
            INSERT INTO project_structure_history (
                project_id, change_type, from_workspace_id, to_workspace_id,
                from_parent_project_id, to_parent_project_id, reason, changed_by
            ) VALUES (
                :project_id, :change_type, :from_workspace_id, :to_workspace_id,
                :from_parent_project_id, :to_parent_project_id, :reason, :changed_by
            )
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'project_id' => $history->projectId,
            'change_type' => $history->changeType,
            'from_workspace_id' => $history->fromWorkspaceId,
            'to_workspace_id' => $history->toWorkspaceId,
            'from_parent_project_id' => $history->fromParentProjectId,
            'to_parent_project_id' => $history->toParentProjectId,
            'reason' => $history->reason,
            'changed_by' => $history->changedBy,
        ]);

        return (int) $this->db->lastInsertId();
    }
}