<?php

declare(strict_types=1);

namespace App\Domain\Project;

/**
 * ProjectTeamAssignmentService
 *
 * 2-layer model (M0-Design/Revision6/R6-06 §1):
 *  - project_member_assignments = assignment ledger (history, ไม่ลบแถว)
 *  - project_members            = live authorization projection ที่ PermissionResolver อ่าน
 *
 * Invariant: active ledger row ต้องมี project_members row ตรงกันเสมอ (sync ใน service นี้)
 */
final class ProjectTeamAssignmentService
{
    public function __construct(
        private readonly ProjectMemberAssignmentRepositoryInterface $assignmentRepository,
        private readonly ProjectMemberRepositoryInterface $memberRepository,
        private readonly int $workspaceId,
    ) {
    }

    /**
     * @return array<int, ProjectMemberAssignment>
     */
    public function listByProject(int $projectId, bool $includeRevoked = false): array
    {
        return $this->assignmentRepository->findByProjectId($projectId, $includeRevoked);
    }

    /**
     * Assign human member: เขียน ledger + sync projection
     *
     * @throws \DomainException ASSIGNMENT_ALREADY_ACTIVE เมื่อมี active row อยู่แล้ว
     */
    public function assign(int $projectId, int $userId, int $roleId, string $assignmentSource, ?string $note, int $assignedBy): int
    {
        if ($this->assignmentRepository->findActiveByProjectAndUser($projectId, $userId) !== null) {
            throw new \DomainException('ASSIGNMENT_ALREADY_ACTIVE');
        }

        $assignmentId = $this->assignmentRepository->create($projectId, $userId, $roleId, $assignmentSource, $note, $assignedBy);

        // sync authorization projection (addMember = INSERT; ถ้ามีอยู่แล้วให้เป็น noop ตาม unique key)
        $this->memberRepository->addMember($projectId, $userId, $roleId);

        return $assignmentId;
    }

    /**
     * Revoke: mark ledger + ลบ projection (PermissionResolver ไม่เห็นสิทธิ์อีกต่อไป)
     */
    public function revoke(int $assignmentId, int $revokedBy): bool
    {
        $assignment = $this->assignmentRepository->findById($assignmentId);
        if ($assignment === null || !$assignment->isActive()) {
            return false;
        }

        $ok = $this->assignmentRepository->revoke($assignmentId, $revokedBy);
        if ($ok) {
            $this->memberRepository->removeMember($assignment->projectId, $assignment->userId);
        }

        return $ok;
    }
}
