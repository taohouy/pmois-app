<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/**
 * OAuth login state store (one-time use)
 *
 * กติกา (CTO Review 1.1):
 *  - state เก็บเป็น sha256 hash เท่านั้น — ไม่เก็บ raw
 *  - ผูกกับ browser ผ่าน fingerprint_hash (cookie ที่ client ถือ)
 *  - one-time use: markUsed แล้วห้ามใช้ซ้ำ
 */
interface OAuthStateRepositoryInterface
{
    /**
     * @return int state row id
     */
    public function create(
        string $stateHash,
        string $purpose,
        string $fingerprintHash,
        string $nonce,
        ?string $claimToken,
        string $expiresAt
    ): int;

    /**
     * @return array<string, mixed>|null
     */
    public function findByStateHash(string $stateHash): ?array;

    public function markUsed(int $id): void;
}
