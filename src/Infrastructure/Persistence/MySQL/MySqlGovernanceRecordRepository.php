<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Governance\GovernanceRecord;
use App\Domain\Governance\GovernanceRecordRepositoryInterface;
use RuntimeException;

final class MySqlGovernanceRecordRepository extends BaseRepository implements GovernanceRecordRepositoryInterface
{
    public function findById(int $id): ?GovernanceRecord
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM governance_records WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }
        $this->assertWorkspaceMatch($row);

        return GovernanceRecord::fromRow($row);
    }

    public function listByWorkspace(): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM governance_records WHERE {{WORKSPACE_FILTER}} ORDER BY created_at DESC'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll();
        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $r): GovernanceRecord => GovernanceRecord::fromRow($r), $rows);
    }

    public function listByWorkspaceFiltered(?string $audience, ?string $policyType, ?string $category): array
    {
        $conditions = [];
        $params = ['workspace_id' => $this->workspaceId];

        if ($audience !== null) {
            $conditions[] = 'audience = :audience';
            $params['audience'] = $audience;
        }
        if ($policyType !== null) {
            $conditions[] = 'policy_type = :policy_type';
            $params['policy_type'] = $policyType;
        }
        if ($category !== null) {
            $conditions[] = 'category = :category';
            $params['category'] = $category;
        }

        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM governance_records WHERE {{WORKSPACE_FILTER}}
             ' . ($conditions !== [] ? 'AND ' . implode(' AND ', $conditions) : '') . '
             ORDER BY created_at DESC'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $r): GovernanceRecord => GovernanceRecord::fromRow($r), $rows);
    }

    public function create(
        string $code,
        string $title,
        string $category,
        ?string $description,
        int $ownerUserId,
        int $createdByUserId,
        ?string $audience = null,
        ?string $policyType = null
    ): GovernanceRecord {
        if ($this->workspaceId === null) {
            throw new RuntimeException('ต้องมี workspace context ก่อนสร้าง governance record');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO governance_records (workspace_id, code, title, category, audience, policy_type, description, owner_user_id, status, created_by)
             VALUES (:workspace_id, :code, :title, :category, :audience, :policy_type, :description, :owner_user_id, :status, :created_by)'
        );
        $stmt->execute([
            'workspace_id' => $this->workspaceId,
            'code' => $code,
            'title' => $title,
            'category' => $category,
            'audience' => $audience,
            'policy_type' => $policyType,
            'description' => $description,
            'owner_user_id' => $ownerUserId,
            'status' => 'draft',
            'created_by' => $createdByUserId,
        ]);

        $record = $this->findById((int) $this->db->lastInsertId());
        if ($record === null) {
            throw new RuntimeException('สร้าง governance record สำเร็จแต่ดึงข้อมูลกลับไม่ได้');
        }

        return $record;
    }

    public function updateStatus(int $id, string $status): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $sql = $this->applyWorkspaceScope(
            'UPDATE governance_records SET status = :status WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);

        return $stmt->execute(['status' => $status, 'id' => $id, 'workspace_id' => $this->workspaceId]);
    }
}
