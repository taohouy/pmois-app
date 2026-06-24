<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

/**
 * Pattern: Top-level — ไม่ scope ด้วย workspace_id เพราะ Workspace คือตัวบนสุดของระบบ
 * (ตรงข้ามกับ Repository อื่นที่ extends BaseRepository)
 */
interface WorkspaceRepositoryInterface
{
    public function findById(int $id): ?Workspace;

    public function findByCode(string $code): ?Workspace;

    /**
     * @return array<int, Workspace>
     */
    public function listAll(): array;

    public function create(
        string $code,
        string $name,
        ?string $description,
        int $createdByUserId
    ): Workspace;

    public function update(int $id, string $name, ?string $description, string $status): bool;
}
