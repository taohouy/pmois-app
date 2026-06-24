<?php

declare(strict_types=1);

namespace App\Domain\Rfc;

final class Rfc
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly ?int $projectId,
        public readonly ?int $relatedGovernanceRecordId,
        public readonly string $code,
        public readonly string $title,
        public readonly string $description,
        public readonly string $status,
        public readonly int $createdBy,
        public readonly ?int $resultingDecisionId,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            workspaceId: (int) $row['workspace_id'],
            projectId: $row['project_id'] !== null ? (int) $row['project_id'] : null,
            relatedGovernanceRecordId: $row['related_governance_record_id'] !== null ? (int) $row['related_governance_record_id'] : null,
            code: (string) $row['code'],
            title: (string) $row['title'],
            description: (string) $row['description'],
            status: (string) $row['status'],
            createdBy: (int) $row['created_by'],
            resultingDecisionId: $row['resulting_decision_id'] !== null ? (int) $row['resulting_decision_id'] : null,
        );
    }
}
