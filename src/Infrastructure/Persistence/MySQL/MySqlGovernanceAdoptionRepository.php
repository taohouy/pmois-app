<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Governance\GovernanceAdoption;
use App\Domain\Governance\GovernanceAdoptionRepositoryInterface;
use RuntimeException;

final class MySqlGovernanceAdoptionRepository extends BaseRepository implements GovernanceAdoptionRepositoryInterface
{
    public function findById(int $id): ?GovernanceAdoption
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM governance_adoptions WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }
        $this->assertWorkspaceMatch($row);

        return GovernanceAdoption::fromRow($row);
    }

    public function listByProject(int $projectId): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM governance_adoptions WHERE project_id = :project_id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['project_id' => $projectId, 'workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll();
        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $r): GovernanceAdoption => GovernanceAdoption::fromRow($r), $rows);
    }

    public function create(int $projectId, int $governanceVersionId, int $createdByUserId): GovernanceAdoption
    {
        if ($this->workspaceId === null) {
            throw new RuntimeException('ต้องมี workspace context ก่อนสร้าง adoption');
        }

        $stmt = $this->db->prepare(
            "INSERT INTO governance_adoptions
                (workspace_id, project_id, governance_version_id, adoption_status, status, adopted_date, created_by)
             VALUES (:workspace_id, :project_id, :version_id, 'in_progress', 'active', CURDATE(), :created_by)"
        );
        $stmt->execute([
            'workspace_id' => $this->workspaceId,
            'project_id' => $projectId,
            'version_id' => $governanceVersionId,
            'created_by' => $createdByUserId,
        ]);

        $adoption = $this->findById((int) $this->db->lastInsertId());
        if ($adoption === null) {
            throw new RuntimeException('สร้าง adoption สำเร็จแต่ดึงข้อมูลกลับไม่ได้');
        }

        return $adoption;
    }

    public function updateAdoptionStatus(int $id, string $adoptionStatus): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE governance_adoptions SET adoption_status = :status WHERE id = :id');
        return $stmt->execute(['status' => $adoptionStatus, 'id' => $id]);
    }

    public function retire(int $id): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $stmt = $this->db->prepare("UPDATE governance_adoptions SET status = 'superseded' WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function hasActiveAdoptionForRecord(int $projectId, int $governanceRecordId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM governance_adoptions ga
             INNER JOIN governance_versions gv ON gv.id = ga.governance_version_id
             WHERE ga.project_id = :project_id
               AND gv.governance_record_id = :record_id
               AND ga.status = 'active'
               AND ga.workspace_id = :workspace_id
             LIMIT 1"
        );
        $stmt->execute([
            'project_id' => $projectId,
            'record_id' => $governanceRecordId,
            'workspace_id' => $this->workspaceId,
        ]);

        return $stmt->fetch() !== false;
    }

    public function listWorkspaceSummary(): array
    {
        $stmt = $this->db->prepare(
            "SELECT ga.project_id, gr.title AS record_title, ga.adoption_status
             FROM governance_adoptions ga
             INNER JOIN governance_versions gv ON gv.id = ga.governance_version_id
             INNER JOIN governance_records gr ON gr.id = gv.governance_record_id
             WHERE ga.workspace_id = :workspace_id AND ga.status = 'active'"
        );
        $stmt->execute(['workspace_id' => $this->workspaceId]);

        return $stmt->fetchAll();
    }
}
