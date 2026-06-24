<?php

declare(strict_types=1);

namespace App\Domain\Governance;

/**
 * Pattern: Scoped ทางอ้อม 1 ชั้น (version -> record -> workspace)
 */
interface GovernanceVersionRepositoryInterface
{
    public function findById(int $id): ?GovernanceVersion;

    /** @return array<int, GovernanceVersion> */
    public function listByRecord(int $recordId): array;

    /**
     * หา version ที่ status='published' ของ record เดียวกัน (ใช้โดย auto-supersede)
     * คืนได้สูงสุด 1 แถวตามกฎ "active published เดียวต่อ record" (ถ้ามากกว่านั้นคือ data bug)
     */
    public function findPublishedByRecord(int $recordId): ?GovernanceVersion;

    public function create(int $recordId, string $versionLabel, string $content, int $createdByUserId): GovernanceVersion;

    public function updateContent(int $id, string $content): bool;

    public function markPublished(int $id, int $publishedByUserId): bool;

    public function markSuperseded(int $id): bool;
}
