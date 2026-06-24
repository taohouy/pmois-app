<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Ai\AiContextExportRepositoryInterface;

final class MySqlAiContextExportRepository implements AiContextExportRepositoryInterface
{
    public function __construct(
        private readonly \PDO $db,
        private readonly ?int $workspaceId
    ) {
    }

    public function record(
        int $apiTokenId,
        ?int $aiConsumerId,
        string $exportType,
        ?string $scopeEntityType,
        ?int $scopeEntityId,
        array $payloadSnapshot
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO ai_context_exports
                (workspace_id, api_token_id, ai_consumer_id, export_type, scope_entity_type, scope_entity_id, payload_snapshot)
             VALUES
                (:workspace_id, :token_id, :consumer_id, :export_type, :scope_type, :scope_id, :payload)'
        );
        $stmt->execute([
            'workspace_id' => $this->workspaceId,
            'token_id' => $apiTokenId,
            'consumer_id' => $aiConsumerId,
            'export_type' => $exportType,
            'scope_type' => $scopeEntityType,
            'scope_id' => $scopeEntityId,
            'payload' => json_encode($payloadSnapshot, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
