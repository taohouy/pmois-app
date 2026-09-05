<?php

declare(strict_types=1);

namespace App\Domain\Project;

interface ProjectMemberAssignmentRepositoryInterface
{
    /**
     * @param bool $includeRevoked true = full history, false = active only
     * @return array<int, ProjectMemberAssignment>
     */
    public function findByProjectId(int $projectId, bool $includeRevoked = false): array;

    public function findById(int $id): ?ProjectMemberAssignment;

    public function findActiveByProjectAndUser(int $projectId, int $userId): ?ProjectMemberAssignment;

    public function create(int $projectId, int $userId, int $roleId, string $assignmentSource, ?string $note, int $assignedBy): int;

    public function revoke(int $id, int $revokedBy): bool;

    /**
     * @return array<int, ProjectMemberAssignment>
     */
    public function insertRowsForBootstrap(array $rows): array;
}
