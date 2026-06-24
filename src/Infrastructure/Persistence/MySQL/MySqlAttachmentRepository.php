<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Knowledge\Attachment;
use App\Domain\Knowledge\AttachmentRepositoryInterface;
use RuntimeException;

/**
 * Pattern: Scoped ตรง + Soft Delete
 * ทุก query (findById) กรอง "deleted_at IS NULL" เสมอ -- record ที่ soft delete แล้ว
 * ถือว่า "ไม่เจอ" สำหรับ caller ทั่วไป (เหมือนไม่มีอยู่จริง) แต่ยังอยู่ใน DB และไฟล์ยังอยู่บน disk
 */
final class MySqlAttachmentRepository extends BaseRepository implements AttachmentRepositoryInterface
{
    public function findById(int $id): ?Attachment
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM attachments WHERE id = :id AND deleted_at IS NULL AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }
        $this->assertWorkspaceMatch($row);

        return Attachment::fromRow($row);
    }

    public function create(
        string $originalName,
        string $storedPath,
        string $mimeType,
        int $sizeBytes,
        string $checksum,
        int $uploadedByUserId
    ): Attachment {
        if ($this->workspaceId === null) {
            throw new RuntimeException('ต้องมี workspace context ก่อนสร้าง attachment');
        }

        $stmt = $this->db->prepare(
            "INSERT INTO attachments (workspace_id, original_name, stored_path, mime_type, size_bytes, storage_type, checksum, uploaded_by)
             VALUES (:workspace_id, :name, :path, :mime, :size, 'local', :checksum, :uploaded_by)"
        );
        $stmt->execute([
            'workspace_id' => $this->workspaceId,
            'name' => $originalName,
            'path' => $storedPath,
            'mime' => $mimeType,
            'size' => $sizeBytes,
            'checksum' => $checksum,
            'uploaded_by' => $uploadedByUserId,
        ]);

        $attachment = $this->findById((int) $this->db->lastInsertId());
        if ($attachment === null) {
            throw new RuntimeException('สร้าง attachment สำเร็จแต่ดึงข้อมูลกลับไม่ได้');
        }

        return $attachment;
    }

    public function softDelete(int $id): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE attachments SET deleted_at = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
