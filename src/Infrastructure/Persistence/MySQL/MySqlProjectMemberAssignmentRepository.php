<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Project\ProjectMemberAssignment;
use App\Domain\Project\ProjectMemberAssignmentRepositoryInterface;
use PDO;

final class MySqlProjectMemberAssignmentRepository extends BaseRepository implements ProjectMemberAssignmentRepositoryInterface
{
    public function findByProjectId(int $projectId, bool $includeRevoked = false): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_member_assignments WHERE project_id = :project_id
             ' . ($includeRevoked ? '' : 'AND revoked_at IS NULL') . '
             AND {{WORKSPACE_FILTER}} ORDER BY id ASC'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['project_id' => $projectId, 'workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $row): ProjectMemberAssignment => ProjectMemberAssignment::fromRow($row), $rows);
    }

    public function findById(int $id): ?ProjectMemberAssignment
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_member_assignments WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);
        return ProjectMemberAssignment::fromRow($row);
    }

    public function findActiveByProjectAndUser(int $projectId, int $userId): ?ProjectMemberAssignment
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM project_member_assignments
             WHERE project_id = :project_id AND user_id = :user_id AND revoked_at IS NULL
             AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'project_id' => $projectId,
            'user_id' => $userId,
            'workspace_id' => $this->workspaceId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);
        return ProjectMemberAssignment::fromRow($row);
    }

    public function create(int $projectId, int $userId, int $roleId, string $assignmentSource, ?string $note, int $assignedBy): int
    {
        // INSERT ระบุ workspace_id ตรงจาก parameter (INSERT ไม่ใช้ applyWorkspaceScope ตาม pattern ระบบเดิม)
        $sql = 'INSERT INTO project_member_assignments (project_id, workspace_id, user_id, role_id, assignment_source, note, assigned_by)
             VALUES (:project_id, :workspace_id, :user_id, :role_id, :assignment_source, :note, :assigned_by)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'project_id' => $projectId,
            'workspace_id' => $this->workspaceId,
            'user_id' => $userId,
            'role_id' => $roleId,
            'assignment_source' => $assignmentSource,
            'note' => $note,
            'assigned_by' => $assignedBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function revoke(int $id, int $revokedBy): bool
    {
        $sql = $this->applyWorkspaceScope(
            'UPDATE project_member_assignments SET revoked_by = :revoked_by, revoked_at = NOW()
             WHERE id = :id AND revoked_at IS NULL AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'revoked_by' => $revokedBy, 'workspace_id' => $this->workspaceId]);

        return $stmt->rowCount() > 0;
    }

    public function insertRowsForBootstrap(array $rows): array
    {
        foreach ($rows as $row) {
            $stmt = $this->db->prepare(
                'INSERT INTO project_member_assignments (project_id, workspace_id, user_id, role_id, assignment_source, assigned_by, assigned_at)
                 VALUES (:project_id, :workspace_id, :user_id, :role_id, :assignment_source, :assigned_by, :assigned_at)'
            );
            $stmt->execute($row);
        }
        return [];
    }
}
