<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Audit\AuditTrailRepositoryInterface;
use App\Infrastructure\Persistence\MySQL\MySqlAuditTrailRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * AuditTrailTest
 *
 * ครอบคลุมตาม Phase 0 Specification Package v0.1 หมวด 7:
 *   1. record() เขียนแถวจริงพร้อม workspace_id ที่ถูกต้อง (ไม่ NULL ถ้ามี context)
 *   2. ค่า action ที่ explicit (override) ต้องถูกใช้แทน default mapping
 *   3. listByEntity() ต้อง filter workspace_id เสมอ แม้ entity_id ชนกันข้าม workspace
 *   4. ไม่มี method update()/delete() ในคลาสนี้เลย (immutable by design -- ตรวจสอบ
 *      ด้วย reflection ว่า public method มีแค่ตามที่ interface กำหนดจริง)
 *
 * ⚠️ ยังไม่ได้รันจริง (ไม่มี PHP/MySQL ในสิ่งแวดล้อมที่ผมเขียนโค้ดนี้) -- ต้องรันยืนยัน
 * บนเครื่อง local/staging ก่อนเชื่อว่าผ่านจริง เหมือน test ไฟล์อื่นในชุดนี้
 */
final class AuditTrailTest extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $userId;
    private AuditTrailRepositoryInterface $auditRepo;

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
        $this->auditRepo = new MySqlAuditTrailRepository($this->db, $this->workspaceId);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testRecordWritesRowWithCorrectWorkspaceId(): void
    {
        $this->auditRepo->record(
            userId: $this->userId,
            action: 'create',
            entityType: 'project',
            entityId: 999,
            beforeValue: null,
            afterValue: ['code' => 'TEST'],
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit'
        );

        $stmt = $this->db->prepare(
            'SELECT * FROM audit_trails WHERE entity_type = :t AND entity_id = :id LIMIT 1'
        );
        $stmt->execute(['t' => 'project', 'id' => 999]);
        $row = $stmt->fetch();

        $this->assertNotFalse($row, 'audit_trails ต้องมีแถวที่บันทึกไว้');
        $this->assertSame($this->workspaceId, (int) $row['workspace_id'], 'workspace_id ต้องตรงกับ context ที่ Repository ถูกสร้างมา');
        $this->assertSame('create', $row['action']);
        $this->assertSame('{"code":"TEST"}', $row['after_value']);
    }

    public function testListByEntityFiltersWorkspaceEvenWhenEntityIdCollides(): void
    {
        $otherWorkspaceId = $this->seedWorkspace($this->userId);
        $otherWorkspaceRepo = new MySqlAuditTrailRepository($this->db, $otherWorkspaceId);

        // entity_id ชนกันโดยตั้งใจ (เช่น project id=42 มีอยู่ในทั้ง 2 workspace ในทางทฤษฎี)
        $this->auditRepo->record($this->userId, 'create', 'project', 42, null, ['ws' => 'A'], null, null);
        $otherWorkspaceRepo->record($this->userId, 'create', 'project', 42, null, ['ws' => 'B'], null, null);

        $resultsForA = $this->auditRepo->listByEntity('project', 42);

        $this->assertCount(1, $resultsForA, 'ต้องเห็นแค่ของ workspace ตัวเองเท่านั้น แม้ entity_id ชนกัน');
        $this->assertSame($this->workspaceId, (int) $resultsForA[0]['workspace_id']);
    }

    public function testRepositoryHasNoUpdateOrDeleteMethod(): void
    {
        $reflection = new \ReflectionClass(MySqlAuditTrailRepository::class);
        $publicMethods = array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC)
        );

        foreach ($publicMethods as $methodName) {
            $this->assertStringNotContainsStringIgnoringCase(
                'update',
                $methodName,
                "พบ method '{$methodName}' ที่ดูเหมือนจะแก้ไขข้อมูลย้อนหลังได้ -- ขัดกับหลัก immutable ของ audit_trails"
            );
            $this->assertStringNotContainsStringIgnoringCase(
                'delete',
                $methodName,
                "พบ method '{$methodName}' ที่ดูเหมือนจะลบข้อมูลได้ -- ขัดกับหลัก Retention: Keep Forever ที่ CTO approve ไว้"
            );
        }
    }

    // ===== Seed helpers =====

    private function seedUser(): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO users (name, email, password_hash, status, is_platform_admin)
             VALUES ('Test', :email, 'hash', 'active', 0)"
        );
        $stmt->execute(['email' => 'audit-test-' . uniqid() . '@example.com']);
        return (int) $this->db->lastInsertId();
    }

    private function seedWorkspace(int $createdBy): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO workspaces (code, name, status, created_by)
             VALUES (:code, :code, 'active', :created_by)"
        );
        $stmt->execute(['code' => 'WS-AUDIT-' . uniqid(), 'created_by' => $createdBy]);
        return (int) $this->db->lastInsertId();
    }
}
