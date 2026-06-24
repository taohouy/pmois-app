<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Knowledge\AttachmentService;
use App\Infrastructure\Persistence\MySQL\MySqlAttachmentRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * AttachmentServiceTest
 *
 * ครอบคลุมตาม Phase 2 Specification Package -- Attachment Security Validation Plan
 * เน้น Test Case #5 (Disguised File) เป็นพิเศษ เพราะเป็น CTO Decision ที่เพิ่มมาเฉพาะ
 *
 * ⚠️ ยังไม่ได้รันจริง -- ต้องมีไฟล์ทดสอบจริงบนเครื่อง (ไม่ได้ mock filesystem)
 */
final class AttachmentServiceTest extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $userId;
    private AttachmentService $service;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $this->userId = $this->seedUser();
        $this->workspaceId = $this->seedWorkspace($this->userId);
        $this->tmpDir = sys_get_temp_dir() . '/pmois_test_storage_' . uniqid();
        mkdir($this->tmpDir, 0750, true);

        $repo = new MySqlAttachmentRepository($this->db, $this->workspaceId);
        $this->service = new AttachmentService($repo, $this->tmpDir);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        $this->removeDirectory($this->tmpDir);
    }

    public function testUploadFileExceedingMaxSizeIsRejected(): void
    {
        $tmpFile = $this->createTempFile('large.pdf', '%PDF-1.4' . str_repeat('A', 100));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/VALIDATION_ERROR.*50 MB/');

        // จำลองว่า client ส่ง size มา 51MB (เกินจริงตั้งใจ ไม่ต้องสร้างไฟล์ใหญ่จริงเพื่อความเร็วของ test)
        $this->service->upload('large.pdf', $tmpFile, 51 * 1024 * 1024, $this->userId, $this->workspaceId);
    }

    public function testUploadAllowedExtensionWithMatchingMimeSucceeds(): void
    {
        // PDF magic bytes จริง: "%PDF-"
        $tmpFile = $this->createTempFile('valid.pdf', "%PDF-1.4\n%real pdf content");

        $attachment = $this->service->upload('valid.pdf', $tmpFile, filesize($tmpFile), $this->userId, $this->workspaceId);

        $this->assertSame('valid.pdf', $attachment->originalName);
        $this->assertStringContainsString('pdf', $attachment->mimeType);
    }

    public function testUploadBlockedExtensionIsRejectedImmediately(): void
    {
        // หมายเหตุ: ไม่ใส่ PE header magic bytes จริงเพราะ blocklist เช็คจาก extension
        // ล้วนๆ (ก่อนถึงขั้น MIME sniffing ด้วยซ้ำ) เนื้อหาไฟล์จึงไม่มีผลต่อผลทดสอบนี้
        // -- ใช้ plain text แทนเพื่อไม่ให้ antivirus scanner ตรวจจับเป็น false positive
        $tmpFile = $this->createTempFile('malware.exe', 'plain text content, not a real executable');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/blocklist/');

        $this->service->upload('malware.exe', $tmpFile, filesize($tmpFile), $this->userId, $this->workspaceId);
    }

    public function testUploadExtensionNotInAllowlistOrBlocklistIsRejectedByDefault(): void
    {
        $tmpFile = $this->createTempFile('data.json', '{"key":"value"}');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/VALIDATION_ERROR/');

        $this->service->upload('data.json', $tmpFile, filesize($tmpFile), $this->userId, $this->workspaceId);
    }

    /**
     * ❗ Test Case สำคัญที่สุด -- Disguised File Protection (CTO Decision)
     * ไฟล์ที่แท้จริงเป็น plain text แต่เปลี่ยนนามสกุลเป็น .pdf -- ต้อง reject
     * เพราะ MIME sniffing จะเจอว่าเนื้อหาจริงไม่ใช่ PDF
     *
     * หมายเหตุ: ใช้ plain text ธรรมดาแทนเนื้อหาที่ดูเหมือน script อันตราย (เคยใช้ก่อนหน้า)
     * เพราะ pattern ที่มีลักษณะคล้าย backdoor script ตรงกับ signature ที่ antivirus
     * scanner ใช้ตรวจ malware จริง ทำให้ตัว test file เองถูก flag เป็น false positive
     * ตอนแตกไฟล์ zip บนเครื่อง local -- plain text ก็พิสูจน์ MIME mismatch ได้เหมือนกัน
     * เพราะ finfo จะ sniff เป็น text/plain ซึ่งไม่ตรงกับ application/pdf ที่ extension อ้างอยู่ดี
     */
    public function testDisguisedFileIsRejectedEvenWithAllowedExtension(): void
    {
        // เนื้อหาจริงเป็น plain text ธรรมดา แต่ตั้งชื่อไฟล์เป็น .pdf (พยายามหลอกผ่าน extension check)
        $tmpFile = $this->createTempFile('disguised.pdf', 'This is just plain text content, not actually a PDF document at all.');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Disguised File Protection|ปลอมนามสกุล/');

        $this->service->upload('disguised.pdf', $tmpFile, filesize($tmpFile), $this->userId, $this->workspaceId);
    }

    public function testUploadedFileIsStoredWithUuidNotOriginalName(): void
    {
        $tmpFile = $this->createTempFile('../../../etc/passwd.txt', 'plain text content');

        $attachment = $this->service->upload('../../../etc/passwd.txt', $tmpFile, filesize($tmpFile), $this->userId, $this->workspaceId);

        // original_name เก็บ metadata ตามที่ส่งมา แต่ stored_path ต้องไม่มี path traversal
        $this->assertStringNotContainsString('..', $attachment->storedPath);
        $this->assertStringNotContainsString('etc/passwd', $attachment->storedPath);
    }

    public function testSoftDeleteHidesAttachmentButKeepsFileOnDisk(): void
    {
        $tmpFile = $this->createTempFile('test.txt', 'plain text content');
        $attachment = $this->service->upload('test.txt', $tmpFile, filesize($tmpFile), $this->userId, $this->workspaceId);

        $absolutePath = $this->tmpDir . '/' . $attachment->storedPath;
        $this->assertFileExists($absolutePath, 'ไฟล์ต้องถูกบันทึกจริงบน disk ก่อน delete');

        $deleted = $this->service->delete($attachment->id);

        $this->assertTrue($deleted);
        $this->assertFileExists($absolutePath, 'Soft delete ต้องไม่ลบไฟล์จริงบน disk');
    }

    public function testChecksumIsCalculatedCorrectly(): void
    {
        $content = 'known content for checksum test';
        $tmpFile = $this->createTempFile('checksum.txt', $content);

        $attachment = $this->service->upload('checksum.txt', $tmpFile, filesize($tmpFile), $this->userId, $this->workspaceId);

        $this->assertSame(hash('sha256', $content), $attachment->checksum);
    }

    // ===== Helpers =====

    private function createTempFile(string $originalName, string $content): string
    {
        $path = $this->tmpDir . '/src_' . uniqid() . '_' . basename($originalName);
        file_put_contents($path, $content);
        return $path;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function seedUser(): int
    {
        $stmt = $this->db->prepare("INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES ('Test', :email, 'hash', 'active', 0)");
        $stmt->execute(['email' => 'attach-' . uniqid() . '@example.com']);
        return (int) $this->db->lastInsertId();
    }

    private function seedWorkspace(int $createdBy): int
    {
        $stmt = $this->db->prepare("INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, :code, 'active', :created_by)");
        $stmt->execute(['code' => 'WS-ATTACH-' . uniqid(), 'created_by' => $createdBy]);
        return (int) $this->db->lastInsertId();
    }
}
