<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

final class KnowledgeArticle
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly ?int $projectId,
        public readonly string $title,
        public readonly ?string $category,
        public readonly string $content,
        public readonly string $status,
        public readonly int $authoredBy,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            workspaceId: (int) $row['workspace_id'],
            projectId: $row['project_id'] !== null ? (int) $row['project_id'] : null,
            title: (string) $row['title'],
            category: $row['category'] !== null ? (string) $row['category'] : null,
            content: (string) $row['content'],
            status: (string) $row['status'],
            authoredBy: (int) $row['authored_by'],
        );
    }
}
