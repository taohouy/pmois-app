<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Ai\AiContextAggregationService;
use App\Infrastructure\Persistence\MySQL\MySqlAiContextExportRepository;
use App\Infrastructure\Persistence\MySQL\MySqlDecisionRegisterRepository;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceAdoptionRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectStatusUpdateRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * ContextExportVerificationTest
 *
 * ครอบคลุมตาม Phase 3 Specification Package -- Context Export Verification Plan
 */
final class ContextExportVerificationTest extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $userId;
    private int $apiTokenId;
    private int $aiConsumerId;
    private AiContextAggregationService $service;

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
        $this->aiConsumerId = $this->seedAiConsumer($this->workspaceId, $this->userId);
        $this->apiTokenId = $this->seedApiToken($this->workspaceId, $this->userId, $this->aiConsumerId);

        $this->service = new AiContextAggregationService(
            new MySqlGovernanceAdoptionRepository($this->db, $this->workspaceId),
            new MySqlProjectRepository($this->db, $this->workspaceId),
            new MySqlProjectStatusUpdateRepository($this->db, $this->workspaceId),
            new MySqlDecisionRegisterRepository($this->db, $this->workspaceId),
            new MySqlAiContextExportRepository($this->db, $this->workspaceId)
        );
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testGeneratedAtExistsInPayload(): void
    {
        $payload = $this->service->getGovernanceSummary($this->apiTokenId, $this->aiConsumerId);

        $this->assertArrayHasKey('generated_at', $payload);
        $this->assertNotEmpty($payload['generated_at']);
    }

    public function testAllFourPayloadTypesHaveGeneratedAt(): void
    {
        $payloads = [
            $this->service->getGovernanceSummary($this->apiTokenId, $this->aiConsumerId),
            $this->service->getProjectStatus($this->apiTokenId, $this->aiConsumerId),
            $this->service->getRecentDecisions($this->apiTokenId, $this->aiConsumerId),
            $this->service->getFullWorkspaceContext($this->apiTokenId, $this->aiConsumerId),
        ];

        foreach ($payloads as $payload) {
            $this->assertArrayHasKey('generated_at', $payload);
        }
    }

    public function testPayloadSnapshotMatchesActualPayload(): void
    {
        $payload = $this->service->getGovernanceSummary($this->apiTokenId, $this->aiConsumerId);

        $stmt = $this->db->prepare(
            'SELECT payload_snapshot FROM ai_context_exports WHERE api_token_id = :token_id ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['token_id' => $this->apiTokenId]);
        $row = $stmt->fetch();

        $storedPayload = json_decode($row['payload_snapshot'], true);

        $this->assertSame($payload, $storedPayload, 'payload_snapshot ต้องตรงกับ payload จริงที่ส่งออก 100%');
    }

    public function testAiConsumerIdIsDenormalizedCorrectly(): void
    {
        $this->service->getGovernanceSummary($this->apiTokenId, $this->aiConsumerId);

        $stmt = $this->db->prepare(
            'SELECT ai_consumer_id FROM ai_context_exports WHERE api_token_id = :token_id ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['token_id' => $this->apiTokenId]);
        $row = $stmt->fetch();

        $this->assertSame($this->aiConsumerId, (int) $row['ai_consumer_id']);
    }

    /**
     * Human Token Export (CTO Decision Option B)
     * Human token ไม่มี ai_consumer_id ผูกอยู่ -- ต้องเขียน NULL ลง ai_context_exports.ai_consumer_id
     * ไม่ใช่ sentinel 0 อีกต่อไป (Gate Remediation -- ปิด Gap ที่เจอใน Gate Verification Report)
     */
    public function testHumanTokenExportWritesNullAiConsumerId(): void
    {
        $humanTokenId = $this->seedApiToken($this->workspaceId, $this->userId, null);

        $this->service->getGovernanceSummary($humanTokenId, null);

        $stmt = $this->db->prepare(
            'SELECT ai_consumer_id FROM ai_context_exports WHERE api_token_id = :token_id ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['token_id' => $humanTokenId]);
        $row = $stmt->fetch();

        $this->assertNull($row['ai_consumer_id'], 'Human token export ต้องบันทึก ai_consumer_id เป็น NULL ไม่ใช่ sentinel 0');
    }

    public function testRecentDecisionsLimitedTo20(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->seedDecision($this->workspaceId, $this->userId, "Decision {$i}");
        }

        $payload = $this->service->getRecentDecisions($this->apiTokenId, $this->aiConsumerId);

        $this->assertCount(20, $payload['recent_decisions'], '/decisions/recent ต้องคืนแค่ 20 รายการตาม default limit');
    }

    public function testExportIsolatedByWorkspace(): void
    {
        $otherUserId = $this->seedUser();
        $otherWorkspaceId = $this->seedWorkspace($otherUserId);
        $otherConsumerId = $this->seedAiConsumer($otherWorkspaceId, $otherUserId);
        $otherTokenId = $this->seedApiToken($otherWorkspaceId, $otherUserId, $otherConsumerId);

        $otherService = new AiContextAggregationService(
            new MySqlGovernanceAdoptionRepository($this->db, $otherWorkspaceId),
            new MySqlProjectRepository($this->db, $otherWorkspaceId),
            new MySqlProjectStatusUpdateRepository($this->db, $otherWorkspaceId),
            new MySqlDecisionRegisterRepository($this->db, $otherWorkspaceId),
            new MySqlAiContextExportRepository($this->db, $otherWorkspaceId)
        );
        $otherService->getGovernanceSummary($otherTokenId, $otherConsumerId);

        $stmt = $this->db->prepare('SELECT COUNT(*) AS cnt FROM ai_context_exports WHERE workspace_id = :ws');
        $stmt->execute(['ws' => $this->workspaceId]);
        $row = $stmt->fetch();

        $this->assertSame(0, (int) $row['cnt'], 'Export log ของ workspace อื่นต้องไม่ปนกับ workspace นี้');
    }

    // ===== Seed helpers =====

    private function seedUser(): int
    {
        $stmt = $this->db->prepare("INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES ('Test', :email, 'hash', 'active', 0)");
        $stmt->execute(['email' => 'ctxexp-' . uniqid() . '@example.com']);
        return (int) $this->db->lastInsertId();
    }

    private function seedWorkspace(int $createdBy): int
    {
        $stmt = $this->db->prepare("INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, :code, 'active', :created_by)");
        $stmt->execute(['code' => 'WS-CTXEXP-' . uniqid(), 'created_by' => $createdBy]);
        return (int) $this->db->lastInsertId();
    }

    private function seedAiConsumer(int $workspaceId, int $createdBy): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ai_consumers (workspace_id, code, name, status, created_by) VALUES (:ws, :code, 'Test AI', 'active', :created_by)"
        );
        $stmt->execute(['ws' => $workspaceId, 'code' => 'AI-' . uniqid(), 'created_by' => $createdBy]);
        return (int) $this->db->lastInsertId();
    }

    private function seedApiToken(int $workspaceId, int $createdBy, ?int $aiConsumerId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO api_tokens (workspace_id, created_by_user_id, ai_consumer_id, token_name, token_hash, status)
             VALUES (:ws, :created_by, :consumer, 'Test Token', :hash, 'active')"
        );
        $stmt->execute(['ws' => $workspaceId, 'created_by' => $createdBy, 'consumer' => $aiConsumerId, 'hash' => hash('sha256', uniqid())]);
        return (int) $this->db->lastInsertId();
    }

    private function seedDecision(int $workspaceId, int $userId, string $title): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO decision_registers (workspace_id, category, title, decision_description, decision_date, decided_by, status, created_by)
             VALUES (:ws, 'technical', :title, 'desc', CURDATE(), :user, 'approved', :user)"
        );
        $stmt->execute(['ws' => $workspaceId, 'title' => $title, 'user' => $userId]);
    }
}
