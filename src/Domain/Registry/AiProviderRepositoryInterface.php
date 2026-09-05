<?php

declare(strict_types=1);

namespace App\Domain\Registry;

/**
 * AI Provider Registry — global (ไม่ scope ต้ workspace)
 * จัดการโดย is_platform_admin เท่านั้น (เช่นเดียวกับ workspace.create)
 */
interface AiProviderRepositoryInterface
{
    /**
     * @return array<int, AiProvider>
     */
    public function listAll(): array;

    public function findById(int $id): ?AiProvider;

    public function findByCode(string $code): ?AiProvider;

    public function create(string $code, string $name, int $createdBy): AiProvider;

    public function updateStatus(int $id, string $status): bool;
}
