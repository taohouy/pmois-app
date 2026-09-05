<?php

declare(strict_types=1);

namespace App\Domain\Project;

interface ProjectAiAssignmentRepositoryInterface
{
    /**
     * @param bool $includeRevoked true = คืนทุกแถว (full history), false = เฉพาะ active
     * @return array<int, ProjectAiAssignment>
     */
    public function findByProjectId(int $projectId, bool $includeRevoked = false): array;

    public function findById(int $id): ?ProjectAiAssignment;

    public function findActiveByProjectAndConsumer(int $projectId, int $aiConsumerId): ?ProjectAiAssignment;

    public function create(int $projectId, int $aiConsumerId, int $roleId, ?string $purpose, int $assignedBy): int;

    public function revoke(int $id, int $revokedBy): bool;
}
