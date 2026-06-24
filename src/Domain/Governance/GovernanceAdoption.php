<?php

declare(strict_types=1);

namespace App\Domain\Governance;

final class GovernanceAdoption
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly int $projectId,
        public readonly int $governanceVersionId,
        public readonly string $adoptionStatus,
        public readonly string $status,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            workspaceId: (int) $row['workspace_id'],
            projectId: (int) $row['project_id'],
            governanceVersionId: (int) $row['governance_version_id'],
            adoptionStatus: (string) $row['adoption_status'],
            status: (string) $row['status'],
        );
    }
}
