<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Project\ProjectAiAssignment;
use App\Domain\Project\ProjectAiAssignmentRepositoryInterface;
use PDO;

final class MySqlProjectAiAssignmentRepository extends BaseRepository implements ProjectAiAssignmentRepositoryInterface
{
    public function findByProjectId(int $projectId, bool $includeRevoked = false): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_ai_assignments WHERE project_id = :project_id
             ' . ($includeRevoked ? '' : 'AND revoked_at IS NULL') . '
             AND {{WORKSPACE_FILTER}} ORDER BY id ASC'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['project_id' => $projectId, 'workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $row): ProjectAiAssignment => ProjectAiAssignment::fromRow($row), $rows);
    }

    public function findById(int $id): ?ProjectAiAssignment
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_ai_assignments WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);
        return ProjectAiAssignment::fromRow($row);
    }

    public function findActiveByProjectAndConsumer(int $projectId, int $aiConsumerId): ?ProjectAiAssignment
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_ai_assignments
             WHERE project_id = :project_id AND ai_consumer_id = :ai_consumer_id AND revoked_at IS NULL
             AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'project_id' => $projectId,
            'ai_consumer_id' => $aiConsumerId,
            'workspace_id' => $this->workspaceId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);
        return ProjectAiAssignment::fromRow($row);
    }

    public function create(int $projectId, int $aiConsumerId, int $roleId, ?string $purpose, int $assignedBy): int
    {
        // INSERT ระบุ workspace_id ตรงจาก parameter (INSERT ไม่ใช้ applyWorkspaceScope ตาม pattern ระบบเดิม)
        $sql = 'INSERT INTO project_ai_assignments (project_id, workspace_id, ai_consumer_id, role_id, purpose, assigned_by)
             VALUES (:project_id, :workspace_id, :ai_consumer_id, :role_id, :purpose, :assigned_by)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'project_id' => $projectId,
            'workspace_id' => $this->workspaceId,
            'ai_consumer_id' => $aiConsumerId,
            'role_id' => $roleId,
            'purpose' => $purpose,
            'assigned_by' => $assignedBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function revoke(int $id, int $revokedBy): bool
    {
        $sql = $this->applyWorkspaceScope(
            'UPDATE project_ai_assignments SET revoked_by = :revoked_by, revoked_at = NOW()
             WHERE id = :id AND revoked_at IS NULL AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'revoked_by' => $revokedBy, 'workspace_id' => $this->workspaceId]);

        return $stmt->rowCount() > 0;
    }
}
