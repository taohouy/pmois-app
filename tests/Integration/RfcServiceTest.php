<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Decision\DecisionRegisterRepositoryInterface;
use App\Domain\Rfc\RfcService;
use App\Infrastructure\Persistence\MySQL\MySqlDecisionRegisterRepository;
use App\Infrastructure\Persistence\MySQL\MySqlRfcRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * RfcServiceTest
 *
 * ครอบคลุมตาม Phase 1 Specification Package v0.1 หมวด 4.2
 * เน้น Test Case #2 (Segregation of Duties) เป็นพิเศษ -- กฎที่ CTO ย้ำหลายรอบ
 */
final class RfcServiceTest extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private MySqlRfcRepository $rfcRepo;
    private DecisionRegisterRepositoryInterface $decisionRepo;
    private RfcService $service;

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $creatorId = $this->seedUser();
        $this->workspaceId = $this->seedWorkspace($creatorId);
        $this->rfcRepo = new MySqlRfcRepository($this->db, $this->workspaceId);
        $this->decisionRepo = new MySqlDecisionRegisterRepository($this->db, $this->workspaceId);
        $this->service = new RfcService($this->rfcRepo, $this->decisionRepo);
        $this->creatorId = $creatorId;
    }

    private int $creatorId;

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testSubmitChangesDraftToUnderReview(): void
    {
        $rfc = $this->rfcRepo->create('RFC-001', 'Test RFC', 'desc', null, null, $this->creatorId);
        $result = $this->service->submit($rfc->id);

        $this->assertSame('under_review', $result->status);
    }

    public function testSegregationOfDutiesBlocksCreatorFromReviewingOwnRfc(): void
    {
        $rfc = $this->rfcRepo->create('RFC-002', 'Test RFC', 'desc', null, null, $this->creatorId);
        $this->service->submit($rfc->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/FORBIDDEN/');
        $this->service->review($rfc->id, $this->creatorId, 'approved', 'self-review'); // creator == reviewer
    }

    public function testReviewByDifferentUserSucceeds(): void
    {
        $reviewerId = $this->seedUser();
        $rfc = $this->rfcRepo->create('RFC-003', 'Test RFC', 'desc', null, null, $this->creatorId);
        $this->service->submit($rfc->id);

        $result = $this->service->review($rfc->id, $reviewerId, 'approved', 'looks good');

        $this->assertSame('approved', $result->status);
    }

    public function testConvertBeforeApprovedThrowsError(): void
    {
        $rfc = $this->rfcRepo->create('RFC-004', 'Test RFC', 'desc', null, null, $this->creatorId);
        $this->service->submit($rfc->id); // ยังเป็น under_review ไม่ใช่ approved

        $this->expectException(RuntimeException::class);
        $this->service->convertToDecision($rfc->id, $this->creatorId);
    }

    public function testConvertToDecisionStartsAtApprovedStatusNotProposed(): void
    {
        $reviewerId = $this->seedUser();
        $rfc = $this->rfcRepo->create('RFC-005', 'Test RFC', 'desc', null, null, $this->creatorId);
        $this->service->submit($rfc->id);
        $this->service->review($rfc->id, $reviewerId, 'approved', 'ok');

        $result = $this->service->convertToDecision($rfc->id, $reviewerId);

        $this->assertSame('converted_to_decision', $result->status);
        $this->assertNotNull($result->resultingDecisionId);

        $decision = $this->decisionRepo->findById($result->resultingDecisionId);
        $this->assertSame('approved', $decision->status, 'Decision จาก RFC ต้องเริ่มที่ approved ตรง ไม่ใช่ proposed');
        $this->assertSame('governance', $decision->category, 'ถ้าไม่ระบุ category ตอน convert ต้อง default เป็น governance');
    }

    public function testConvertTwiceThrowsError(): void
    {
        $reviewerId = $this->seedUser();
        $rfc = $this->rfcRepo->create('RFC-006', 'Test RFC', 'desc', null, null, $this->creatorId);
        $this->service->submit($rfc->id);
        $this->service->review($rfc->id, $reviewerId, 'approved', 'ok');
        $this->service->convertToDecision($rfc->id, $reviewerId);

        $this->expectException(RuntimeException::class);
        $this->service->convertToDecision($rfc->id, $reviewerId); // convert ซ้ำ
    }

    private function seedUser(): int
    {
        $stmt = $this->db->prepare("INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES ('Test', :email, 'hash', 'active', 0)");
        $stmt->execute(['email' => 'rfc-' . uniqid() . '@example.com']);
        return (int) $this->db->lastInsertId();
    }

    private function seedWorkspace(int $createdBy): int
    {
        $stmt = $this->db->prepare("INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, :code, 'active', :created_by)");
        $stmt->execute(['code' => 'WS-RFC-' . uniqid(), 'created_by' => $createdBy]);
        return (int) $this->db->lastInsertId();
    }
}
