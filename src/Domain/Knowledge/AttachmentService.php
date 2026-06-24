<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

use RuntimeException;

/**
 * AttachmentService
 *
 * คุม Upload Policy เต็มรูปแบบตาม CTO Decision (Phase 2 Specification Package):
 *   - Max size: 50 MB
 *   - Allowlist: pdf, docx, xlsx, pptx, png, jpg, jpeg, gif, txt, csv, zip
 *   - Blocklist: php, exe, bat, cmd, sh, ps1 (reject แม้ไม่ตรง allowlist เพื่อความชัดเจน)
 *   - Disguised File Protection (CTO Decision เพิ่ม): ต้องเช็คทั้ง extension และ MIME
 *     sniffing (magic bytes จริงของไฟล์) -- "ทั้ง 2 อย่างต้องผ่าน" ถ้า extension บอกว่า
 *     เป็น .pdf แต่ magic bytes จริงไม่ใช่ PDF -> reject ทันที
 */
final class AttachmentService
{
    private const MAX_SIZE_BYTES = 50 * 1024 * 1024; // 50 MB

    private const ALLOWED_EXTENSIONS = ['pdf', 'docx', 'xlsx', 'pptx', 'png', 'jpg', 'jpeg', 'gif', 'txt', 'csv', 'zip'];

    private const BLOCKED_EXTENSIONS = ['php', 'exe', 'bat', 'cmd', 'sh', 'ps1'];

    /**
     * Map นามสกุล -> MIME type ที่ "ควรจะเป็น" จริง (ใช้เทียบกับผลจาก MIME sniffing)
     * บางนามสกุลมีได้หลาย MIME ที่ valid (เช่น .jpg อาจเป็น image/jpeg เท่านั้นจริงๆ
     * แต่ .docx/.xlsx/.pptx เป็น zip-based format ข้างใน บางระบบ sniff ได้แค่ application/zip)
     *
     * @var array<string, array<int, string>>
     */
    private const EXPECTED_MIME_BY_EXTENSION = [
        'pdf' => ['application/pdf'],
        'docx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'pptx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif' => ['image/gif'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv'],
        'zip' => ['application/zip'],
    ];

    public function __construct(
        private readonly AttachmentRepositoryInterface $attachmentRepo,
        private readonly string $storageBasePath // เช่น /www/wwwroot/.../storage/uploads
    ) {
    }

    /**
     * @param string $tmpFilePath path ของไฟล์ที่ upload มาชั่วคราว (เช่น $_FILES['file']['tmp_name'])
     */
    public function upload(string $originalName, string $tmpFilePath, int $sizeBytes, int $uploadedByUserId, int $workspaceId): Attachment
    {
        $this->validateSize($sizeBytes);

        $extension = $this->extractExtension($originalName);
        $this->validateExtension($extension);

        $actualMimeType = $this->sniffMimeType($tmpFilePath);
        $this->validateMimeMatchesExtension($extension, $actualMimeType);

        $checksum = hash_file('sha256', $tmpFilePath);
        if ($checksum === false) {
            throw new RuntimeException('คำนวณ checksum ไม่สำเร็จ');
        }

        $storedFilename = $this->generateStoredFilename($extension);
        $relativePath = $this->buildRelativePath($workspaceId, $storedFilename);
        $absolutePath = $this->storageBasePath . '/' . $relativePath;

        $this->ensureDirectoryExists(dirname($absolutePath));

        if (!@copy($tmpFilePath, $absolutePath)) {
            throw new RuntimeException('บันทึกไฟล์ลง storage ไม่สำเร็จ');
        }

        return $this->attachmentRepo->create(
            originalName: $originalName, // เก็บชื่อเดิมไว้แค่ metadata -- ไม่ใช่ชื่อไฟล์จริงบน disk (ป้องกัน path traversal)
            storedPath: $relativePath,
            mimeType: $actualMimeType,
            sizeBytes: $sizeBytes,
            checksum: $checksum,
            uploadedByUserId: $uploadedByUserId
        );
    }

    public function delete(int $attachmentId): bool
    {
        return $this->attachmentRepo->softDelete($attachmentId);
    }

    private function validateSize(int $sizeBytes): void
    {
        if ($sizeBytes > self::MAX_SIZE_BYTES) {
            throw new RuntimeException(
                'VALIDATION_ERROR: ไฟล์มีขนาดเกิน 50 MB (ได้รับ ' . round($sizeBytes / 1024 / 1024, 2) . ' MB)'
            );
        }
    }

    private function extractExtension(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === '') {
            throw new RuntimeException('VALIDATION_ERROR: ไฟล์ต้องมีนามสกุล');
        }

        return $ext;
    }

    private function validateExtension(string $extension): void
    {
        if (in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            throw new RuntimeException("VALIDATION_ERROR: ไฟล์ประเภท .{$extension} ไม่อนุญาตให้อัปโหลด (อยู่ใน blocklist)");
        }

        // default-deny: ไม่อยู่ allowlist = reject เสมอ แม้ไม่ได้อยู่ blocklist ตรงๆ
        // (เช่น .json, .xml ไม่อยู่ทั้ง 2 รายการ แต่ต้อง reject เพราะไม่ใช่ allowlist)
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException(
                "VALIDATION_ERROR: ไฟล์ประเภท .{$extension} ไม่อยู่ในรายการที่อนุญาต "
                . '(' . implode(', ', self::ALLOWED_EXTENSIONS) . ')'
            );
        }
    }

    /**
     * MIME Sniffing -- เช็ค magic bytes จริงของไฟล์ผ่าน PHP finfo (ไม่เชื่อ extension
     * หรือ Content-Type header ที่ client ส่งมา เพราะปลอมแปลงได้ง่าย)
     */
    private function sniffMimeType(string $filePath): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new RuntimeException('เปิด fileinfo extension ไม่สำเร็จ -- ตรวจสอบว่า PHP ติดตั้ง ext-fileinfo แล้ว');
        }

        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        if ($mimeType === false) {
            throw new RuntimeException('ตรวจสอบ MIME type ของไฟล์ไม่สำเร็จ');
        }

        return $mimeType;
    }

    /**
     * ❗ Disguised File Protection (CTO Decision) -- extension และ MIME ต้องตรงกันทั้งคู่
     * ถ้าไม่ตรง = ปฏิเสธทันที ไม่ว่า extension จะอยู่ใน allowlist ก็ตาม
     */
    private function validateMimeMatchesExtension(string $extension, string $actualMimeType): void
    {
        $expectedMimes = self::EXPECTED_MIME_BY_EXTENSION[$extension] ?? [];

        if (!in_array($actualMimeType, $expectedMimes, true)) {
            throw new RuntimeException(
                "VALIDATION_ERROR: ไฟล์นี้ดูเหมือนถูกปลอมนามสกุล -- ระบุเป็น .{$extension} "
                . "แต่เนื้อหาไฟล์จริง (MIME sniffing) ตรวจพบเป็น '{$actualMimeType}' ซึ่งไม่ตรงกัน (Disguised File Protection)"
            );
        }
    }

    private function generateStoredFilename(string $extension): string
    {
        // ใช้ UUID เป็นชื่อไฟล์จริงบน disk เสมอ -- ไม่ใช้ original filename เด็ดขาด
        // ป้องกัน path traversal (เช่น "../../etc/passwd") โดยธรรมชาติ
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }

    private function buildRelativePath(int $workspaceId, string $storedFilename): string
    {
        return sprintf('%d/%s/%s', $workspaceId, date('Y/m'), $storedFilename);
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException("สร้าง directory ไม่สำเร็จ: {$directory}");
        }
    }
}
