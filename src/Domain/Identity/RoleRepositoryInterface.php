<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Pattern: Top-level (global) -- role ไม่ผูก workspace ตามที่ frozen ไว้ใน System Design
 */
interface RoleRepositoryInterface
{
    public function findById(int $id): ?Role;

    public function findByCode(string $code): ?Role;

    /**
     * @return array<int, Role>
     */
    public function listAll(): array;
}
