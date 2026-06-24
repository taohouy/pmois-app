<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Project\ProjectMemberRepositoryInterface;

/**
 * Pattern: Scoped ทางอ้อมผ่าน join — project_members ไม่มี column workspace_id ของตัวเอง
 * (มีแค่ project_id) จึงต้อง JOIN ผ่าน projects.workspace_id ก่อน filter เสมอ
 *
 * ⚠️ จุดนี้คือจุดที่ developer ใหม่เสี่ยงพลาดที่สุด (ระบุไว้ใน Foundation Module Design หมวด 4.3)
 * ห้าม query project_members ตรงๆ โดยไม่ join ผ่าน projects ก่อน ไม่ว่ากรณีใด
 */
final class MySqlProjectMemberRepository extends BaseRepository implements ProjectMemberRepositoryInterface
{
    public function findRoleIdForUser(int $projectId, int $userId): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT pm.role_id, p.workspace_id
             FROM project_members pm
             INNER JOIN projects p ON p.id = pm.project_id
             WHERE pm.project_id = :project_id
               AND pm.user_id = :user_id
               AND p.workspace_id = :workspace_id
             LIMIT 1'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'user_id' => $userId,
            'workspace_id' => $this->workspaceId,
        ]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        // defense in depth เหมือน assertWorkspaceMatch แต่เขียนตรงเพราะ query นี้คืนค่าเดียว ไม่ใช่ entity เต็ม
        if ((int) $row['workspace_id'] !== $this->workspaceId) {
            throw new \RuntimeException('Workspace scope mismatch ใน ProjectMemberRepository');
        }

        return (int) $row['role_id'];
    }

    public function addMember(int $projectId, int $userId, int $roleId): void
    {
        // ตรวจก่อนว่า project นี้อยู่ใน workspace context จริง ป้องกันการ add member
        // เข้า project ของ workspace อื่นโดยไม่ตั้งใจ (เช่น ส่ง project_id ของ workspace อื่นมาเดา)
        $this->assertProjectBelongsToCurrentWorkspace($projectId);

        $stmt = $this->db->prepare(
            'INSERT INTO project_members (project_id, user_id, role_id)
             VALUES (:project_id, :user_id, :role_id)
             ON DUPLICATE KEY UPDATE role_id = :role_id_update'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'user_id' => $userId,
            'role_id' => $roleId,
            'role_id_update' => $roleId,
        ]);
    }

    public function removeMember(int $projectId, int $userId): bool
    {
        $this->assertProjectBelongsToCurrentWorkspace($projectId);

        $stmt = $this->db->prepare(
            'DELETE FROM project_members WHERE project_id = :project_id AND user_id = :user_id'
        );
        return $stmt->execute(['project_id' => $projectId, 'user_id' => $userId]);
    }

    public function listMembers(int $projectId): array
    {
        $this->assertProjectBelongsToCurrentWorkspace($projectId);

        $stmt = $this->db->prepare(
            'SELECT user_id, role_id FROM project_members WHERE project_id = :project_id'
        );
        $stmt->execute(['project_id' => $projectId]);

        return $stmt->fetchAll();
    }

    private function assertProjectBelongsToCurrentWorkspace(int $projectId): void
    {
        $stmt = $this->db->prepare('SELECT workspace_id FROM projects WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $projectId]);
        $row = $stmt->fetch();

        if ($row === false || (int) $row['workspace_id'] !== $this->workspaceId) {
            throw new \RuntimeException(
                "Project {$projectId} ไม่อยู่ใน workspace context ปัจจุบัน — ปฏิเสธ action นี้"
            );
        }
    }
}
