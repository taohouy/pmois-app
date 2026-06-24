<?php

declare(strict_types=1);

namespace App\Domain\Decision;

use RuntimeException;

/**
 * DecisionRegisterService
 *
 * คุมกฎ Decision Category (CTO Decision -- Phase 1 Specification Package v0.1):
 *   - สร้างตรง (ไม่ผ่าน RFC): category เป็น "required" ถ้าไม่ส่งมา -> VALIDATION_ERROR
 *     ห้าม auto-default เป็น 'other' เด็ดขาด
 *   - สร้างจาก RFC: ใช้ default 'governance' ได้ถ้าไม่ระบุ (อยู่ใน RfcService ไม่ใช่ที่นี่)
 */
final class DecisionRegisterService
{
    public const VALID_CATEGORIES = [
        'technical', 'architecture', 'process', 'governance',
        'vendor', 'budget', 'scope', 'organizational', 'other',
    ];

    public function __construct(private readonly DecisionRegisterRepositoryInterface $decisionRepo)
    {
    }

    /**
     * สร้าง Decision โดยตรง (ไม่ผ่าน RFC) -- category เป็น required เสมอ
     *
     * @throws RuntimeException ถ้า category ไม่ถูกส่งมาหรือไม่ valid
     */
    public function createDirect(
        ?string $category,
        string $title,
        ?string $context,
        string $decisionDescription,
        string $decisionDate,
        ?int $projectId,
        ?int $relatedGovernanceRecordId,
        int $decidedByUserId,
        int $createdByUserId
    ): DecisionRegister {
        if ($category === null || $category === '') {
            throw new RuntimeException(
                'VALIDATION_ERROR: category เป็น field บังคับสำหรับการสร้าง Decision โดยตรง '
                . '(ไม่อนุญาตให้ default เป็น "other" ตาม CTO Decision)'
            );
        }

        if (!in_array($category, self::VALID_CATEGORIES, true)) {
            throw new RuntimeException(
                'VALIDATION_ERROR: category ต้องเป็นหนึ่งใน: ' . implode(', ', self::VALID_CATEGORIES)
            );
        }

        return $this->decisionRepo->create(
            category: $category,
            title: $title,
            context: $context,
            decisionDescription: $decisionDescription,
            decisionDate: $decisionDate,
            projectId: $projectId,
            relatedGovernanceRecordId: $relatedGovernanceRecordId,
            status: 'proposed', // สร้างตรงเริ่มที่ proposed เสมอ (ต่างจากที่มาจาก RFC)
            decidedByUserId: $decidedByUserId,
            createdByUserId: $createdByUserId
        );
    }

    public function approve(int $decisionId): DecisionRegister
    {
        return $this->changeStatus($decisionId, 'proposed', 'approved');
    }

    public function reject(int $decisionId): DecisionRegister
    {
        return $this->changeStatus($decisionId, 'proposed', 'rejected');
    }

    private function changeStatus(int $decisionId, string $expectedCurrentStatus, string $newStatus): DecisionRegister
    {
        $decision = $this->decisionRepo->findById($decisionId);
        if ($decision === null) {
            throw new RuntimeException('ไม่พบ Decision นี้ใน workspace ปัจจุบัน');
        }

        if ($decision->status !== $expectedCurrentStatus) {
            throw new RuntimeException(
                "เปลี่ยนสถานะนี้ได้เฉพาะจาก '{$expectedCurrentStatus}' เท่านั้น (ปัจจุบัน: {$decision->status})"
            );
        }

        $this->decisionRepo->updateStatus($decisionId, $newStatus);

        $updated = $this->decisionRepo->findById($decisionId);
        if ($updated === null) {
            throw new RuntimeException('เปลี่ยนสถานะสำเร็จแต่ดึงข้อมูลกลับไม่ได้');
        }

        return $updated;
    }
}
