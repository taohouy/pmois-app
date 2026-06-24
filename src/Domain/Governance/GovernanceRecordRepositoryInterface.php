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

    public function create(
        string $code,
        string $title,
        string $category,
        ?string $description,
        int $ownerUserId,
        int $createdByUserId
    ): GovernanceRecord;

    public function updateStatus(int $id, string $status): bool;
}
