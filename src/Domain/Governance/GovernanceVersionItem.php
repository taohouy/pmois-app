<?php

declare(strict_types=1);

namespace App\Domain\Governance;

final class GovernanceVersionItem
{
    public function __construct(
        public readonly int $id,
        public readonly int $governanceVersionId,
        public readonly string $itemCode,
        public readonly string $title,
        public readonly ?string $description,
        public readonly int $sequenceOrder,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            governanceVersionId: (int) $row['governance_version_id'],
            itemCode: (string) $row['item_code'],
            title: (string) $row['title'],
            description: $row['description'] !== null ? (string) $row['description'] : null,
            sequenceOrder: (int) $row['sequence_order'],
        );
    }
}
