<?php

declare(strict_types=1);

namespace App\Domain\Ai;

final class AiConsumer
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly ?int $providerId,
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
            providerId: isset($row['provider_id']) && $row['provider_id'] !== null ? (int) $row['provider_id'] : null,
            code: (string) $row['code'],
            name: (string) $row['name'],
            status: (string) $row['status'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspaceId,
            'provider_id' => $this->providerId,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
        ];
    }
}
