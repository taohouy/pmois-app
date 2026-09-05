<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Notification\NotificationService;
use App\Infrastructure\Http\HttpClientInterface;
use App\Infrastructure\Persistence\MySQL\MySqlDashboardRepository;
use App\Infrastructure\Persistence\MySQL\MySqlNotificationRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * ApiPlatformM5Test — M5 API Platform
 *
 * ครอบคลุม: Telegram notification (sent/failed/skipped + event templates),
 * Audit API (filters), Project API Token listing, Scope mapping (config)
 * รันบน DB ที่ migrate ครบ (รวม 0061)
 */
final class ApiPlatformM5Test extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $otherWorkspaceId;
    private int $userId;
    private int $projectId;

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $this->userId = $this->insertUser('m5-user');
        $stmt = $this->db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, "M5 Test WS", "active", :user)');
        $stmt->execute(['code' => 'm5-ws-' . uniqid(), 'user' => $this->userId]);
        $this->workspaceId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare('INSERT INTO projects (workspace_id, code, name, status, development_mode, owner_user_id) VALUES (:ws, "M5-1", "M5 Test Project", "active", "manual", :user)');
        $stmt->execute(['ws' => $this->workspaceId, 'user' => $this->userId]);
        $this->projectId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, "M5 Other WS", "active", :user)');
        $stmt->execute(['code' => 'm5-other-' . uniqid(), 'user' => $this->userId]);
        $this->otherWorkspaceId = (int) $this->db->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        unset($GLOBALS['app_env']['TELEGRAM_BOT_TOKEN'], $GLOBALS['app_env']['TELEGRAM_CHAT_ID']);
    }

    // ===== Telegram Notification Integration =====

    public function testNotConfiguredLogsSkipped(): void
    {
        $service = $this->service(null);
        $service->notify('revision_committed', $this->workspaceId, $this->projectId, [1, 'main', 'success']);

        $counts = $this->repoCounts();
        $this->assertSame(1, $counts['skipped'], 'ยังไม่ตั้ง TELEGRAM env — ต้อง log skipped (best-effort)');
        $this->assertSame(0, $counts['failed']);
    }

    public function testTelegramSendSuccess(): void
    {
        $GLOBALS['app_env']['TELEGRAM_BOT_TOKEN'] = 'test-token';
        $GLOBALS['app_env']['TELEGRAM_CHAT_ID'] = '12345';

        $captured = new class implements HttpClientInterface {
            public ?string $lastBody = null;
            public int $calls = 0;

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->calls++;
                $this->lastBody = (string) $request->getBody();

                return (new ResponseFactory())->createResponse(200);
            }
        };

        $service = $this->service($captured);
        $service->notify('revision_committed', $this->workspaceId, $this->projectId, [7, 'main', 'success']);

        $this->assertSame(1, $captured->calls);
        $this->assertStringContainsString('"chat_id":"12345"', (string) $captured->lastBody);
        $counts = $this->repoCounts();
        $this->assertSame(1, $counts['sent']);
        $this->assertSame(0, $counts['failed']);

        // message template: revision_committed
        $row = $this->db->query('SELECT message, event_type FROM notifications ORDER BY id DESC LIMIT 1')->fetch();
        $this->assertSame('revision_committed', $row['event_type']);
        $this->assertStringContainsString('#7', $row['message']);
    }

    public function testTelegramFailureLoggedNotThrown(): void
    {
        $GLOBALS['app_env']['TELEGRAM_BOT_TOKEN'] = 'test-token';
        $GLOBALS['app_env']['TELEGRAM_CHAT_ID'] = '12345';

        $failing = new class implements HttpClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('connection refused');
            }
        };

        $service = $this->service($failing);
        $service->notify('revision_submitted', $this->workspaceId, $this->projectId, [3, 'do work']);

        // best-effort: ล้มเหลวแล้ว log failed — ห้าม throw ใส่ธุรกรรมหลัก
        $counts = $this->repoCounts();
        $this->assertSame(1, $counts['failed']);
        $this->assertSame(0, $counts['sent']);
    }

    public function testUnknownEventFallsBackToEventName(): void
    {
        $service = $this->service(null);
        $service->notify('custom_event', $this->workspaceId, $this->projectId, []);

        $row = $this->db->query('SELECT message FROM notifications ORDER BY id DESC LIMIT 1')->fetch();
        $this->assertSame('custom_event', $row['message']);
    }

    // ===== Audit API =====

    public function testAuditLogsWithFilters(): void
    {
        $this->seedAudit($this->workspaceId, 'project_created', 'project', $this->projectId);
        $this->seedAudit($this->workspaceId, 'revision_submitted', 'revision', 99);
        $this->seedAudit($this->otherWorkspaceId, 'other_ws_action', 'project', 1);

        $service = new \App\Domain\Dashboard\DashboardService(new MySqlDashboardRepository($this->db), $this->workspaceId);

        $all = $service->auditLogs(50, null, null);
        $this->assertCount(2, $all, 'workspace isolation — audit ของ workspace อื่นต้องไม่หลุด');

        $byEntity = $service->auditLogs(50, 'revision', 99);
        $this->assertCount(1, $byEntity);
        $this->assertSame('revision_submitted', $byEntity[0]['action']);
    }

    // ===== Project API Token listing =====

    public function testProjectTokenListing(): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO api_tokens (workspace_id, project_id, created_by_user_id, token_name, token_hash, status)
             VALUES (:ws, :pid, :user, 'project token', :hash, 'active')"
        );
        $stmt->execute(['ws' => $this->workspaceId, 'pid' => $this->projectId, 'user' => $this->userId, 'hash' => hash('sha256', uniqid())]);

        $repo = new \App\Infrastructure\Persistence\MySQL\MySqlApiTokenRepository($this->db, $this->workspaceId);
        $tokens = $repo->listByProject($this->workspaceId, $this->projectId);

        $this->assertCount(1, $tokens);
        $this->assertSame('project token', $tokens[0]['token_name']);
    }

    // ===== Scope mapping (config) =====

    public function testScopeMapping(): void
    {
        $requestFactory = new \Slim\Psr7\Factory\ServerRequestFactory();

        $get = $requestFactory->createServerRequest('GET', '/api/v1/projects/5/revisions');
        $this->assertSame('read', \App\Application\Middleware\ApiScopeMiddleware::requiredScope($get));

        $post = $requestFactory->createServerRequest('POST', '/api/v1/revisions');
        $this->assertSame('write', \App\Application\Middleware\ApiScopeMiddleware::requiredScope($post));

        $ai = $requestFactory->createServerRequest('GET', '/api/v1/pmo-context');
        $this->assertSame('ai_context', \App\Application\Middleware\ApiScopeMiddleware::requiredScope($ai));
    }

    // ===== helpers =====

    private function service(?HttpClientInterface $client): NotificationService
    {
        return new NotificationService(
            new MySqlNotificationRepository($this->db),
            $client ?? new class implements HttpClientInterface {
                public function sendRequest(RequestInterface $request): ResponseInterface
                {
                    throw new \RuntimeException('no network in this test');
                }
            },
            new RequestFactory(),
            new StreamFactory()
        );
    }

    /** @return array<string, int> */
    private function repoCounts(): array
    {
        return (new MySqlNotificationRepository($this->db))->statusCounts();
    }

    private function seedAudit(int $workspaceId, string $action, string $entityType, int $entityId): void
    {
        $stmt = $this->db->prepare('INSERT INTO audit_trails (workspace_id, user_id, action, entity_type, entity_id) VALUES (:ws, :user, :action, :etype, :eid)');
        $stmt->execute(['ws' => $workspaceId, 'user' => $this->userId, 'action' => $action, 'etype' => $entityType, 'eid' => $entityId]);
    }

    private function insertUser(string $name): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES (:name, :email, NULL, "active", 0)');
        $stmt->execute(['name' => $name, 'email' => $name . uniqid() . '@test.local']);

        return (int) $this->db->lastInsertId();
    }
}
