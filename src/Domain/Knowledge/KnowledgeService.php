<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

use App\Domain\Decision\DecisionRegisterRepositoryInterface;

/**
 * KnowledgeService — M6 Knowledge Center
 *
 * Registry types (config — เพิ่มประเภทใหม่แก้ที่เดียว):
 *   business_rule        — Business Rules Registry
 *   known_issue          — Known Issues
 *   risk                 — Risk Register
 *   future_enhancement   — Future Enhancements
 *
 * ADR / Decision Log reuse decision_registers (ADR = category 'architecture')
 * Project Knowledge Base reuse knowledge_articles (+ fulltext search)
 */
final class KnowledgeService
{
    public const ENTRY_TYPES = ['business_rule', 'known_issue', 'risk', 'future_enhancement'];

    /** status ที่อนุญาตต่อประเภท (config) */
    private const STATUSES = [
        'business_rule' => ['active', 'deprecated'],
        'known_issue' => ['open', 'workaround', 'resolved'],
        'risk' => ['open', 'mitigated', 'closed'],
        'future_enhancement' => ['proposed', 'planned', 'in_progress', 'done'],
    ];

    private const SEVERITIES = ['low', 'medium', 'high', 'critical'];
    private const LEVELS = ['low', 'medium', 'high'];

    public function __construct(
        private readonly KnowledgeEntryRepositoryInterface $entryRepository,
        private readonly KnowledgeArticleRepositoryInterface $articleRepository,
        private readonly DecisionRegisterRepositoryInterface $decisionRepository,
        private readonly int $workspaceId,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException VALIDATION_ERROR (ต่อประเภท)
     */
    public function create(array $data, int $createdBy): KnowledgeEntry
    {
        $entryType = (string) ($data['entry_type'] ?? '');
        if (!in_array($entryType, self::ENTRY_TYPES, true)) {
            throw new \InvalidArgumentException('entry_type must be one of: ' . implode(', ', self::ENTRY_TYPES));
        }

        $errors = [];
        if (empty($data['title'])) {
            $errors[] = 'title is required';
        }
        if (empty($data['body'])) {
            $errors[] = 'body is required';
        }

        $status = (string) ($data['status'] ?? self::STATUSES[$entryType][0]);
        if (!in_array($status, self::STATUSES[$entryType], true)) {
            $errors[] = "status for {$entryType} must be one of: " . implode(', ', self::STATUSES[$entryType]);
        }

        if (isset($data['severity']) && $data['severity'] !== null && !in_array($data['severity'], self::SEVERITIES, true)) {
            $errors[] = 'severity must be one of: ' . implode(', ', self::SEVERITIES);
        }
        if ($entryType === 'known_issue' && ($data['severity'] ?? null) === null) {
            $errors[] = 'severity is required for known_issue';
        }
        foreach (['probability', 'impact'] as $levelField) {
            if (isset($data[$levelField]) && $data[$levelField] !== null && !in_array($data[$levelField], self::LEVELS, true)) {
                $errors[] = "{$levelField} must be one of: " . implode(', ', self::LEVELS);
            }
        }
        if ($entryType === 'risk' && (($data['probability'] ?? null) === null || ($data['impact'] ?? null) === null)) {
            $errors[] = 'probability and impact are required for risk';
        }

        if ($errors !== []) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        return $this->entryRepository->create([
            'workspace_id' => $this->workspaceId,
            'project_id' => isset($data['project_id']) && $data['project_id'] !== null ? (int) $data['project_id'] : null,
            'entry_type' => $entryType,
            'code' => $data['code'] ?? null,
            'title' => (string) $data['title'],
            'body' => (string) $data['body'],
            'status' => $status,
            'severity' => $data['severity'] ?? null,
            'probability' => $data['probability'] ?? null,
            'impact' => $data['impact'] ?? null,
            'mitigation' => $data['mitigation'] ?? null,
            'related_revision_id' => isset($data['related_revision_id']) && $data['related_revision_id'] !== null ? (int) $data['related_revision_id'] : null,
            'related_url' => $data['related_url'] ?? null,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * @return array<int, KnowledgeEntry>
     */
    public function listEntries(?string $entryType, ?int $projectId, ?string $status): array
    {
        return $this->entryRepository->findByWorkspace($entryType, $projectId, $status);
    }

    public function getEntry(int $id): ?KnowledgeEntry
    {
        return $this->entryRepository->findById($id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateEntry(int $id, array $data): bool
    {
        return $this->entryRepository->update($id, $data);
    }

    public function deleteEntry(int $id): bool
    {
        return $this->entryRepository->delete($id);
    }

    /**
     * Search API — ค้นหาข้ามประเภท: knowledge entries + knowledge articles (fulltext) + ADR/decisions
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function search(string $keyword): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            throw new \InvalidArgumentException('q is required');
        }

        $entries = array_map(
            static fn (KnowledgeEntry $e): array => ['type' => $e->entryType, 'id' => $e->id, 'code' => $e->code, 'title' => $e->title, 'project_id' => $e->projectId, 'created_at' => $e->createdAt],
            $this->entryRepository->search($keyword)
        );

        $articles = array_map(
            static fn ($a): array => ['type' => 'knowledge_article', 'id' => $a->id, 'code' => null, 'title' => $a->title, 'project_id' => $a->projectId],
            $this->articleRepository->search($keyword)
        );

        $decisions = [];
        foreach ($this->decisionRepository->listByWorkspace() as $d) {
            if (stripos($d->title . ' ' . $d->decisionDescription, $keyword) !== false) {
                $decisions[] = ['type' => 'decision', 'id' => $d->id, 'code' => null, 'title' => $d->title, 'category' => $d->category, 'project_id' => $d->projectId];
            }
        }

        return ['entries' => $entries, 'articles' => $articles, 'decisions' => $decisions];
    }

    /**
     * Project Knowledge Base — entries + articles ของ project (fail-closed ต่าง workspace)
     *
     * @return array<string, mixed>|null
     */
    public function projectKnowledge(int $projectId): array
    {
        $entries = $this->entryRepository->findByWorkspace(null, $projectId, null);

        return [
            'project_id' => $projectId,
            'entries' => array_map(static fn (KnowledgeEntry $e): array => $e->toArray(), $entries),
            'articles' => array_map(static fn ($a): array => [
                'id' => $a->id, 'title' => $a->title, 'category' => $a->category, 'status' => $a->status,
            ], $this->articleRepository->listByWorkspace(null, $projectId)),
        ];
    }

    /**
     * ADR — Architecture Decision Records (decision_registers category='architecture')
     *
     * @return array<int, array<string, mixed>>
     */
    public function architectureDecisionRecords(?int $projectId): array
    {
        $result = [];
        foreach ($this->decisionRepository->listByWorkspace() as $d) {
            if ($d->category !== 'architecture') {
                continue;
            }
            if ($projectId !== null && $d->projectId !== $projectId) {
                continue;
            }
            $result[] = [
                'id' => $d->id,
                'title' => $d->title,
                'decision' => $d->decisionDescription,
                'status' => $d->status,
                'project_id' => $d->projectId,
            ];
        }

        return $result;
    }

    /**
     * Knowledge Timeline — entries เรียงเวลาใหม่→เก่า (ต่อ project หรือทั้ง workspace)
     *
     * @return array<int, array<string, mixed>>
     */
    public function knowledgeTimeline(?int $projectId, int $limit): array
    {
        return $this->entryRepository->timeline($this->workspaceId, $projectId, $limit);
    }
}
