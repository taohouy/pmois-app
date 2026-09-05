<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Registry\AiConsumerCodeResolverInterface;

/**
 * Adapter: resolve Agent code จาก ai_consumers (workspace-scoped)
 * ใช้โดย ProjectTemplateService เพื่อบังคับให้ template อ้าง Agent ผ่าน Registry
 */
final class MySqlAiConsumerCodeResolver implements AiConsumerCodeResolverInterface
{
    public function __construct(private readonly \PDO $db, private readonly int $workspaceId)
    {
    }

    public function resolveByCode(string $code): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, code, name, status FROM ai_consumers WHERE code = :code AND workspace_id = :workspace_id LIMIT 1'
        );
        $stmt->execute(['code' => $code, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }
}
