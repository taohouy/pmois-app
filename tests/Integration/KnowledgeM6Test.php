<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Knowledge\KnowledgeEntryRepositoryInterface;
use App\Domain\Knowledge\KnowledgeService;
use App\Infrastructure\Persistence\MySQL\MySqlDecisionRegisterRepository;
use App\Infrastructure\Persistence\MySQL\MySqlKnowledgeArticleRepository;
use App\Infrastructure\Persistence\MySQL\MySqlKnowledgeEntryRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * KnowledgeM6Test — M6 Knowledge Center
 *
 * ครอบคลุม: Business Rules Registry, Known Issues, Risk Register, Future Enhancements,
 * ADR (decision_registers category='architecture'), unified Search API,
 * Project Knowledge Base (linking), Knowledge Timeline, workspace isolation
 * รันบน DB ที่ migrate ครบ (รวม 0062)
 */
final class KnowledgeM6Test extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $otherWorkspaceId;
    private int $userId;
    private int $projectId;
    private KnowledgeService $service;
    private KnowledgeEntryRepositoryInterface $entryRepo;

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $this->userId = $this->insertUser('k6-user');
        $stmt = $this->db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, "K6 Test WS", "active", :user)');
        $stmt->execute(['code' => 'k6-ws-' . uniqid(), 'user' => $this->userId]);
        $this->workspaceId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, "K6 Other WS", "active", :user)');
        $stmt->execute(['code' => 'k6-other-' . uniqid(), 'user' => $this->userId]);
        $this->otherWorkspaceId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare('INSERT INTO projects (workspace_id, code, name, status, development_mode, owner_user_id) VALUES (:ws, "K6-1", "K6 Test Project", "active", "manual", :user)');
        $stmt->execute(['ws' => $this->workspaceId, 'user' => $this->userId]);
        $this->projectId = (int) $this->db->lastInsertId();

        $this->entryRepo = new MySqlKnowledgeEntryRepository($this->db, $this->workspaceId);
        $this->service = new KnowledgeService(
            $this->entryRepo,
            new MySqlKnowledgeArticleRepository($this->db, $this->workspaceId),
            new MySqlDecisionRegisterRepository($this->db, $this->workspaceId),
            $this->workspaceId
        );
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testCreateEachRegistryType(): void
    {
        $br = $this->service->create([
            'entry_type' => 'business_rule', 'code' => 'BR-001', 'title' => 'Code freeze policy',
            'body' => 'No feature merge after UAT start', 'status' => 'active', 'project_id' => $this->projectId,
        ], $this->userId);
        $this->assertSame('business_rule', $br->entryType);

        $ki = $this->service->create([
            'entry_type' => 'known_issue', 'code' => 'KI-001', 'title' => 'LINE webhook delay',
            'body' => 'Callback delayed up to 30s', 'severity' => 'medium', 'status' => 'workaround',
            'project_id' => $this->projectId,
        ], $this->userId);
        $this->assertSame('medium', $ki->severity);

        $risk = $this->service->create([
            'entry_type' => 'risk', 'code' => 'RISK-001', 'title' => 'Single CTO bottleneck',
            'body' => 'All reviews depend on one CTO', 'probability' => 'medium', 'impact' => 'high',
            'mitigation' => 'Train backup reviewer', 'status' => 'open', 'project_id' => $this->projectId,
        ], $this->userId);
        $this->assertSame('high', $risk->impact);
        $this->assertNotNull($risk->mitigation);

        $fe = $this->service->create([
            'entry_type' => 'future_enhancement', 'title' => 'Multi-workspace dashboard',
            'body' => 'Aggregate dashboard across workspaces', 'status' => 'proposed',
        ], $this->userId);
        $this->assertSame('proposed', $fe->status);
        $this->assertNull($fe->projectId, 'workspace-level knowledge (ไม่ผูก project) ได้');

        $this->assertCount(4, $this->service->listEntries(null, null, null));
    }

    public function testKnownIssueRequiresSeverity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('severity is required');

        $this->service->create([
            'entry_type' => 'known_issue', 'title' => 'Missing severity', 'body' => 'b',
        ], $this->userId);
    }

    public function testRiskRequiresProbabilityAndImpact(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('probability and impact are required');

        $this->service->create([
            'entry_type' => 'risk', 'title' => 'Incomplete risk', 'body' => 'b', 'probability' => 'high',
        ], $this->userId);
    }

    public function testInvalidStatusForTypeRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('status for business_rule');

        $this->service->create([
            'entry_type' => 'business_rule', 'title' => 'Bad status', 'body' => 'b', 'status' => 'resolved',
        ], $this->userId);
    }

    public function testUnifiedSearchAcrossTypes(): void
    {
        $this->service->create([
            'entry_type' => 'business_rule', 'code' => 'BR-002', 'title' => 'Deployment window rule',
            'body' => 'Deploy only 22:00-23:00', 'status' => 'active', 'project_id' => $this->projectId,
        ], $this->userId);
        $this->service->create([
            'entry_type' => 'risk', 'title' => 'Deployment downtime risk', 'body' => 'Deploy นอก window อาจกระทบผู้ใช้',
            'probability' => 'low', 'impact' => 'high', 'status' => 'open',
        ], $this->userId);

        $result = $this->service->search('Deploy');

        // ค้นเจอทั้ง business rule (title) และ risk (title/body) — cross-type
        $entryTypes = array_unique(array_column($result['entries'], 'type'));
        $this->assertContains('business_rule', $entryTypes);
        $this->assertContains('risk', $entryTypes);
    }

    public function testEmptySearchKeywordRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->search('   ');
    }

    public function testProjectKnowledgeBaseLinking(): void
    {
        $this->service->create([
            'entry_type' => 'business_rule', 'title' => 'Linked rule', 'body' => 'b', 'project_id' => $this->projectId,
        ], $this->userId);
        $this->service->create([
            'entry_type' => 'future_enhancement', 'title' => 'Unlinked enhancement', 'body' => 'b',
        ], $this->userId);

        $knowledge = $this->service->projectKnowledge($this->projectId);
        $this->assertCount(1, $knowledge['entries'], 'Project KB เฉพาะ entries ที่ผูกกับ project นี้');
        $this->assertSame('Linked rule', $knowledge['entries'][0]['title']);
    }

    public function testKnowledgeTimelineOrderedNewestFirst(): void
    {
        $this->service->create(['entry_type' => 'business_rule', 'title' => 'First', 'body' => 'b'], $this->userId);
        $this->service->create(['entry_type' => 'risk', 'title' => 'Second', 'body' => 'b', 'probability' => 'low', 'impact' => 'low'], $this->userId);

        $timeline = $this->service->knowledgeTimeline(null, 20);
        $this->assertCount(2, $timeline);
        $this->assertSame('Second', $timeline[0]['title'], 'เรียงใหม่→เก่า');
    }

    public function testWorkspaceIsolation(): void
    {
        $this->service->create([
            'entry_type' => 'business_rule', 'title' => 'Only in main ws', 'body' => 'b',
        ], $this->userId);

        $other = new KnowledgeService(
            new MySqlKnowledgeEntryRepository($this->db, $this->otherWorkspaceId),
            new MySqlKnowledgeArticleRepository($this->db, $this->otherWorkspaceId),
            new MySqlDecisionRegisterRepository($this->db, $this->otherWorkspaceId),
            $this->otherWorkspaceId
        );

        $this->assertSame([], $other->listEntries(null, null, null), 'workspace อื่นต้องไม่เห็นข้อมูล');
    }

    private function insertUser(string $name): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES (:name, :email, NULL, "active", 0)');
        $stmt->execute(['name' => $name, 'email' => $name . uniqid() . '@test.local']);

        return (int) $this->db->lastInsertId();
    }
}
