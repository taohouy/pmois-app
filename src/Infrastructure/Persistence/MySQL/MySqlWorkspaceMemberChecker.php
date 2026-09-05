<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Registry\WorkspaceMemberCheckerInterface;

/**
 * Adapter: เช็ค active workspace_members (reuses existing table — fail-closed เดิม)
 */
final class MySqlWorkspaceMemberChecker implements WorkspaceMemberCheckerInterface
{
    public function __construct(private readonly \PDO $db)
    {
    }

    public function hasActiveMembership(int $workspaceId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM workspace_members WHERE workspace_id = :workspace_id AND user_id = :user_id AND status = 'active' LIMIT 1"
        );
        $stmt->execute(['workspace_id' => $workspaceId, 'user_id' => $userId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) !== false;
    }
}
