<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Governance\GovernanceVersionService;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceVersionRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * GovernanceVersionServiceTest
 *
 * ครอบคลุมตาม Phase 1 Specification Package v0.1 หมวด 4.1 (State Machine Test Plan)
 * เน้น Test Case #2 (Auto-supersede) และ #5 (ไม่กระทบ record อื่น) เป็นพิเศษ
 * เพราะเป็น business rule ที่เพิ่งเปลี่ยนจาก manual เป็น auto ตาม CTO Decision
 *
 * ⚠️ ยังไม่ได้รันจริง (ไม่มี PHP/MySQL ในสิ่งแวดล้อมที่เขียนโค้ดนี้)
 */
final class GovernanceVersionServiceTest extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $userId;
    private GovernanceVersionService $service;
    private MySqlGovernanceVersionRepository $versionRepo;

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
        $this->versionRepo = new MySqlGovernanceVersionRepository($this->db, $this->workspaceId);
        $this->service = new GovernanceVersionService($this->db, $this->versionRepo);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testPublishFirstVersionSucceeds(): void
    {
        $recordId = $this->seedRecord($this->workspaceId, $this->userId);
        $v1 = $this->versionRepo->create($recordId, '1.0', 'เนื้อหา v1', $this->userId);

        $result = $this->service->publish($v1->id, $this->userId);

        $this->assertSame('published', $result['published']->status);
        $this->assertNull($result['superseded'], 'Publish version แรกของ record ต้องไม่มี version ใดถูก supersede');
    }

    public function testPublishSecondVersionAutoSupersedesFirst(): void
    {
        $recordId = $this->seedRecord($this->workspaceId, $this->userId);
        $v1 = $this->versionRepo->create($recordId, '1.0', 'v1', $this->userId);
        $this->service->publish($v1->id, $this->userId);

        $v2 = $this->versionRepo->create($recordId, '2.0', 'v2', $this->userId);
        $result = $this->service->publish($v2->id, $this->userId);

        $this->assertSame('published', $result['published']->status);
        $this->assertNotNull($result['superseded'], 'ต้องมี version ที่ถูก auto-supersede');
        $this->assertSame($v1->id, $result['superseded']->id);
        $this->assertSame('superseded', $result['superseded']->status);

        // ยืนยันซ้ำจาก DB ตรงๆ ไม่ใช่แค่เชื่อ return value
        $v1Fresh = $this->versionRepo->findById($v1->id);
        $this->assertSame('superseded', $v1Fresh->status);
    }

    public function testPublishTwiceThrowsError(): void
    {
        $recordId = $this->seedRecord($this->workspaceId, $this->userId);
        $v1 = $this->versionRepo->create($recordId, '1.0', 'v1', $this->userId);
        $this->service->publish($v1->id, $this->userId);

        $this->expectException(RuntimeException::class);
        $this->service->publish($v1->id, $this->userId); // publish ซ้ำ -- ต้อง error
    }

    public function testUpdateContentAfterPublishThrowsError(): void
    {
        $recordId = $this->seedRecord($this->workspaceId, $this->userId);
        $v1 = $this->versionRepo->create($recordId, '1.0', 'v1', $this->userId);
        $this->service->publish($v1->id, $this->userId);

        $this->expectException(RuntimeException::class);
        $this->service->updateContent($v1->id, 'เนื้อหาใหม่หลัง publish');
    }

    public function testAutoSupersedeDoesNotAffectOtherRecord(): void
    {
        $recordA = $this->seedRecord($this->workspaceId, $this->userId);
        $recordB = $this->seedRecord($this->workspaceId, $this->userId);

        $aV1 = $this->versionRepo->create($recordA, '1.0', 'A-v1', $this->userId);
        $this->service->publish($aV1->id, $this->userId);

        $bV1 = $this->versionRepo->create($recordB, '1.0', 'B-v1', $this->userId);
        $this->service->publish($bV1->id, $this->userId);

        // publish version ใหม่ของ record A เท่านั้น
        $aV2 = $this->versionRepo->create($recordA, '2.0', 'A-v2', $this->userId);
        $this->service->publish($aV2->id, $this->userId);

        $bV1Fresh = $this->versionRepo->findById($bV1->id);
        $this->assertSame('published', $bV1Fresh->status, 'Record B ต้องไม่ถูกกระทบจากการ publish ของ Record A');
    }

    // ===== Seed helpers =====

    private function seedUser(): int
    {
        $stmt = $this->db->prepare("INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES ('Test', :email, 'hash', 'active', 0)");
        $stmt->execute(['email' => 'govver-' . uniqid() . '@example.com']);
        return (int) $this->db->lastInsertId();
    }

    private function seedWorkspace(int $createdBy): int
    {
        $stmt = $this->db->prepare("INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, :code, 'active', :created_by)");
        $stmt->execute(['code' => 'WS-GOVVER-' . uniqid(), 'created_by' => $createdBy]);
        return (int) $this->db->lastInsertId();
    }

    private function seedRecord(int $workspaceId, int $ownerId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO governance_records (workspace_id, code, title, category, owner_user_id, status, created_by)
             VALUES (:ws, :code, 'Test Record', 'policy', :owner, 'active', :owner)"
        );
        $stmt->execute(['ws' => $workspaceId, 'code' => 'GOV-' . uniqid(), 'owner' => $ownerId]);
        return (int) $this->db->lastInsertId();
    }
}
