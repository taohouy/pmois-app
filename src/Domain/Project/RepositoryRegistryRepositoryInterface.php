<?php

declare(strict_types=1);

namespace App\Domain\Project;

/**
 * GitLab Repository Registry — manual metadata registration (CTO Constraint: GitLab only)
 * ห้ามเรียก GitLab API เพื่อ create group/branch — registration = บันทึก metadata เท่านั้น
 */
interface RepositoryRegistryRepositoryInterface
{
    /**
     * @return array<int, RepositoryRegistry>
     */
    public function findByProjectId(int $projectId): array;

    public function findById(int $id): ?RepositoryRegistry;

    public function findByUrl(string $repositoryUrl): ?RepositoryRegistry;

    /**
     * @param array<string, mixed> $data — snake_case keys matching repositories columns
     */
    public function create(array $data): RepositoryRegistry;

    /**
     * @param array<string, mixed> $data — subset of columns to update
     */
    public function update(int $id, array $data): bool;
}
