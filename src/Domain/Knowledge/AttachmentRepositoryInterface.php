<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

/** Pattern: Scoped ตรง + Soft Delete (deleted_at) */
interface AttachmentRepositoryInterface
{
    /** คืน null ถ้าไม่พบ "หรือ" ถูก soft delete ไปแล้ว (deleted_at IS NOT NULL) */
    public function findById(int $id): ?Attachment;

    public function create(
        string $originalName,
        string $storedPath,
        string $mimeType,
        int $sizeBytes,
        string $checksum,
        int $uploadedByUserId
    ): Attachment;

    /** Soft delete -- ตั้ง deleted_at, ไม่ลบไฟล์จริงบน disk */
    public function softDelete(int $id): bool;
}
