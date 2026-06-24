<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Persistence\MySQL\MySqlGovernanceVersionItemRepository;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceVersionRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Phase1WorkspaceScopingTest
 *
 * ตาม Phase 1 Specification Package v0.1 หมวด 5 -- เน้น GovernanceVersionItemRepository
 * ที่ join 2 ชั้น (item -> version -> record -> workspace) ซึ่งถูก flag ไว้ว่าเสี่ยง bug
 * สูงสุดของ Phase 1 ทั้งหมด
 *
 * ⚠️ ยังไม่ได้รันจริง -- ต้องทดสอบบน production-like server ตามมาตรฐานเดิม
 */
final class Phase1WorkspaceScopingTest extends TestCase
{
    private PDO $db;
    private int $workspaceA;
    private int $workspaceB;
    private int $userId;

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
        $this->workspaceA = $this->seedWorkspace($this->userId);
        $this->workspaceB = $this->seedWorkspace($this->userId);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    /**
     * Test หลัก: item ของ version ใน workspace B ต้อง "มองไม่เห็น" จาก repository
     * ที่ scope ด้วย workspace A แม้ id ตรงเป๊ะ -- ทดสอบ join เต็ม 2 ชั้น
     */
    public function testVersionItemFromOtherWorkspaceIsNotVisible(): void
    {
        $recordB = $this->seedRecord($this->workspaceB, $this->userId);
        $versionRepoB = new MySqlGovernanceVersionRepository($this->db, $this->workspaceB);
        $versionB = $versionRepoB->create($recordB, '1.0', 'content B', $this->userId);
        $itemRepoB = new MySqlGovernanceVersionItemRepository($this->db, $this->workspaceB);
        $itemB = $itemRepoB->create($versionB->id, 'GF-001', 'Item B', null, 1, $this->userId);

        // ใช้ repository ที่ scope ด้วย workspace A พยายามหา item ของ workspace B
        $itemRepoA = new MySqlGovernanceVersionItemRepository($this->db, $this->workspaceA);
        $result = $itemRepoA->findById($itemB->id);

        $this->assertNull($result, 'Item ของ workspace B ต้องมองไม่เห็นจาก repository ของ workspace A แม้ id ตรงเป๊ะ');
    }

    /**
     * Test เสริม: การ list items ของ version ที่อยู่ workspace อื่น ต้อง reject ตั้งแต่ระดับ
     * version (join ชั้นแรก) ไม่ปล่อยให้เข้าไป query item ชั้นที่ 2 เลย
     */
    public function testListItemsForVersionFromOtherWorkspaceThrows(): void
    {
        $recordB = $this->seedRecord($this->workspaceB, $this->userId);
        $versionRepoB = new MySqlGovernanceVersionRepository($this->db, $this->workspaceB);
        $versionB = $versionRepoB->create($recordB, '1.0', 'content B', $this->userId);

        $itemRepoA = new MySqlGovernanceVersionItemRepository($this->db, $this->workspaceA);

        $this->expectException(RuntimeException::class);
        $itemRepoA->listByVersion($versionB->id); // version นี้อยู่ workspace B ไม่ใช่ A
    }

    /**
     * Test เสริม: สร้าง item ลงใน version ของ workspace อื่นต้องไม่ได้
     */
    public function testCreateItemOnVersionFromOtherWorkspaceThrows(): void
    {
        $recordB = $this->seedRecord($this->workspaceB, $this->userId);
        $versionRepoB = new MySqlGovernanceVersionRepository($this->db, $this->workspaceB);
        $versionB = $versionRepoB->create($recordB, '1.0', 'content B', $this->userId);

        $itemRepoA = new MySqlGovernanceVersionItemRepository($this->db, $this->workspaceA);

        $this->expectException(RuntimeException::class);
        $itemRepoA->create($versionB->id, 'GF-999', 'Sneaky Item', null, 1, $this->userId);
    }

    /**
     * Test ที่ code/title ซ้ำกันข้าม workspace ไม่ทำให้ query ปนกัน
     * (เคสคลาสสิกจาก Phase 0: ข้อมูลคล้ายกันข้าม workspace ต้องแยกออกจากกันสนิท)
     */
    public function testIdenticalItemCodeAcrossWorkspacesDoesNotCollide(): void
    {
        $recordA = $this->seedRecord($this->workspaceA, $this->userId);
        $versionRepoA = new MySqlGovernanceVersionRepository($this->db, $this->workspaceA);
        $versionA = $versionRepoA->create($recordA, '1.0', 'content A', $this->userId);
        $itemRepoA = new MySqlGovernanceVersionItemRepository($this->db, $this->workspaceA);
        $itemA = $itemRepoA->create($versionA->id, 'GF-001', 'Item A', null, 1, $this->userId); // code เดียวกับ B

        $recordB = $this->seedRecord($this->workspaceB, $this->userId);
        $versionRepoB = new MySqlGovernanceVersionRepository($this->db, $this->workspaceB);
        $versionB = $versionRepoB->create($recordB, '1.0', 'content B', $this->userId);
        $itemRepoB = new MySqlGovernanceVersionItemRepository($this->db, $this->workspaceB);
        $itemB = $itemRepoB->create($versionB->id, 'GF-001', 'Item B', null, 1, $this->userId); // code ซ้ำตั้งใจ

        $listA = $itemRepoA->listByVersion($versionA->id);
        $this->assertCount(1, $listA);
        $this->assertSame('Item A', $listA[0]->title, 'workspace A ต้องเห็นแค่ item ของตัวเอง แม้ code ซ้ำกับ workspace B');
    }

    private function seedUser(): int
    {
        $stmt = $this->db->prepare("INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES ('Test', :email, 'hash', 'active', 0)");
        $stmt->execute(['email' => 'scoping-' . uniqid() . '@example.com']);
        return (int) $this->db->lastInsertId();
    }

    private function seedWorkspace(int $createdBy): int
    {
        $stmt = $this->db->prepare("INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, :code, 'active', :created_by)");
        $stmt->execute(['code' => 'WS-SCOPE-' . uniqid(), 'created_by' => $createdBy]);
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
