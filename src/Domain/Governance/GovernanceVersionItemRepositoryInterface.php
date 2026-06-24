<?php

declare(strict_types=1);

namespace App\Domain\Governance;

/**
 * ⚠️ Pattern: Scoped ทางอ้อม 2 ชั้น (item -> version -> record -> workspace)
 * จุดเสี่ยง bug สูงสุดของ Phase 1 ตามที่ระบุไว้ใน Workspace Scoping Test Plan
 */
interface GovernanceVersionItemRepositoryInterface
{
    public function findById(int $id): ?GovernanceVersionItem;

    /** @return array<int, GovernanceVersionItem> */
    public function listByVersion(int $versionId): array;

    public function create(
        int $versionId,
        string $itemCode,
        string $title,
        ?string $description,
        int $sequenceOrder,
        int $createdByUserId
    ): GovernanceVersionItem;
}
