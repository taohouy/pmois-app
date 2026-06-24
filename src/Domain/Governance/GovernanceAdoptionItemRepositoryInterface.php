<?php

declare(strict_types=1);

namespace App\Domain\Governance;

/**
 * Pattern: Scoped ทางอ้อม 1 ชั้น (item -> adoption -> workspace)
 */
interface GovernanceAdoptionItemRepositoryInterface
{
    public function findById(int $id): ?GovernanceAdoptionItem;

    /** @return array<int, GovernanceAdoptionItem> */
    public function listByAdoption(int $adoptionId): array;

    public function create(int $adoptionId, int $versionItemId, int $createdByUserId): GovernanceAdoptionItem;

    public function updateComplianceStatus(int $id, string $complianceStatus, ?string $evidenceNote, int $reviewedByUserId): bool;

    /** @return array<int, string> รายการ compliance_status ทั้งหมดของ adoption นี้ -- ใช้คำนวณ rollup */
    public function listComplianceStatuses(int $adoptionId): array;
}
