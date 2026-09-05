<?php

declare(strict_types=1);

namespace App\Domain\Registry;

final class GitProvider
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $baseUrl,
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
        return new self(
            id: (int) $row['id'],
            code: (string) $row['code'],
            name: (string) $row['name'],
            baseUrl: $row['base_url'] !== null ? (string) $row['base_url'] : null,
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
            'code' => $this->code,
            'name' => $this->name,
            'base_url' => $this->baseUrl,
            'status' => $this->status,
        ];
    }
}
