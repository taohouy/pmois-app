<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Registry\GovernanceVersionCheckerInterface;

/**
 * Adapter: เช็คว่า governance_version เป็น published และอยู่ใน workspace นี้
 */
final class MySqlGovernanceVersionChecker implements GovernanceVersionCheckerInterface
{
    public function __construct(private readonly \PDO $db)
    {
    }

    public function isPublishedInWorkspace(int $workspaceId, int $governanceVersionId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1
             FROM governance_versions gv
             JOIN governance_records gr ON gr.id = gv.governance_record_id
             WHERE gv.id = :id AND gv.status = 'published' AND gr.workspace_id = :workspace_id
             LIMIT 1"
        );
        $stmt->execute(['id' => $governanceVersionId, 'workspace_id' => $workspaceId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) !== false;
    }
}
