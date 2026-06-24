<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Rfc\RfcComment;
use App\Domain\Rfc\RfcCommentRepositoryInterface;
use RuntimeException;

final class MySqlRfcCommentRepository extends BaseRepository implements RfcCommentRepositoryInterface
{
    public function listByRfc(int $rfcId): array
    {
        $this->assertRfcBelongsToWorkspace($rfcId);

        $stmt = $this->db->prepare('SELECT * FROM rfc_comments WHERE rfc_id = :rfc_id ORDER BY created_at ASC');
        $stmt->execute(['rfc_id' => $rfcId]);

        return array_map(static fn (array $r): RfcComment => RfcComment::fromRow($r), $stmt->fetchAll());
    }

    public function create(int $rfcId, string $commentText, int $commentedByUserId): RfcComment
    {
        $this->assertRfcBelongsToWorkspace($rfcId);

        $stmt = $this->db->prepare(
            'INSERT INTO rfc_comments (rfc_id, comment_text, commented_by) VALUES (:rfc_id, :text, :user_id)'
        );
        $stmt->execute(['rfc_id' => $rfcId, 'text' => $commentText, 'user_id' => $commentedByUserId]);

        $stmt2 = $this->db->prepare('SELECT * FROM rfc_comments WHERE id = :id');
        $stmt2->execute(['id' => $this->db->lastInsertId()]);
        $row = $stmt2->fetch();

        if ($row === false) {
            throw new RuntimeException('สร้าง comment สำเร็จแต่ดึงข้อมูลกลับไม่ได้');
        }

        return RfcComment::fromRow($row);
    }

    private function assertRfcBelongsToWorkspace(int $rfcId): void
    {
        $stmt = $this->db->prepare('SELECT workspace_id FROM rfcs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $rfcId]);
        $row = $stmt->fetch();

        if ($row === false || (int) $row['workspace_id'] !== $this->workspaceId) {
            throw new RuntimeException("RFC {$rfcId} ไม่อยู่ใน workspace context ปัจจุบัน");
        }
    }
}
