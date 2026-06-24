<?php

declare(strict_types=1);

namespace App\Domain\Rfc;

/** Pattern: Scoped ตรง */
interface RfcRepositoryInterface
{
    public function findById(int $id): ?Rfc;

    /** @return array<int, Rfc> */
    public function listByWorkspace(): array;

    public function create(
        string $code,
        string $title,
        string $description,
        ?int $projectId,
        ?int $relatedGovernanceRecordId,
        int $createdByUserId
    ): Rfc;

    public function updateStatus(int $id, string $status): bool;

    public function markSubmitted(int $id): bool;

    public function markReviewed(int $id, int $reviewerId, string $status, ?string $reviewNote): bool;

    public function markConverted(int $id, int $decisionId): bool;
}
