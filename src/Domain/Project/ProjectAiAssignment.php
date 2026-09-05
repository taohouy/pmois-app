<?php

declare(strict_types=1);

namespace App\Domain\Project;

final class ProjectAiAssignment
{
    public function __construct(
        public readonly int $id,
        public readonly int $projectId,
        public readonly int $workspaceId,
        public readonly int $aiConsumerId,
        public readonly int $roleId,
        public readonly ?string $purpose,
        public readonly int $assignedBy,
        public readonly string $assignedAt,
        public readonly ?int $revokedBy,
        public readonly ?string $revokedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            projectId: (int) $row['project_id'],
            workspaceId: (int) $row['workspace_id'],
            aiConsumerId: (int) $row['ai_consumer_id'],
            roleId: (int) $row['role_id'],
            purpose: $row['purpose'] !== null ? (string) $row['purpose'] : null,
            assignedBy: (int) $row['assigned_by'],
            assignedAt: (string) $row['assigned_at'],
            revokedBy: $row['revoked_by'] !== null ? (int) $row['revoked_by'] : null,
            revokedAt: $row['revoked_at'] !== null ? (string) $row['revoked_at'] : null,
        );
    }

    public function isActive(): bool
    {
        return $this->revokedAt === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->projectId,
            'ai_consumer_id' => $this->aiConsumerId,
            'role_id' => $this->roleId,
            'purpose' => $this->purpose,
            'assigned_by' => $this->assignedBy,
            'assigned_at' => $this->assignedAt,
            'revoked_by' => $this->revokedBy,
            'revoked_at' => $this->revokedAt,
            'is_active' => $this->isActive(),
        ];
    }
}
