<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Registry\AiConsumerCodeResolverInterface;
use App\Domain\Registry\ProjectTemplateRepositoryInterface;
use App\Domain\Registry\ProjectTemplateService;
use App\Infrastructure\Persistence\MySQL\MySqlProjectTemplateRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * ProjectTemplateServiceTest — payload contract (CTO Requirement #10, R6-05 §3)
 *
 * จุดสำคัญ: template อ้าง AI Agent ต้อง resolve ผ่าน Registry เท่านั้น (CTO Requirement #2)
 * ต้องรันบน DB ที่ migrate ถึง 0055 แล้ว
 */
final class ProjectTemplateServiceTest extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $adminUserId;
    private ProjectTemplateRepositoryInterface $templateRepo;

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $stmt = $this->db->prepare('INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES ("tpl-admin", :email, NULL, "active", 1)');
        $stmt->execute(['email' => 'tpl-' . uniqid() . '@test.local']);
        $this->adminUserId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, "Template Test WS", "active", :user)');
        $stmt->execute(['code' => 'tpl-ws-' . uniqid(), 'user' => $this->adminUserId]);
        $this->workspaceId = (int) $this->db->lastInsertId();

        $this->templateRepo = new MySqlProjectTemplateRepository($this->db, $this->workspaceId);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    private function serviceWithRegistry(?array $registeredCodes = ['claude-code']): ProjectTemplateService
    {
        $resolver = new class($registeredCodes) implements AiConsumerCodeResolverInterface {
            public function __construct(private readonly ?array $codes)
            {
            }

            public function resolveByCode(string $code): ?array
            {
                if ($this->codes !== null && in_array($code, $this->codes, true)) {
                    return ['id' => 999, 'code' => $code, 'name' => $code, 'status' => 'active'];
                }
                return null;
            }
        };

        return new ProjectTemplateService($this->templateRepo, $resolver);
    }

    public function testValidPayloadAccepted(): void
    {
        $template = $this->serviceWithRegistry()->create(
            $this->workspaceId,
            'standard-web',
            'Standard Web Project',
            null,
            false,
            [
                'milestones' => [['code' => 'M1', 'title' => 'Kick-off', 'planned_offset_days' => 7]],
                'ai_agents' => [['ai_consumer_code' => 'claude-code', 'role' => 'SENIOR_DEV', 'purpose' => 'implementation']],
                'auto_create_project_token' => true,
            ],
            $this->adminUserId
        );

        $this->assertSame('standard-web', $template->code);
        $this->assertSame('claude-code', $template->payload['ai_agents'][0]['ai_consumer_code']);
    }

    public function testUnknownAiAgentCodeRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found in the workspace AI registry');

        $this->serviceWithRegistry(['claude-code'])->create(
            $this->workspaceId,
            'bad-template',
            'Bad Template',
            null,
            false,
            ['ai_agents' => [['ai_consumer_code' => 'not-registered-agent']]],
            $this->adminUserId
        );
    }

    public function testInvalidLayerRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->serviceWithRegistry()->create(
            $this->workspaceId,
            'bad-layer',
            'Bad Layer',
            null,
            false,
            ['tech_stack' => [['layer' => 'quantum', 'name' => 'PHP']]],
            $this->adminUserId
        );
    }

    public function testDuplicateCodeRejected(): void
    {
        $this->expectException(\RuntimeException::class);

        $service = $this->serviceWithRegistry();
        $service->create($this->workspaceId, 'dup-code', 'First', null, false, [], $this->adminUserId);
        $service->create($this->workspaceId, 'dup-code', 'Second', null, false, [], $this->adminUserId);
    }

    public function testSetDefaultSwapsPreviousDefault(): void
    {
        $service = $this->serviceWithRegistry();
        $first = $service->create($this->workspaceId, 'first', 'First', null, true, [], $this->adminUserId);
        $second = $service->create($this->workspaceId, 'second', 'Second', null, false, [], $this->adminUserId);

        $service->setDefault($this->workspaceId, $second->id);

        $default = $this->templateRepo->findDefaultForWorkspace($this->workspaceId);
        $this->assertNotNull($default);
        $this->assertSame($second->id, $default->id);

        // first ยังอยู่แต่ไม่ใช่ default แล้ว
        $stillThere = $this->templateRepo->findById($first->id);
        $this->assertNotNull($stillThere);
        $this->assertFalse($stillThere->isDefault);
    }
}
