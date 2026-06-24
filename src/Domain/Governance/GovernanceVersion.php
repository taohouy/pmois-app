<?php

declare(strict_types=1);

namespace App\Domain\Governance;

final class GovernanceVersion
{
    public function __construct(
        public readonly int $id,
        public readonly int $governanceRecordId,
        public readonly string $versionLabel,
        public readonly string $content,
        public readonly string $status,
        public readonly ?string $effectiveDate,
        public readonly ?int $publishedBy,
        public readonly ?string $publishedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            governanceRecordId: (int) $row['governance_record_id'],
            versionLabel: (string) $row['version_label'],
            content: (string) $row['content'],
            status: (string) $row['status'],
            effectiveDate: $row['effective_date'] !== null ? (string) $row['effective_date'] : null,
            publishedBy: $row['published_by'] !== null ? (int) $row['published_by'] : null,
            publishedAt: $row['published_at'] !== null ? (string) $row['published_at'] : null,
        );
    }
}
