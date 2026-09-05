<?php

declare(strict_types=1);

namespace App\Domain\Governance;

/**
 * GovernancePolicyService — M4 PMO Governance
 *
 * Configuration over Hardcode: policy types และ audiences เป็นค่า config ในคลาสนี้
 * (เพิ่ม policy type ใหม่ เช่น 'security' ภายหลัง แก้ที่เดียว ไม่แตะ schema)
 *
 * Rules:
 *  - Working Instructions = category 'guideline' + audience (cto หรือ dev)
 *  - Policies = category 'policy' + policy_type (review / delivery / approval)
 *  - Templates ทั่วไป (policy/standard/framework/guideline) ใช้ได้เหมือนเดิม (audience NULL)
 */
final class GovernancePolicyService
{
    public const POLICY_TYPES = ['review', 'delivery', 'approval'];
    public const AUDIENCES = ['all', 'cto', 'dev', 'pmo'];
    private const CATEGORIES = ['policy', 'standard', 'framework', 'guideline'];

    public function __construct(private readonly GovernanceRecordRepositoryInterface $recordRepository)
    {
    }

    /**
     * ตรวจ audience/policy_type ตามกฎ แล้วสร้าง record
     *
     * @throws \InvalidArgumentException VALIDATION_ERROR
     */
    public function createTemplate(
        string $code,
        string $title,
        string $category,
        ?string $description,
        int $ownerUserId,
        int $createdByUserId,
        ?string $audience = null,
        ?string $policyType = null
    ): GovernanceRecord {
        $errors = [];

        if (!in_array($category, self::CATEGORIES, true)) {
            $errors[] = 'category must be one of: ' . implode(', ', self::CATEGORIES);
        }
        if ($audience !== null && !in_array($audience, self::AUDIENCES, true)) {
            $errors[] = 'audience must be one of: ' . implode(', ', self::AUDIENCES);
        }
        if ($policyType !== null) {
            if (!in_array($policyType, self::POLICY_TYPES, true)) {
                $errors[] = 'policy_type must be one of: ' . implode(', ', self::POLICY_TYPES);
            }
            if ($category !== 'policy') {
                $errors[] = 'policy_type is only allowed with category="policy"';
            }
        }
        // Working Instructions: guideline ที่ระบุ audience cto/dev
        if ($audience !== null && in_array($audience, ['cto', 'dev'], true) && $category !== 'guideline') {
            $errors[] = 'audience cto/dev working instructions require category="guideline"';
        }
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        return $this->recordRepository->create(
            $code, $title, $category, $description, $ownerUserId, $createdByUserId, $audience, $policyType
        );
    }

    /**
     * Review / Delivery / Approval Policies
     *
     * @return array<int, GovernanceRecord>
     */
    public function listPolicies(?string $policyType): array
    {
        if ($policyType !== null && !in_array($policyType, self::POLICY_TYPES, true)) {
            throw new \InvalidArgumentException('policy_type must be one of: ' . implode(', ', self::POLICY_TYPES));
        }

        return $this->recordRepository->listByWorkspaceFiltered(null, $policyType, 'policy');
    }

    /**
     * CTO / Dev Working Instructions
     *
     * @return array<int, GovernanceRecord>
     */
    public function listWorkingInstructions(?string $audience): array
    {
        if ($audience !== null && !in_array($audience, ['cto', 'dev'], true)) {
            throw new \InvalidArgumentException('audience must be cto or dev');
        }

        return $this->recordRepository->listByWorkspaceFiltered($audience, null, 'guideline');
    }
}
