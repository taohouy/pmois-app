<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Governance\GovernanceAdoptionRepositoryInterface;
use App\Domain\Governance\GovernancePolicyService;
use App\Domain\Governance\GovernanceVersionService;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceAdoptionRepository;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceRecordRepository;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceVersionRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * GovernanceM4Test — M4 PMO Governance
 *
 * ครอบคลุม: Working Instructions (audience), Policies (review/delivery/approval),
 * validation, filters และกฎสำคัญ: "ปรับปรุง Governance ในอนาคตโดยไม่กระทบ Project เดิม"
 * (publish version ใหม่ → supersede เดิม → adoption ของ project ยังชี้ version เดิม)
 * รันบน DB ที่ migrate ครบ (รวม 0060)
 */
final class GovernanceM4Test extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $userId;
    private int $projectId;
    private GovernancePolicyService $policyService;
    private GovernanceVersionService $versionService;
    private GovernanceAdoptionRepositoryInterface $adoptionRepo;

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $this->userId = $this->insertUser('gov-admin');
        $stmt = $this->db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, "Gov Test WS", "active", :user)');
        $stmt->execute(['code' => 'gov-ws-' . uniqid(), 'user' => $this->userId]);
        $this->workspaceId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare('INSERT INTO projects (workspace_id, code, name, status, development_mode, owner_user_id) VALUES (:ws, "GOV-1", "Gov Test Project", "active", "manual", :user)');
        $stmt->execute(['ws' => $this->workspaceId, 'user' => $this->userId]);
        $this->projectId = (int) $this->db->lastInsertId();

        $recordRepo = new MySqlGovernanceRecordRepository($this->db, $this->workspaceId);
        $this->policyService = new GovernancePolicyService($recordRepo);
        $this->versionService = new GovernanceVersionService($this->db, new MySqlGovernanceVersionRepository($this->db, $this->workspaceId));
        $this->adoptionRepo = new MySqlGovernanceAdoptionRepository($this->db, $this->workspaceId);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    // ===== Working Instruction Management (CTO/Dev) =====

    public function testCreateCtoWorkingInstructionAndList(): void
    {
        $record = $this->policyService->createTemplate(
            'WI-CTO-001', 'CTO Review Checklist', 'guideline', null,
            $this->userId, $this->userId, 'cto', null
        );

        $this->assertSame('cto', $record->audience);
        $instructions = $this->policyService->listWorkingInstructions('cto');
        $this->assertCount(1, $instructions);
        $this->assertSame('WI-CTO-001', $instructions[0]->code);
    }

    public function testAudienceOnNonGuidelineRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('require category="guideline"');

        $this->policyService->createTemplate('WI-BAD', 'Bad', 'standard', null, $this->userId, $this->userId, 'cto', null);
    }

    // ===== Review / Delivery / Approval Policy =====

    public function testCreatePoliciesAndListByType(): void
    {
        $this->policyService->createTemplate('POL-REV-001', 'Review Policy', 'policy', null, $this->userId, $this->userId, null, 'review');
        $this->policyService->createTemplate('POL-DEL-001', 'Delivery Policy', 'policy', null, $this->userId, $this->userId, null, 'delivery');
        $this->policyService->createTemplate('POL-APP-001', 'Approval Policy', 'policy', null, $this->userId, $this->userId, null, 'approval');

        $review = $this->policyService->listPolicies('review');
        $this->assertCount(1, $review);
        $this->assertSame('POL-REV-001', $review[0]->code);

        $all = $this->policyService->listPolicies(null);
        $this->assertCount(3, $all);
    }

    public function testInvalidPolicyTypeRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('policy_type must be one of');

        $this->policyService->createTemplate('POL-BAD', 'Bad', 'policy', null, $this->userId, $this->userId, null, 'security');
    }

    public function testPolicyTypeOnNonPolicyCategoryRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('only allowed with category="policy"');

        $this->policyService->createTemplate('STD-BAD', 'Bad', 'standard', null, $this->userId, $this->userId, null, 'review');
    }

    // ===== Filters =====

    public function testFilteredListing(): void
    {
        $this->policyService->createTemplate('WI-DEV-001', 'Dev Instructions', 'guideline', null, $this->userId, $this->userId, 'dev', null);
        $this->policyService->createTemplate('STD-001', 'Plain Standard', 'standard', null, $this->userId, $this->userId, null, null);

        $repo = new MySqlGovernanceRecordRepository($this->db, $this->workspaceId);
        $devOnly = $repo->listByWorkspaceFiltered('dev', null, null);
        $this->assertCount(1, $devOnly);
        $this->assertSame('WI-DEV-001', $devOnly[0]->code);

        $standards = $repo->listByWorkspaceFiltered(null, null, 'standard');
        $this->assertCount(1, $standards);
    }

    // ===== ปรับปรุง Governance ในอนาคต โดยไม่กระทบ Project เดิม =====

    public function testNewVersionPublishDoesNotAffectExistingProjectAdoption(): void
    {
        // 1. สร้าง template + v1 + publish (การสร้าง version ใช้ repository ตรง — publish ผ่าน service)
        $record = $this->policyService->createTemplate('POL-REV-100', 'Review Policy', 'policy', null, $this->userId, $this->userId, null, 'review');
        $versionRepo = new MySqlGovernanceVersionRepository($this->db, $this->workspaceId);
        $v1 = $versionRepo->create($record->id, '1.0', 'rules v1', $this->userId);
        $this->versionService->publish($v1->id, $this->userId);

        // 2. project adopt v1 (governance binding)
        $adoption = $this->adoptionRepo->create($this->projectId, $v1->id, $this->userId);
        $this->assertSame($v1->id, $adoption->governanceVersionId);

        // 3. อนาคต: publish v2 → auto-supersede v1
        $v2 = $versionRepo->create($record->id, '2.0', 'rules v2 (updated)', $this->userId);
        $result = $this->versionService->publish($v2->id, $this->userId);

        $this->assertSame('superseded', $result['superseded']->status);
        $this->assertSame('published', $result['published']->status);

        // 4. adoption เดิมของ project ยังชี้ v1 — ไม่ถูกเปลี่ยนอัตโนมัติ (project เดิมไม่กระทบ)
        $adoptions = $this->adoptionRepo->listByProject($this->projectId);
        $this->assertCount(1, $adoptions);
        $this->assertSame($v1->id, $adoptions[0]->governanceVersionId, 'project เดิมต้องยังใช้ version ที่ adopt ไว้');
        $this->assertSame('active', $adoptions[0]->status);

        // 5. project ต้องการ version ใหม่ = bind เพิ่ม explicitly (ผ่าน API เดิม)
        $this->adoptionRepo->create($this->projectId, $v2->id, $this->userId);
        $this->assertCount(2, $this->adoptionRepo->listByProject($this->projectId));
    }

    private function insertUser(string $name): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES (:name, :email, NULL, "active", 0)');
        $stmt->execute(['name' => $name, 'email' => $name . uniqid() . '@test.local']);

        return (int) $this->db->lastInsertId();
    }
}
