<?php

declare(strict_types=1);

namespace App\Domain\Registry;

final class ProjectTemplate
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $isDefault,
        /** @var array<string, mixed> */
        public readonly array $payload,
        public readonly string $status,
        public readonly int $createdBy,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $payload = $row['payload'];
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($payload)) {
            $payload = [];
        }

        return new self(
            id: (int) $row['id'],
            workspaceId: (int) $row['workspace_id'],
            code: (string) $row['code'],
            name: (string) $row['name'],
            description: $row['description'] !== null ? (string) $row['description'] : null,
            isDefault: (bool) $row['is_default'],
            payload: $payload,
            status: (string) $row['status'],
            createdBy: (int) $row['created_by'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
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
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_default' => $this->isDefault,
            'payload' => $this->payload,
            'status' => $this->status,
        ];
    }
}
