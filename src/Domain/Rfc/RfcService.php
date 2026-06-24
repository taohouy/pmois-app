<?php

declare(strict_types=1);

namespace App\Domain\Rfc;

use App\Domain\Decision\DecisionRegisterRepositoryInterface;
use RuntimeException;

/**
 * RfcService
 *
 * คุม State Machine: draft -> under_review -> approved/rejected -> converted_to_decision
 * และ Segregation of Duties (Approved -- ผู้ review ห้ามเป็นคนสร้าง RFC เอง)
 *
 * หมายเหตุสำคัญ: Segregation of Duties check อยู่ "ที่นี่" ไม่ใช่ใน PermissionResolver
 * ทั่วไป เพราะเป็นกฎเฉพาะของ RFC ไม่ใช่กฎสิทธิ์ทั่วไป (ตามที่ตกลงไว้ตั้งแต่ Phase 0 Planning)
 */
final class RfcService
{
    private const VALID_CATEGORY_DEFAULT_ON_CONVERT = 'governance'; // CTO Decision

    public function __construct(
        private readonly RfcRepositoryInterface $rfcRepo,
        private readonly DecisionRegisterRepositoryInterface $decisionRepo
    ) {
    }

    public function submit(int $rfcId): Rfc
    {
        $rfc = $this->mustFind($rfcId);

        if ($rfc->status !== 'draft') {
            throw new RuntimeException("Submit ได้เฉพาะ RFC ที่ status='draft' เท่านั้น (ปัจจุบัน: {$rfc->status})");
        }

        $this->rfcRepo->markSubmitted($rfcId);

        return $this->mustFind($rfcId);
    }

    public function review(int $rfcId, int $reviewerId, string $decision, ?string $reviewNote): Rfc
    {
        $rfc = $this->mustFind($rfcId);

        if ($rfc->status !== 'under_review') {
            throw new RuntimeException("Review ได้เฉพาะ RFC ที่ status='under_review' เท่านั้น (ปัจจุบัน: {$rfc->status})");
        }

        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new RuntimeException("decision ต้องเป็น 'approved' หรือ 'rejected' เท่านั้น");
        }

        // ❗ Segregation of Duties (Approved) -- ผู้สร้าง RFC ห้ามเป็นผู้ review ของตัวเอง
        if ($rfc->createdBy === $reviewerId) {
            throw new RuntimeException('FORBIDDEN: ผู้สร้าง RFC ไม่สามารถเป็นผู้ review RFC ของตัวเองได้ (Segregation of Duties)');
        }

        $this->rfcRepo->markReviewed($rfcId, $reviewerId, $decision, $reviewNote);

        return $this->mustFind($rfcId);
    }

    /**
     * @param string|null $category ถ้าไม่ระบุ ใช้ default 'governance' ตาม CTO Decision
     */
    public function convertToDecision(int $rfcId, int $convertedByUserId, ?string $category = null): Rfc
    {
        $rfc = $this->mustFind($rfcId);

        if ($rfc->status !== 'approved') {
            throw new RuntimeException("Convert to Decision ได้เฉพาะ RFC ที่ status='approved' เท่านั้น (ปัจจุบัน: {$rfc->status})");
        }

        if ($rfc->resultingDecisionId !== null) {
            throw new RuntimeException('RFC นี้ถูก convert เป็น Decision ไปแล้ว (resulting_decision_id ไม่ใช่ NULL)');
        }

        $decision = $this->decisionRepo->create(
            category: $category ?? self::VALID_CATEGORY_DEFAULT_ON_CONVERT,
            title: $rfc->title,
            context: $rfc->description,
            decisionDescription: 'แปลงจาก RFC: ' . $rfc->code,
            decisionDate: date('Y-m-d'),
            projectId: $rfc->projectId,
            relatedGovernanceRecordId: $rfc->relatedGovernanceRecordId,
            status: 'approved', // ✅ เริ่มที่ approved ตรง ไม่ผ่าน proposed (CTO Decision)
            decidedByUserId: $convertedByUserId,
            createdByUserId: $convertedByUserId
        );

        $this->rfcRepo->markConverted($rfcId, $decision->id);

        return $this->mustFind($rfcId);
    }

    private function mustFind(int $rfcId): Rfc
    {
        $rfc = $this->rfcRepo->findById($rfcId);
        if ($rfc === null) {
            throw new RuntimeException('ไม่พบ RFC นี้ใน workspace ปัจจุบัน');
        }

        return $rfc;
    }
}
