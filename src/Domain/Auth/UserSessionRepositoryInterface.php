<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/**
 * PMOIS user sessions — HttpOnly cookie session หลัง LINE Login สำเร็จ
 * token เก็บเป็น sha256 hash เท่านั้น
 */
interface UserSessionRepositoryInterface
{
    public function create(
        int $userId,
        string $sessionTokenHash,
        string $purpose,
        ?string $ipAddress,
        ?string $userAgent,
        string $expiresAt
    ): int;

    /**
     * @return array<string, mixed>|null (non-expired, non-revoked only)
     */
    public function findActiveByTokenHash(string $sessionTokenHash): ?array;

    public function revoke(int $id): bool;
}
