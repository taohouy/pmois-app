<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Persistence\MySQL\MySqlProjectMemberRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * WorkspaceScopingTest
 *
 * ⚠️ Mandatory ตามที่ CTO ย้ำซ้ำหลายรอบว่า "Workspace Data Leak Tests remain mandatory"
 * และ Risk Assessment ประเมินว่าเป็นความเสี่ยงที่ผลกระทบสูงมาก
 *
 * ⚠️ ข้อจำกัดของไฟล์นี้: เขียนตาม PHPUnit convention ที่ถูกต้อง แต่ "ยังไม่ได้รันจริง"
 * เพราะสิ่งแวดล้อมนี้ไม่มี PHP/MySQL ให้ทดสอบ ต้องตั้งค่า TEST_DB_* ใน .env.testing
 * แล้วรันด้วย `vendor/bin/phpunit` บนเครื่อง local ก่อนเชื่อว่าผ่านจริง
 *
 * แนวทางการทดสอบ: สร้าง 2 workspace (A, B) ที่มีข้อมูลคล้ายกัน (เช่น project code ซ้ำกัน
 * คนละ workspace) แล้วยืนยันว่า Repository ที่ scope ด้วย workspace A มองไม่เห็น/แก้ไม่ได้
 * ข้อมูลของ workspace B แม้จะส่ง id ของ B มาตรงๆ ก็ตาม
 */
final class WorkspaceScopingTest extends TestCase
{
    private PDO $db;
    private int $workspaceAId;
    private int $workspaceBId;
    private int $projectInWorkspaceBId;

    protected function setUp(): void
    {
        // 🔴 ต้องตั้งค่าจริงตอน implement -- ใช้ฐานข้อมูลทดสอบแยกจาก dev/prod เสมอ
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );

        $this->db->beginTransaction(); // rollback ทุก test เพื่อไม่ทิ้งข้อมูลค้าง

        $userId = $this->seedUser();
        $this->workspaceAId = $this->seedWorkspace('WS-A-' . uniqid(), $userId);
        $this->workspaceBId = $this->seedWorkspace('WS-B-' . uniqid(), $userId);

        $this->projectInWorkspaceBId = $this->seedProject($this->workspaceBId, 'SHARED-CODE', $userId);
        // ตั้งใจให้ workspace A มี project code เดียวกัน เพื่อเทสว่าจะไม่ "ชน" หรือ "เห็นปนกัน"
        $this->seedProject($this->workspaceAId, 'SHARED-CODE', $userId);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    /**
     * Test หลัก: Repository ที่ scope ด้วย workspace A ต้อง "ไม่เห็น" project ของ workspace B
     * แม้จะส่ง id ของ project ใน workspace B ตรงๆ ก็ตาม
     */
    public function testProjectRepositoryCannotSeeOtherWorkspaceData(): void
    {
        $repoScopedToA = new MySqlProjectRepository($this->db, $this->workspaceAId);

        $result = $repoScopedToA->findById($this->projectInWorkspaceBId);

        $this->assertNull(
            $result,
            'Repository ที่ scope ด้วย workspace A ต้องไม่เห็น project ของ workspace B — '
            . 'ถ้า assertion นี้ fail แสดงว่ามี workspace data leak เกิดขึ้นจริง'
        );
    }

    /**
     * Test เสริม: ฟังก์ชัน updateStatus ก็ต้องแก้ข้าม workspace ไม่ได้เช่นกัน (ไม่ใช่แค่ read)
     */
    public function testProjectRepositoryCannotUpdateOtherWorkspaceData(): void
    {
        $repoScopedToA = new MySqlProjectRepository($this->db, $this->workspaceAId);

        $updated = $repoScopedToA->updateStatus($this->projectInWorkspaceBId, 'closed');

        $this->assertFalse(
            $updated,
            'ห้ามแก้ไข project ของ workspace อื่นได้ แม้จะรู้ id ตรงๆ'
        );

        // ยืนยันว่าข้อมูลจริงใน workspace B ไม่ถูกแก้ไป
        $stmt = $this->db->prepare('SELECT status FROM projects WHERE id = :id');
        $stmt->execute(['id' => $this->projectInWorkspaceBId]);
        $row = $stmt->fetch();

        $this->assertSame('planning', $row['status'], 'Status เดิมต้องไม่ถูกเปลี่ยนจาก workspace อื่น');
    }

    /**
     * Test สำหรับ pattern ที่ scope ทางอ้อมผ่าน join (ProjectMemberRepository)
     * เพราะเป็นจุดที่เสี่ยง bug มากที่สุดตามที่ระบุไว้ใน Repository Specification v0.1 หมวด 5.2
     */
    public function testProjectMemberRepositoryRejectsActionOnProjectFromOtherWorkspace(): void
    {
        $repoScopedToA = new MySqlProjectMemberRepository($this->db, $this->workspaceAId);

        $this->expectException(RuntimeException::class);

        // ลองเพิ่ม member เข้า project ของ workspace B โดยใช้ context ของ workspace A
        $repoScopedToA->addMember($this->projectInWorkspaceBId, 1, 1);
    }

    // ===== Seed helpers =====

    private function seedUser(): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO users (name, email, password_hash, status, is_platform_admin)
             VALUES ('Test User', :email, 'hash', 'active', 0)"
        );
        $stmt->execute(['email' => 'test-' . uniqid() . '@example.com']);
        return (int) $this->db->lastInsertId();
    }

    private function seedWorkspace(string $code, int $createdBy): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO workspaces (code, name, status, created_by)
             VALUES (:code, :code, 'active', :created_by)"
        );
        $stmt->execute(['code' => $code, 'created_by' => $createdBy]);
        return (int) $this->db->lastInsertId();
    }

    private function seedProject(int $workspaceId, string $code, int $ownerId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO projects (workspace_id, code, name, status, owner_user_id)
             VALUES (:workspace_id, :code, :code, 'planning', :owner_id)"
        );
        $stmt->execute(['workspace_id' => $workspaceId, 'code' => $code, 'owner_id' => $ownerId]);
        return (int) $this->db->lastInsertId();
    }
}
