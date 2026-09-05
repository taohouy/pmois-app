<?php

declare(strict_types=1);

namespace App\Domain\Project;

/**
 * AiAssignmentService
 *
 * AI Assignment อ้างอิง ai_consumers (Registry) เท่านั้น — ห้าม hardcode ชื่อ AI
 * History: แถวไม่เคยถูกลบ — revoked_at IS NULL = active, revoked_at IS NOT NULL = history
 */
final class AiAssignmentService
{
    public function __construct(
        private readonly ProjectAiAssignmentRepositoryInterface $assignmentRepository,
        private readonly int $workspaceId,
    ) {
    }

    /**
     * @return array<int, ProjectAiAssignment>
     */
    public function listByProject(int $projectId, bool $includeRevoked = false): array
    {
        return $this->assignmentRepository->findByProjectId($projectId, $includeRevoked);
    }

    public function assign(int $projectId, int $aiConsumerId, int $roleId, ?string $purpose, int $assignedBy): int
    {
        if ($this->assignmentRepository->findActiveByProjectAndConsumer($projectId, $aiConsumerId) !== null) {
            throw new \DomainException('ASSIGNMENT_ALREADY_ACTIVE');
        }

        return $this->assignmentRepository->create($projectId, $aiConsumerId, $roleId, $purpose, $assignedBy);
    }

    public function revoke(int $assignmentId, int $revokedBy): bool
    {
        $assignment = $this->assignmentRepository->findById($assignmentId);
        if ($assignment === null || !$assignment->isActive()) {
            return false;
        }

        return $this->assignmentRepository->revoke($assignmentId, $revokedBy);
    }
}
