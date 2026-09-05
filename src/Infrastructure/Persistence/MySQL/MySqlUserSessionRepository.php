<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Auth\UserSessionRepositoryInterface;
use PDO;

/**
 * Global table (ไม่ scope ต่อ workspace) — session token เก็บ hash เท่านั้น
 */
final class MySqlUserSessionRepository implements UserSessionRepositoryInterface
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function create(
        int $userId,
        string $sessionTokenHash,
        string $purpose,
        ?string $ipAddress,
        ?string $userAgent,
        string $expiresAt
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO user_sessions (user_id, session_token_hash, purpose, ip_address, user_agent, expires_at)
             VALUES (:user_id, :session_token_hash, :purpose, :ip_address, :user_agent, :expires_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'session_token_hash' => $sessionTokenHash,
            'purpose' => $purpose,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => $expiresAt,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findActiveByTokenHash(string $sessionTokenHash): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM user_sessions
             WHERE session_token_hash = :hash AND revoked_at IS NULL AND expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute(['hash' => $sessionTokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function revoke(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE user_sessions SET revoked_at = NOW() WHERE id = :id AND revoked_at IS NULL'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
