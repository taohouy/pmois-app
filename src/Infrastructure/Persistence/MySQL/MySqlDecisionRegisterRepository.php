<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Decision\DecisionRegister;
use App\Domain\Decision\DecisionRegisterRepositoryInterface;
use RuntimeException;

final class MySqlDecisionRegisterRepository extends BaseRepository implements DecisionRegisterRepositoryInterface
{
    public function findById(int $id): ?DecisionRegister
    {
        $sql = $this->applyWorkspaceScope('SELECT * FROM decision_registers WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1');
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }
        $this->assertWorkspaceMatch($row);

        return DecisionRegister::fromRow($row);
    }

    public function listByWorkspace(): array
    {
        $sql = $this->applyWorkspaceScope('SELECT * FROM decision_registers WHERE {{WORKSPACE_FILTER}} ORDER BY created_at DESC');
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll();
        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $r): DecisionRegister => DecisionRegister::fromRow($r), $rows);
    }

    public function create(
        string $category,
        string $title,
        ?string $context,
        string $decisionDescription,
        string $decisionDate,
        ?int $projectId,
        ?int $relatedGovernanceRecordId,
        string $status,
        int $decidedByUserId,
        int $createdByUserId
    ): DecisionRegister {
        if ($this->workspaceId === null) {
            throw new RuntimeException('ต้องมี workspace context ก่อนสร้าง decision');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO decision_registers
                (workspace_id, project_id, related_governance_record_id, category, title, context,
                 decision_description, decision_date, decided_by, status, created_by)
             VALUES
                (:workspace_id, :project_id, :gov_record_id, :category, :title, :context,
                 :description, :decision_date, :decided_by, :status, :created_by)'
        );
        $stmt->execute([
            'workspace_id' => $this->workspaceId,
            'project_id' => $projectId,
            'gov_record_id' => $relatedGovernanceRecordId,
            'category' => $category,
            'title' => $title,
            'context' => $context,
            'description' => $decisionDescription,
            'decision_date' => $decisionDate,
            'decided_by' => $decidedByUserId,
            'status' => $status,
            'created_by' => $createdByUserId,
        ]);

        $decision = $this->findById((int) $this->db->lastInsertId());
        if ($decision === null) {
            throw new RuntimeException('สร้าง decision สำเร็จแต่ดึงข้อมูลกลับไม่ได้');
        }

        return $decision;
    }

    public function updateStatus(int $id, string $status): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE decision_registers SET status = :status WHERE id = :id');
        return $stmt->execute(['status' => $status, 'id' => $id]);
    }
}
