<?php

declare(strict_types=1);

namespace App\Domain\Governance;

final class GovernanceRecord
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly string $code,
        public readonly string $title,
        public readonly string $category,
        public readonly ?string $description,
        public readonly int $ownerUserId,
        public readonly string $status,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            workspaceId: (int) $row['workspace_id'],
            code: (string) $row['code'],
            title: (string) $row['title'],
            category: (string) $row['category'],
            description: $row['description'] !== null ? (string) $row['description'] : null,
            ownerUserId: (int) $row['owner_user_id'],
            status: (string) $row['status'],
        );
    }
}
