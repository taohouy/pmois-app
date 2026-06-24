<?php

declare(strict_types=1);

namespace App\Domain\Governance;

final class GovernanceAdoptionItem
{
    public function __construct(
        public readonly int $id,
        public readonly int $governanceAdoptionId,
        public readonly int $governanceVersionItemId,
        public readonly string $complianceStatus,
        public readonly ?string $evidenceNote,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            governanceAdoptionId: (int) $row['governance_adoption_id'],
            governanceVersionItemId: (int) $row['governance_version_item_id'],
            complianceStatus: (string) $row['compliance_status'],
            evidenceNote: $row['evidence_note'] !== null ? (string) $row['evidence_note'] : null,
        );
    }
}
