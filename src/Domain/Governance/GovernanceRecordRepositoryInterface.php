<?php

declare(strict_types=1);

namespace App\Domain\Governance;

/**
 * Pattern: Scoped ตรง (มี workspace_id ในตัวเอง) -- เหมือน ProjectRepository
 */
interface GovernanceRecordRepositoryInterface
{
    public function findById(int $id): ?GovernanceRecord;

    /** @return array<int, GovernanceRecord> */
    public function listByWorkspace(): array;

    /**
     * M4 — filter ตาม audience (CTO/Dev Working Instructions) และ policy_type (Review/Delivery/Approval Policy)
     *
     * @return array<int, GovernanceRecord>
     */
    public function listByWorkspaceFiltered(?string $audience, ?string $policyType, ?string $category): array;

    public function create(
        string $code,
        string $title,
        string $category,
        ?string $description,
        int $ownerUserId,
        int $createdByUserId,
        ?string $audience = null,
        ?string $policyType = null
    ): GovernanceRecord;

    public function updateStatus(int $id, string $status): bool;
}
