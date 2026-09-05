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
        public readonly ?string $audience,
        public readonly ?string $policyType,
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
            audience: isset($row['audience']) && $row['audience'] !== null ? (string) $row['audience'] : null,
            policyType: isset($row['policy_type']) && $row['policy_type'] !== null ? (string) $row['policy_type'] : null,
            description: $row['description'] !== null ? (string) $row['description'] : null,
            ownerUserId: (int) $row['owner_user_id'],
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
            'code' => $this->code,
            'title' => $this->title,
            'category' => $this->category,
            'audience' => $this->audience,
            'policy_type' => $this->policyType,
            'description' => $this->description,
            'status' => $this->status,
        ];
    }
}
