<?php

declare(strict_types=1);

namespace App\Domain\Governance;

use RuntimeException;

/**
 * GovernanceAdoptionService
 *
 * คุม Rollup Rule (Approved -- Phase 1 Planning Package v0.1 หมวด 2.3):
 *   1. มี item ใด non_compliant -> adoption = non_compliant
 *   2. ไม่มี non_compliant แต่มี in_progress -> adoption = in_progress
 *   3. ทุก item compliant/na -> adoption = compliant
 *
 * และกฎ "active adoption เดียวต่อ governance_record ต่อ project" (Decision #2, Governance-centric)
 */
final class GovernanceAdoptionService
{
    public function __construct(
        private readonly GovernanceAdoptionRepositoryInterface $adoptionRepo,
        private readonly GovernanceAdoptionItemRepositoryInterface $adoptionItemRepo,
        private readonly GovernanceVersionItemRepositoryInterface $versionItemRepo
    ) {
    }

    /**
     * Adopt governance version เข้า project -- สร้าง adoption + adoption_items ครบทุกข้อ
     * ในคราวเดียว (ตามที่ design ไว้ใน Phase 1 Planning Package v0.1 หมวด 4 — ลด round-trip
     * และป้องกัน adoption ถูกสร้างแต่ items ไม่ครบ)
     */
    public function adopt(int $projectId, int $governanceVersionId, int $governanceRecordId, int $userId): GovernanceAdoption
    {
        if ($this->adoptionRepo->hasActiveAdoptionForRecord($projectId, $governanceRecordId)) {
            throw new RuntimeException('Project นี้มี active adoption ของ governance record นี้อยู่แล้ว (อนุญาตแค่ 1 active ต่อ record ต่อ project)');
        }

        $adoption = $this->adoptionRepo->create($projectId, $governanceVersionId, $userId);

        $versionItems = $this->versionItemRepo->listByVersion($governanceVersionId);
        foreach ($versionItems as $versionItem) {
            $this->adoptionItemRepo->create($adoption->id, $versionItem->id, $userId);
        }

        return $adoption;
    }

    public function updateItemCompliance(
        int $adoptionItemId,
        string $complianceStatus,
        ?string $evidenceNote,
        int $reviewedByUserId
    ): GovernanceAdoption {
        $item = $this->adoptionItemRepo->findById($adoptionItemId);
        if ($item === null) {
            throw new RuntimeException('ไม่พบ adoption item นี้ใน workspace ปัจจุบัน');
        }

        $this->adoptionItemRepo->updateComplianceStatus($adoptionItemId, $complianceStatus, $evidenceNote, $reviewedByUserId);

        return $this->recalculateStatus($item->governanceAdoptionId);
    }

    public function recalculateStatus(int $adoptionId): GovernanceAdoption
    {
        $statuses = $this->adoptionItemRepo->listComplianceStatuses($adoptionId);

        if (in_array('non_compliant', $statuses, true)) {
            $newStatus = 'non_compliant';
        } elseif (in_array('in_progress', $statuses, true)) {
            $newStatus = 'in_progress';
        } else {
            $newStatus = 'compliant';
        }

        $this->adoptionRepo->updateAdoptionStatus($adoptionId, $newStatus);

        $adoption = $this->adoptionRepo->findById($adoptionId);
        if ($adoption === null) {
            throw new RuntimeException('Recalculate สำเร็จแต่ดึงข้อมูล adoption กลับไม่ได้');
        }

        return $adoption;
    }
}
