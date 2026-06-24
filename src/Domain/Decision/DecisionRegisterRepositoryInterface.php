<?php

declare(strict_types=1);

namespace App\Domain\Decision;

/** Pattern: Scoped ตรง */
interface DecisionRegisterRepositoryInterface
{
    public function findById(int $id): ?DecisionRegister;

    /** @return array<int, DecisionRegister> */
    public function listByWorkspace(): array;

    public function create(
        string $category,
        string $title,
        ?string $context,
        string $decisionDescription,
        string $decisionDate,
        ?int $projectId,
        ?int $relatedGovernanceRecordId,
        string $status,
        int $decidedByUserId,
        int $createdByUserId
    ): DecisionRegister;

    public function updateStatus(int $id, string $status): bool;
}
