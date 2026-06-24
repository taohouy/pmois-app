<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

final class KnowledgeLink
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly string $entityType,
        public readonly int $entityId,
        public readonly string $linkedType,
        public readonly ?int $linkedId,
        public readonly ?string $externalUrl,
        public readonly ?string $linkLabel,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            workspaceId: (int) $row['workspace_id'],
            entityType: (string) $row['entity_type'],
            entityId: (int) $row['entity_id'],
            linkedType: (string) $row['linked_type'],
            linkedId: $row['linked_id'] !== null ? (int) $row['linked_id'] : null,
            externalUrl: $row['external_url'] !== null ? (string) $row['external_url'] : null,
            linkLabel: $row['link_label'] !== null ? (string) $row['link_label'] : null,
        );
    }
}
