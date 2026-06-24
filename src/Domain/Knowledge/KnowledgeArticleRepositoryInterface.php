<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

/** Pattern: Scoped ตรง */
interface KnowledgeArticleRepositoryInterface
{
    public function findById(int $id): ?KnowledgeArticle;

    /** @return array<int, KnowledgeArticle> */
    public function listByWorkspace(?string $category = null, ?int $projectId = null): array;

    public function create(
        string $title,
        ?string $category,
        string $content,
        ?int $projectId,
        int $authoredByUserId,
        int $createdByUserId
    ): KnowledgeArticle;

    public function update(int $id, string $title, ?string $category, string $content): bool;

    public function updateStatus(int $id, string $status): bool;

    /**
     * Full-text search ผ่าน ngram parser -- คืนแค่ status='published' เสมอ
     * (ไม่ค้นหา draft ของคนอื่น)
     * @return array<int, KnowledgeArticle>
     */
    public function search(string $query, ?string $category = null): array;
}
