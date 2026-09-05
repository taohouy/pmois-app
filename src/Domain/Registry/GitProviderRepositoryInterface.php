<?php

declare(strict_types=1);

namespace App\Domain\Registry;

/**
 * Git Provider Registry — global (ไม่ scope ต่อ workspace)
 * CTO Constraint: GitLab เท่านั้น (seed เดียวใน migration 0045)
 * จัดการโดย is_platform_admin เท่านั้น
 */
interface GitProviderRepositoryInterface
{
    /**
     * @return array<int, GitProvider>
     */
    public function listAll(): array;

    public function findById(int $id): ?GitProvider;

    public function findByCode(string $code): ?GitProvider;

    public function create(string $code, string $name, ?string $baseUrl, int $createdBy): GitProvider;

    public function updateStatus(int $id, string $status): bool;
}
