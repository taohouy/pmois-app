<?php

declare(strict_types=1);

namespace App\Domain\Decision;

final class DecisionRegister
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly ?int $projectId,
        public readonly ?int $relatedGovernanceRecordId,
        public readonly string $category,
        public readonly string $title,
        public readonly string $decisionDescription,
        public readonly string $status,
        public readonly int $decidedBy,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            workspaceId: (int) $row['workspace_id'],
            projectId: $row['project_id'] !== null ? (int) $row['project_id'] : null,
            relatedGovernanceRecordId: $row['related_governance_record_id'] !== null ? (int) $row['related_governance_record_id'] : null,
            category: (string) $row['category'],
            title: (string) $row['title'],
            decisionDescription: (string) $row['decision_description'],
            status: (string) $row['status'],
            decidedBy: (int) $row['decided_by'],
        );
    }
}
