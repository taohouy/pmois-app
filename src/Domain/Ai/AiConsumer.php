<?php

declare(strict_types=1);

namespace App\Domain\Ai;

final class AiConsumer
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly string $code,
        public readonly string $name,
        public readonly string $status,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            workspaceId: (int) $row['workspace_id'],
            code: (string) $row['code'],
            name: (string) $row['name'],
            status: (string) $row['status'],
        );
    }
}
