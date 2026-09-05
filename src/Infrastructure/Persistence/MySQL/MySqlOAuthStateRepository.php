<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Auth\OAuthStateRepositoryInterface;
use PDO;

/**
 * Global table (ไม่ scope ต่อ workspace) — state เก็บ hash เท่านั้น
 */
final class MySqlOAuthStateRepository implements OAuthStateRepositoryInterface
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function create(
        string $stateHash,
        string $purpose,
        string $fingerprintHash,
        string $nonce,
        ?string $claimToken,
        string $expiresAt
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO oauth_login_states (state_hash, purpose, nonce, fingerprint_hash, claim_token, expires_at)
             VALUES (:state_hash, :purpose, :nonce, :fingerprint_hash, :claim_token, :expires_at)'
        );
        $stmt->execute([
            'state_hash' => $stateHash,
            'purpose' => $purpose,
            'nonce' => $nonce,
            'fingerprint_hash' => $fingerprintHash,
            'claim_token' => $claimToken,
            'expires_at' => $expiresAt,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findByStateHash(string $stateHash): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM oauth_login_states WHERE state_hash = :hash LIMIT 1'
        );
        $stmt->execute(['hash' => $stateHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function markUsed(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE oauth_login_states SET used_at = NOW() WHERE id = :id AND used_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
    }
}
