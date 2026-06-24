<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Governance\GovernanceVersionItem;
use App\Domain\Governance\GovernanceVersionItemRepositoryInterface;
use RuntimeException;

/**
 * ⚠️ Pattern: Scoped ทางอ้อม 2 ชั้น (item -> version -> record -> workspace)
 *
 * นี่คือ Repository ที่ Workspace Scoping Test Plan (Phase 1 Specification Package
 * หมวด 5) ระบุไว้ว่าเสี่ยง bug สูงสุด -- ทุก query ต้อง JOIN ทะลุ 2 ชั้นเสมอ
 * ไม่มีทางลัด query ตรงจาก governance_version_items อย่างเดียวได้
 */
final class MySqlGovernanceVersionItemRepository extends BaseRepository implements GovernanceVersionItemRepositoryInterface
{
    private const JOIN_CLAUSE = '
        FROM governance_version_items gvi
        INNER JOIN governance_versions gv ON gv.id = gvi.governance_version_id
        INNER JOIN governance_records gr ON gr.id = gv.governance_record_id
    ';

    public function findById(int $id): ?GovernanceVersionItem
    {
        $stmt = $this->db->prepare(
            'SELECT gvi.* ' . self::JOIN_CLAUSE . '
             WHERE gvi.id = :id AND gr.workspace_id = :workspace_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch();

        return $row !== false ? GovernanceVersionItem::fromRow($row) : null;
    }

    public function listByVersion(int $versionId): array
    {
        // เช็คก่อนว่า version นี้อยู่ใน workspace context จริง (join 1 ชั้นพอสำหรับ assert นี้)
        $this->assertVersionBelongsToWorkspace($versionId);

        $stmt = $this->db->prepare(
            'SELECT * FROM governance_version_items
             WHERE governance_version_id = :version_id
             ORDER BY sequence_order ASC'
        );
        $stmt->execute(['version_id' => $versionId]);

        return array_map(
            static fn (array $r): GovernanceVersionItem => GovernanceVersionItem::fromRow($r),
            $stmt->fetchAll()
        );
    }

    public function create(
        int $versionId,
        string $itemCode,
        string $title,
        ?string $description,
        int $sequenceOrder,
        int $createdByUserId
    ): GovernanceVersionItem {
        $this->assertVersionBelongsToWorkspace($versionId);

        $stmt = $this->db->prepare(
            'INSERT INTO governance_version_items
                (governance_version_id, item_code, title, description, sequence_order, created_by)
             VALUES (:version_id, :code, :title, :description, :seq, :created_by)'
        );
        $stmt->execute([
            'version_id' => $versionId,
            'code' => $itemCode,
            'title' => $title,
            'description' => $description,
            'seq' => $sequenceOrder,
            'created_by' => $createdByUserId,
        ]);

        $item = $this->findById((int) $this->db->lastInsertId());
        if ($item === null) {
            throw new RuntimeException('สร้าง version item สำเร็จแต่ดึงข้อมูลกลับไม่ได้');
        }

        return $item;
    }

    /**
     * Join 1 ชั้น (version -> record -> workspace) -- ใช้ตรวจสอบก่อน insert/list
     * แยกจาก findById() ที่ join 2 ชั้นเต็ม เพราะกรณีนี้ยังไม่มี item id ให้เช็ค
     */
    private function assertVersionBelongsToWorkspace(int $versionId): void
    {
        $stmt = $this->db->prepare(
            'SELECT gr.workspace_id
             FROM governance_versions gv
             INNER JOIN governance_records gr ON gr.id = gv.governance_record_id
             WHERE gv.id = :version_id
             LIMIT 1'
        );
        $stmt->execute(['version_id' => $versionId]);
        $row = $stmt->fetch();

        if ($row === false || (int) $row['workspace_id'] !== $this->workspaceId) {
            throw new RuntimeException("Governance version {$versionId} ไม่อยู่ใน workspace context ปัจจุบัน");
        }
    }
}
