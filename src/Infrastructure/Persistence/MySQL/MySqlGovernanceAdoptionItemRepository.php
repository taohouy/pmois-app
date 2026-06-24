<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Governance\GovernanceAdoptionItem;
use App\Domain\Governance\GovernanceAdoptionItemRepositoryInterface;
use RuntimeException;

/**
 * Pattern: Scoped ทางอ้อม 1 ชั้น (item -> adoption -> workspace)
 */
final class MySqlGovernanceAdoptionItemRepository extends BaseRepository implements GovernanceAdoptionItemRepositoryInterface
{
    public function findById(int $id): ?GovernanceAdoptionItem
    {
        $stmt = $this->db->prepare(
            'SELECT gai.* FROM governance_adoption_items gai
             INNER JOIN governance_adoptions ga ON ga.id = gai.governance_adoption_id
             WHERE gai.id = :id AND ga.workspace_id = :workspace_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch();

        return $row !== false ? GovernanceAdoptionItem::fromRow($row) : null;
    }

    public function listByAdoption(int $adoptionId): array
    {
        $this->assertAdoptionBelongsToWorkspace($adoptionId);

        $stmt = $this->db->prepare(
            'SELECT * FROM governance_adoption_items WHERE governance_adoption_id = :adoption_id'
        );
        $stmt->execute(['adoption_id' => $adoptionId]);

        return array_map(
            static fn (array $r): GovernanceAdoptionItem => GovernanceAdoptionItem::fromRow($r),
            $stmt->fetchAll()
        );
    }

    public function create(int $adoptionId, int $versionItemId, int $createdByUserId): GovernanceAdoptionItem
    {
        $this->assertAdoptionBelongsToWorkspace($adoptionId);

        $stmt = $this->db->prepare(
            "INSERT INTO governance_adoption_items
                (governance_adoption_id, governance_version_item_id, compliance_status, created_by)
             VALUES (:adoption_id, :item_id, 'in_progress', :created_by)"
        );
        $stmt->execute([
            'adoption_id' => $adoptionId,
            'item_id' => $versionItemId,
            'created_by' => $createdByUserId,
        ]);

        $item = $this->findById((int) $this->db->lastInsertId());
        if ($item === null) {
            throw new RuntimeException('สร้าง adoption item สำเร็จแต่ดึงข้อมูลกลับไม่ได้');
        }

        return $item;
    }

    public function updateComplianceStatus(int $id, string $complianceStatus, ?string $evidenceNote, int $reviewedByUserId): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE governance_adoption_items
             SET compliance_status = :status, evidence_note = :note, reviewed_by = :reviewer, reviewed_at = NOW()
             WHERE id = :id'
        );
        return $stmt->execute([
            'status' => $complianceStatus,
            'note' => $evidenceNote,
            'reviewer' => $reviewedByUserId,
            'id' => $id,
        ]);
    }

    public function listComplianceStatuses(int $adoptionId): array
    {
        $this->assertAdoptionBelongsToWorkspace($adoptionId);

        $stmt = $this->db->prepare(
            'SELECT compliance_status FROM governance_adoption_items WHERE governance_adoption_id = :adoption_id'
        );
        $stmt->execute(['adoption_id' => $adoptionId]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function assertAdoptionBelongsToWorkspace(int $adoptionId): void
    {
        $stmt = $this->db->prepare('SELECT workspace_id FROM governance_adoptions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $adoptionId]);
        $row = $stmt->fetch();

        if ($row === false || (int) $row['workspace_id'] !== $this->workspaceId) {
            throw new RuntimeException("Adoption {$adoptionId} ไม่อยู่ใน workspace context ปัจจุบัน");
        }
    }
}
