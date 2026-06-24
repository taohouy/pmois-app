<?php

declare(strict_types=1);

namespace App\Domain\Governance;

/**
 * Pattern: Scoped ตรง (มี workspace_id denormalized)
 */
interface GovernanceAdoptionRepositoryInterface
{
    public function findById(int $id): ?GovernanceAdoption;

    /** @return array<int, GovernanceAdoption> */
    public function listByProject(int $projectId): array;

    public function create(int $projectId, int $governanceVersionId, int $createdByUserId): GovernanceAdoption;

    public function updateAdoptionStatus(int $id, string $adoptionStatus): bool;

    public function retire(int $id): bool;

    /**
     * เช็คว่า project นี้มี active adoption ของ governance_record เดียวกันอยู่แล้วหรือไม่
     * (ใช้บังคับกฎ "active เดียวต่อ record ต่อ project")
     */
    public function hasActiveAdoptionForRecord(int $projectId, int $governanceRecordId): bool;

    /**
     * @return array<int, array{project_id: int, record_title: string, adoption_status: string}>
     */
    public function listWorkspaceSummary(): array;
}
