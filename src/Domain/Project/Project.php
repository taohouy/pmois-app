<?php

declare(strict_types=1);

namespace App\Domain\Project;

final class Project
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $status,
        public readonly int $ownerUserId,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            workspaceId: (int) $row['workspace_id'],
            code: (string) $row['code'],
            name: (string) $row['name'],
            description: $row['description'] !== null ? (string) $row['description'] : null,
            status: (string) $row['status'],
            ownerUserId: (int) $row['owner_user_id'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
