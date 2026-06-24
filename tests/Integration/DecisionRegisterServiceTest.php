<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Decision\DecisionRegisterService;
use App\Infrastructure\Persistence\MySQL\MySqlDecisionRegisterRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * DecisionRegisterServiceTest
 *
 * ครอบคลุมตาม Phase 1 Specification Package v0.1 หมวด 4.3
 * เน้น Category เป็น required field เมื่อสร้างตรง (CTO Decision -- ห้าม auto-default เป็น 'other')
 */
final class DecisionRegisterServiceTest extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $userId;
    private DecisionRegisterService $service;

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
        $repo = new MySqlDecisionRegisterRepository($this->db, $this->workspaceId);
        $this->service = new DecisionRegisterService($repo);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testCreateDirectWithoutCategoryThrowsValidationError(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/VALIDATION_ERROR/');

        $this->service->createDirect(
            category: null, // ❗ ไม่ส่ง category มา
            title: 'Test Decision',
            context: null,
            decisionDescription: 'desc',
            decisionDate: date('Y-m-d'),
            projectId: null,
            relatedGovernanceRecordId: null,
            decidedByUserId: $this->userId,
            createdByUserId: $this->userId
        );
    }

    public function testCreateDirectDoesNotAutoDefaultToOther(): void
    {
        // ทดสอบซ้ำเพื่อยืนยันชัดๆ ว่า "ไม่มี category" ไม่ได้แปลว่า "category=other"
        // แต่ต้อง throw error เท่านั้น (ตาม CTO Decision ที่ระบุห้าม auto-default)
        try {
            $this->service->createDirect(null, 'Test', null, 'desc', date('Y-m-d'), null, null, $this->userId, $this->userId);
            $this->fail('ควร throw exception เมื่อไม่ระบุ category');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('VALIDATION_ERROR', $e->getMessage());
        }
    }

    public function testCreateDirectWithInvalidCategoryThrowsError(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->createDirect(
            category: 'not_a_real_category',
            title: 'Test', context: null, decisionDescription: 'desc', decisionDate: date('Y-m-d'),
            projectId: null, relatedGovernanceRecordId: null, decidedByUserId: $this->userId, createdByUserId: $this->userId
        );
    }

    public function testCreateDirectWithValidCategorySucceeds(): void
    {
        $decision = $this->service->createDirect(
            category: 'architecture', // ค่าใหม่ที่เพิ่มตาม CTO Decision
            title: 'Test Decision', context: null, decisionDescription: 'desc', decisionDate: date('Y-m-d'),
            projectId: null, relatedGovernanceRecordId: null, decidedByUserId: $this->userId, createdByUserId: $this->userId
        );

        $this->assertSame('architecture', $decision->category);
        $this->assertSame('proposed', $decision->status, 'สร้างตรงต้องเริ่มที่ proposed เสมอ');
    }

    public function testApproveChangesStatusFromProposedToApproved(): void
    {
        $decision = $this->service->createDirect(
            'technical', 'Test', null, 'desc', date('Y-m-d'), null, null, $this->userId, $this->userId
        );

        $result = $this->service->approve($decision->id);

        $this->assertSame('approved', $result->status);
    }

    public function testApproveTwiceThrowsError(): void
    {
        $decision = $this->service->createDirect(
            'technical', 'Test', null, 'desc', date('Y-m-d'), null, null, $this->userId, $this->userId
        );
        $this->service->approve($decision->id);

        $this->expectException(RuntimeException::class);
        $this->service->approve($decision->id); // approve ซ้ำ -- ไม่ใช่ proposed แล้ว
    }

    private function seedUser(): int
    {
        $stmt = $this->db->prepare("INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES ('Test', :email, 'hash', 'active', 0)");
        $stmt->execute(['email' => 'decision-' . uniqid() . '@example.com']);
        return (int) $this->db->lastInsertId();
    }

    private function seedWorkspace(int $createdBy): int
    {
        $stmt = $this->db->prepare("INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, :code, 'active', :created_by)");
        $stmt->execute(['code' => 'WS-DEC-' . uniqid(), 'created_by' => $createdBy]);
        return (int) $this->db->lastInsertId();
    }
}
