<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Rfc\Rfc;
use App\Domain\Rfc\RfcRepositoryInterface;
use RuntimeException;

final class MySqlRfcRepository extends BaseRepository implements RfcRepositoryInterface
{
    public function findById(int $id): ?Rfc
    {
        $sql = $this->applyWorkspaceScope('SELECT * FROM rfcs WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1');
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }
        $this->assertWorkspaceMatch($row);

        return Rfc::fromRow($row);
    }

    public function listByWorkspace(): array
    {
        $sql = $this->applyWorkspaceScope('SELECT * FROM rfcs WHERE {{WORKSPACE_FILTER}} ORDER BY created_at DESC');
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll();
        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $r): Rfc => Rfc::fromRow($r), $rows);
    }

    public function create(
        string $code,
        string $title,
        string $description,
        ?int $projectId,
        ?int $relatedGovernanceRecordId,
        int $createdByUserId
    ): Rfc {
        if ($this->workspaceId === null) {
            throw new RuntimeException('ต้องมี workspace context ก่อนสร้าง RFC');
        }

        $stmt = $this->db->prepare(
            "INSERT INTO rfcs (workspace_id, project_id, related_governance_record_id, code, title, description, status, created_by)
             VALUES (:workspace_id, :project_id, :gov_record_id, :code, :title, :description, 'draft', :created_by)"
        );
        $stmt->execute([
            'workspace_id' => $this->workspaceId,
            'project_id' => $projectId,
            'gov_record_id' => $relatedGovernanceRecordId,
            'code' => $code,
            'title' => $title,
            'description' => $description,
            'created_by' => $createdByUserId,
        ]);

        $rfc = $this->findById((int) $this->db->lastInsertId());
        if ($rfc === null) {
            throw new RuntimeException('สร้าง RFC สำเร็จแต่ดึงข้อมูลกลับไม่ได้');
        }

        return $rfc;
    }

    public function updateStatus(int $id, string $status): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE rfcs SET status = :status WHERE id = :id');
        return $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function markSubmitted(int $id): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }
        $stmt = $this->db->prepare("UPDATE rfcs SET status = 'under_review', submitted_at = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function markReviewed(int $id, int $reviewerId, string $status, ?string $reviewNote): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }
        $stmt = $this->db->prepare(
            'UPDATE rfcs SET status = :status, reviewed_by = :reviewer, reviewed_at = NOW(), review_note = :note WHERE id = :id'
        );
        return $stmt->execute(['status' => $status, 'reviewer' => $reviewerId, 'note' => $reviewNote, 'id' => $id]);
    }

    public function markConverted(int $id, int $decisionId): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }
        $stmt = $this->db->prepare(
            "UPDATE rfcs SET status = 'converted_to_decision', resulting_decision_id = :decision_id WHERE id = :id"
        );
        return $stmt->execute(['decision_id' => $decisionId, 'id' => $id]);
    }
}
