<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Pattern: Top-level (global) -- ไม่ scope ด้วย workspace_id เพราะ user เป็น identity กลาง
 */
interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findByLineUserId(string $lineUserId): ?User;

    public function isPlatformAdmin(int $userId): bool;

    public function create(string $name, string $email, string $passwordHash): User;

    public function createWithLine(string $name, string $email, string $lineUserId, string $authProvider): User;

    public function updateLineInfo(int $id, string $lineUserId, string $lineDisplayName, string $avatarUrl, string $authProvider): bool;

    public function updateStatus(int $id, string $status): bool;

    /**
     * ตั้งค่า is_platform_admin -- เรียกได้เฉพาะผ่าน flow ที่ควบคุมแยกต่างหาก
     * (เช่น เครื่องมือ admin บน CLI หรือ seed script) ไม่ใช่ endpoint ปกติของผู้ใช้ทั่วไป
     */
    public function setPlatformAdmin(int $id, bool $isPlatformAdmin): bool;
}
