<?php

declare(strict_types=1);

namespace App\Domain\Governance;

use PDO;
use RuntimeException;

/**
 * GovernanceVersionService
 *
 * คุม state machine: draft -> published -> superseded
 * Business rule สำคัญที่สุด: Auto-supersede (CTO Decision, Phase 1 Planning Package v0.1)
 * publish() ต้อง supersede version ที่ published อยู่เดิมของ record เดียวกัน "ในธุรกรรมเดียว"
 * ไม่ใช่ 2 ธุรกรรมแยก เพื่อไม่ให้เกิดสถานะ "ไม่มี version ใด published เลย" ถ้า step ใดล้มเหลว
 */
final class GovernanceVersionService
{
    public function __construct(
        private readonly PDO $db,
        private readonly GovernanceVersionRepositoryInterface $versionRepo
    ) {
    }

    /**
     * @return array{published: GovernanceVersion, superseded: ?GovernanceVersion}
     */
    public function publish(int $versionId, int $publishedByUserId): array
    {
        $version = $this->versionRepo->findById($versionId);
        if ($version === null) {
            throw new RuntimeException('ไม่พบ governance version นี้ในworkspace ปัจจุบัน');
        }

        if ($version->status !== 'draft') {
            throw new RuntimeException("Publish ได้เฉพาะ version ที่ status='draft' เท่านั้น (ปัจจุบัน: {$version->status})");
        }

        $previousPublished = $this->versionRepo->findPublishedByRecord($version->governanceRecordId);

        // ✅ แก้: เช็คก่อนว่ามี transaction เปิดอยู่แล้วหรือยัง (เช่น ตอนรันใน test ที่
        // ห่อ transaction ของตัวเองไว้เพื่อ rollback cleanup) -- ถ้ามีอยู่แล้วไม่เปิดซ้ำ
        // เพราะ PDO ไม่รองรับ nested transaction จะ throw "There is already an active transaction"
        $ownsTransaction = !$this->db->inTransaction();

        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            if ($previousPublished !== null) {
                $superseded = $this->versionRepo->markSuperseded($previousPublished->id);
                if (!$superseded) {
                    throw new RuntimeException('Supersede version เดิมไม่สำเร็จ');
                }
            }

            $published = $this->versionRepo->markPublished($versionId, $publishedByUserId);
            if (!$published) {
                throw new RuntimeException('Publish version ใหม่ไม่สำเร็จ');
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return [
            'published' => $this->versionRepo->findById($versionId),
            'superseded' => $previousPublished !== null ? $this->versionRepo->findById($previousPublished->id) : null,
        ];
    }

    public function updateContent(int $versionId, string $content): bool
    {
        $version = $this->versionRepo->findById($versionId);
        if ($version === null) {
            throw new RuntimeException('ไม่พบ governance version นี้ในworkspace ปัจจุบัน');
        }

        if ($version->status !== 'draft') {
            throw new RuntimeException('แก้ไขเนื้อหาได้เฉพาะ version ที่ status=draft เท่านั้น (content immutable หลัง publish)');
        }

        return $this->versionRepo->updateContent($versionId, $content);
    }
}
