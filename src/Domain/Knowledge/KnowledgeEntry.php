<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

final class KnowledgeEntry
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly ?int $projectId,
        public readonly string $entryType,
        public readonly ?string $code,
        public readonly string $title,
        public readonly string $body,
        public readonly string $status,
        public readonly ?string $severity,
        public readonly ?string $probability,
        public readonly ?string $impact,
        public readonly ?string $mitigation,
        public readonly ?int $relatedRevisionId,
        public readonly ?string $relatedUrl,
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
            workspaceId: (int) $row['workspace_id'],
            projectId: $row['project_id'] !== null ? (int) $row['project_id'] : null,
            entryType: (string) $row['entry_type'],
            code: $row['code'] !== null ? (string) $row['code'] : null,
            title: (string) $row['title'],
            body: (string) $row['body'],
            status: (string) $row['status'],
            severity: $row['severity'] !== null ? (string) $row['severity'] : null,
            probability: $row['probability'] !== null ? (string) $row['probability'] : null,
            impact: $row['impact'] !== null ? (string) $row['impact'] : null,
            mitigation: $row['mitigation'] !== null ? (string) $row['mitigation'] : null,
            relatedRevisionId: $row['related_revision_id'] !== null ? (int) $row['related_revision_id'] : null,
            relatedUrl: $row['related_url'] !== null ? (string) $row['related_url'] : null,
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
            'project_id' => $this->projectId,
            'entry_type' => $this->entryType,
            'code' => $this->code,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status,
            'severity' => $this->severity,
            'probability' => $this->probability,
            'impact' => $this->impact,
            'mitigation' => $this->mitigation,
            'related_revision_id' => $this->relatedRevisionId,
            'related_url' => $this->relatedUrl,
            'created_at' => $this->createdAt,
        ];
    }
}
