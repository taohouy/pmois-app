<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Auth\ApiTokenRepositoryInterface;

/**
 * Pattern: Scoped ตรง
 * หมายเหตุ: Phase 0 ยังไม่มี ai_consumer_id (เพิ่มทีหลังด้วย ALTER TABLE ใน Phase 4)
 */
final class MySqlApiTokenRepository extends BaseRepository implements ApiTokenRepositoryInterface
{
    public function create(int $workspaceId, int $createdByUserId, string $tokenName, ?array $scopes, ?int $aiConsumerId = null, ?int $projectId = null): array
    {
        $rawToken = bin2hex(random_bytes(32)); // 64 hex chars
        $tokenHash = hash('sha256', $rawToken);

        $stmt = $this->db->prepare(
            'INSERT INTO api_tokens (workspace_id, project_id, created_by_user_id, ai_consumer_id, token_name, token_hash, scopes, status)
             VALUES (:workspace_id, :project_id, :created_by_user_id, :ai_consumer_id, :token_name, :token_hash, :scopes, :status)'
        );
        $stmt->execute([
            'workspace_id' => $workspaceId,
            'project_id' => $projectId,
            'created_by_user_id' => $createdByUserId,
            'ai_consumer_id' => $aiConsumerId,
            'token_name' => $tokenName,
            'token_hash' => $tokenHash,
            'scopes' => $scopes !== null ? implode(',', $scopes) : null,
            'status' => 'active',
        ]);

        return [
            'id' => (int) $this->db->lastInsertId(),
            'raw_token' => $rawToken, // แสดงให้ผู้ใช้เห็นครั้งเดียวตอนสร้าง -- หลังจากนี้กู้คืนไม่ได้
        ];
    }

    public function listByProject(int $workspaceId, int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, token_name, scopes, status, expires_at, last_used_at, created_at
             FROM api_tokens
             WHERE workspace_id = :workspace_id AND project_id = :project_id
             ORDER BY created_at DESC'
        );
        $stmt->execute(['workspace_id' => $workspaceId, 'project_id' => $projectId]);

        return $stmt->fetchAll();
    }

    public function findByHash(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM api_tokens WHERE token_hash = :hash LIMIT 1');
        $stmt->execute(['hash' => $tokenHash]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function revoke(int $id): bool
    {
        $sql = $this->applyWorkspaceScope(
            "UPDATE api_tokens SET status = 'revoked' WHERE id = :id AND {{WORKSPACE_FILTER}}"
        );
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
    }

    public function touchLastUsed(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function listByWorkspace(int $workspaceId): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT id, project_id, token_name, status, expires_at, last_used_at, created_at
             FROM api_tokens WHERE {{WORKSPACE_FILTER}} ORDER BY created_at DESC'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['workspace_id' => $workspaceId]);
        return $stmt->fetchAll();
    }
}
