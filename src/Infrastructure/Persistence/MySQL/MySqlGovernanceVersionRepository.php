<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Governance\GovernanceVersion;
use App\Domain\Governance\GovernanceVersionRepositoryInterface;
use RuntimeException;

/**
 * Pattern: Scoped ทางอ้อม 1 ชั้น -- governance_versions ไม่มี workspace_id ของตัวเอง
 * ต้อง JOIN ผ่าน governance_records ก่อน filter เสมอ (เหมือน ProjectMemberRepository
 * ใน Phase 0 แต่ join คนละตาราง)
 */
final class MySqlGovernanceVersionRepository extends BaseRepository implements GovernanceVersionRepositoryInterface
{
    public function findById(int $id): ?GovernanceVersion
    {
        $stmt = $this->db->prepare(
            'SELECT gv.* FROM governance_versions gv
             INNER JOIN governance_records gr ON gr.id = gv.governance_record_id
             WHERE gv.id = :id AND gr.workspace_id = :workspace_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch();

        return $row !== false ? GovernanceVersion::fromRow($row) : null;
    }

    public function listByRecord(int $recordId): array
    {
        $this->assertRecordBelongsToWorkspace($recordId);

        $stmt = $this->db->prepare(
            'SELECT * FROM governance_versions WHERE governance_record_id = :record_id ORDER BY created_at DESC'
        );
        $stmt->execute(['record_id' => $recordId]);

        return array_map(
            static fn (array $r): GovernanceVersion => GovernanceVersion::fromRow($r),
            $stmt->fetchAll()
        );
    }

    public function findPublishedByRecord(int $recordId): ?GovernanceVersion
    {
        $this->assertRecordBelongsToWorkspace($recordId);

        $stmt = $this->db->prepare(
            "SELECT * FROM governance_versions
             WHERE governance_record_id = :record_id AND status = 'published'
             LIMIT 1"
        );
        $stmt->execute(['record_id' => $recordId]);
        $row = $stmt->fetch();

        return $row !== false ? GovernanceVersion::fromRow($row) : null;
    }

    public function create(int $recordId, string $versionLabel, string $content, int $createdByUserId): GovernanceVersion
    {
        $this->assertRecordBelongsToWorkspace($recordId);

        $stmt = $this->db->prepare(
            "INSERT INTO governance_versions (governance_record_id, version_label, content, status, created_by)
             VALUES (:record_id, :label, :content, 'draft', :created_by)"
        );
        $stmt->execute([
            'record_id' => $recordId,
            'label' => $versionLabel,
            'content' => $content,
            'created_by' => $createdByUserId,
        ]);

        $version = $this->findById((int) $this->db->lastInsertId());
        if ($version === null) {
            throw new RuntimeException('สร้าง governance version สำเร็จแต่ดึงข้อมูลกลับไม่ได้');
        }

        return $version;
    }

    public function updateContent(int $id, string $content): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE governance_versions SET content = :content WHERE id = :id');
        return $stmt->execute(['content' => $content, 'id' => $id]);
    }

    public function markPublished(int $id, int $publishedByUserId): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $stmt = $this->db->prepare(
            "UPDATE governance_versions
             SET status = 'published', published_by = :published_by, published_at = NOW()
             WHERE id = :id"
        );
        return $stmt->execute(['published_by' => $publishedByUserId, 'id' => $id]);
    }

    public function markSuperseded(int $id): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $stmt = $this->db->prepare("UPDATE governance_versions SET status = 'superseded' WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    private function assertRecordBelongsToWorkspace(int $recordId): void
    {
        $stmt = $this->db->prepare('SELECT workspace_id FROM governance_records WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $recordId]);
        $row = $stmt->fetch();

        if ($row === false || (int) $row['workspace_id'] !== $this->workspaceId) {
            throw new RuntimeException("Governance record {$recordId} ไม่อยู่ใน workspace context ปัจจุบัน");
        }
    }
}
