<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Automation\AutomationJobRunner;
use App\Domain\Automation\AutomationWorkflowService;
use App\Infrastructure\Http\HttpClientInterface;
use App\Infrastructure\Persistence\MySQL\MySqlAutomationJobRepository;
use App\Infrastructure\Persistence\MySQL\MySqlNotificationRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * AutomationM8Test — M8 Automation Center
 *
 * ครอบคลุม: queue enqueue/claim, handlers (timeline_update/project_update/notification/
 * gitlab_sync/ai_dev_auto), retry policy (backoff → failed เมื่อครบ max_attempts),
 * workflow triggers, AI Dev Auto dispatch
 * รันบน DB ที่ migrate ครบ (รวม 0063/0064)
 */
final class AutomationM8Test extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $userId;
    private int $projectId;
    private MySqlAutomationJobRepository $repo;
    private AutomationWorkflowService $workflow;
    private ?HttpClientInterface $httpClient = null;

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $this->userId = $this->insertUser('m8-user');
        $stmt = $this->db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, "M8 Test WS", "active", :user)');
        $stmt->execute(['code' => 'm8-ws-' . uniqid(), 'user' => $this->userId]);
        $this->workspaceId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare('INSERT INTO projects (workspace_id, code, name, status, development_mode, owner_user_id) VALUES (:ws, "M8-1", "M8 Test Project", "active", "ai_dev_auto", :user)');
        $stmt->execute(['ws' => $this->workspaceId, 'user' => $this->userId]);
        $this->projectId = (int) $this->db->lastInsertId();

        $this->repo = new MySqlAutomationJobRepository($this->db);
        $this->workflow = new AutomationWorkflowService($this->repo);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    private function runner(): AutomationJobRunner
    {
        return new AutomationJobRunner(
            $this->db,
            $this->repo,
            new \App\Domain\Notification\NotificationService(
                new MySqlNotificationRepository($this->db),
                $this->httpClient ?? new class implements HttpClientInterface {
                    public function sendRequest(RequestInterface $request): ResponseInterface
                    {
                        return (new ResponseFactory())->createResponse(200);
                    }
                },
                new \Slim\Psr7\Factory\RequestFactory(),
                new \Slim\Psr7\Factory\StreamFactory()
            ),
            $this->httpClient ?? new class implements HttpClientInterface {
                public function sendRequest(RequestInterface $request): ResponseInterface
                {
                    return (new ResponseFactory())->createResponse(200);
                }
            }
        );
    }

    // ===== Queue basics =====

    public function testEnqueueDefaultsToQueued(): void
    {
        $job = $this->workflow->enqueue('notification', ['message' => 'hello'], $this->workspaceId, $this->projectId, $this->userId);

        $this->assertSame('queued', $job->status);
        $this->assertSame(0, $job->attempts);
        $this->assertSame(3, $job->maxAttempts);
    }

    public function testInvalidJobTypeRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('job_type must be one of');

        $this->workflow->enqueue('magic_job', [], $this->workspaceId, $this->projectId, $this->userId);
    }

    public function testScheduledFutureJobNotClaimed(): void
    {
        // scheduled_at ใช้เวลาฝั่ง MySQL (NOW()) เพื่อไม่ให้ timezone ของ PHP/MySQL คลาดกัน
        $this->workflow->enqueue('notification', ['message' => 'later'], $this->workspaceId, $this->projectId, $this->userId);
        $this->db->prepare("UPDATE automation_jobs SET scheduled_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE job_type = 'notification'")->execute();

        $summary = $this->runner()->runDue(10);
        $this->assertSame(0, $summary['claimed']);
    }

    // ===== Handlers =====

    public function testTimelineUpdateHandlerCreatesPmoTimelineRow(): void
    {
        $revisionId = $this->seedRevision('committed');
        $this->workflow->enqueue('timeline_update', ['revision_id' => $revisionId], $this->workspaceId, $this->projectId, $this->userId);

        $summary = $this->runner()->runDue(10);
        $this->assertSame(1, $summary['completed']);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM project_status_updates WHERE idempotency_key = 'revision-commit-{$revisionId}'");
        $stmt->execute();
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'timeline_update handler ต้องสร้าง PMO timeline row');
    }

    public function testProjectUpdateHandlerRecomputesCompleteness(): void
    {
        $this->workflow->enqueue('project_update', ['project_id' => $this->projectId], $this->workspaceId, $this->projectId, $this->userId);

        $summary = $this->runner()->runDue(10);
        $this->assertSame(1, $summary['completed']);

        $stmt = $this->db->prepare('SELECT result FROM automation_jobs ORDER BY id DESC LIMIT 1');
        $stmt->execute();
        $result = json_decode((string) $stmt->fetchColumn(), true);
        $this->assertArrayHasKey('profile_completeness_percent', $result);
    }

    public function testGitlabSyncHandlerChecksRepositoryUrls(): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO repositories (project_id, workspace_id, git_provider_id, repository_type, repository_name, repository_url, default_branch, created_by)
             VALUES (:pid, :ws, (SELECT id FROM git_providers WHERE code = "gitlab"), "main", "m8-repo", "https://gitlab.com/pmois/m8-repo", "main", :by)'
        );
        $stmt->execute(['pid' => $this->projectId, 'ws' => $this->workspaceId, 'by' => $this->userId]);

        $this->workflow->enqueue('gitlab_sync', ['project_id' => $this->projectId], $this->workspaceId, $this->projectId, $this->userId);

        // fake HTTP client returns 200 → sync ผ่าน
        $summary = $this->runner()->runDue(10);
        $this->assertSame(1, $summary['completed']);
    }

    public function testGitlabSyncFailureMarksJobFailed(): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO repositories (project_id, workspace_id, git_provider_id, repository_type, repository_name, repository_url, default_branch, created_by)
             VALUES (:pid, :ws, (SELECT id FROM git_providers WHERE code = "gitlab"), "main", "m8-repo", "https://gitlab.com/pmois/m8-repo", "main", :by)'
        );
        $stmt->execute(['pid' => $this->projectId, 'ws' => $this->workspaceId, 'by' => $this->userId]);

        // HTTP 500 → sync check ล้มเหลว
        $this->httpClient = new class implements HttpClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return (new ResponseFactory())->createResponse(500);
            }
        };

        $this->workflow->enqueue('gitlab_sync', ['project_id' => $this->projectId], $this->workspaceId, $this->projectId, $this->userId);
        $runner = $this->runner();

        // attempt 1 → ยังไม่ครบ max → กลับไป queued (backoff)
        $summary = $runner->runDue(10);
        $this->assertSame(1, $summary['failed']);

        // รันจนครบ max_attempts (ข้าม backoff ด้วยการเลื่อน scheduled_at เป็นอดีต)
        for ($i = 0; $i < 2; $i++) {
            $this->db->prepare("UPDATE automation_jobs SET scheduled_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE status = 'queued'")->execute();
            $runner->runDue(10);
        }

        $job = $this->repo->list('failed', 'gitlab_sync', 1)[0] ?? null;
        $this->assertNotNull($job);
        $this->assertSame(3, $job->attempts);
        $this->assertStringContainsString('gitlab sync check failed', (string) $job->lastError);
    }

    // ===== Retry policy =====

    public function testRetryBackoffThenFailedAfterMaxAttempts(): void
    {
        // job ที่ fail แน่ ๆ: gitlab_sync โดยไม่มี repo
        $this->workflow->enqueue('gitlab_sync', ['project_id' => $this->projectId], $this->workspaceId, $this->projectId, $this->userId);
        $runner = $this->runner();

        // attempt 1 → กลับไป queued (backoff)
        $runner->runDue(10);
        $job = $this->repo->list('queued', 'gitlab_sync', 1)[0] ?? null;
        $this->assertNotNull($job, 'attempt 1 ยังไม่ครบ max → กลับไป queued');
        $this->assertSame(1, $job->attempts);

        // บังคับ scheduled_at เป็นอดีต (ข้าม backoff) แล้วรันอีก 2 ครั้งให้ครบ max_attempts=3
        for ($i = 0; $i < 2; $i++) {
            $this->db->prepare("UPDATE automation_jobs SET scheduled_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE status = 'queued'")->execute();
            $runner->runDue(10);
        }

        $failed = $this->repo->list('failed', 'gitlab_sync', 1)[0] ?? null;
        $this->assertNotNull($failed, 'ครบ max_attempts → failed');
        $this->assertSame(3, $failed->attempts);

        // retry endpoint semantics: failed → queued (reset attempts)
        $this->assertTrue($this->workflow->retry($failed->id));
        $requeued = $this->repo->list('queued', 'gitlab_sync', 1)[0] ?? null;
        $this->assertNotNull($requeued);
        $this->assertSame(0, $requeued->attempts);
    }

    // ===== AI Dev Auto Integration =====

    public function testAiDevAutoDispatchSendsTelegram(): void
    {
        $GLOBALS['app_env']['TELEGRAM_BOT_TOKEN'] = 'test-token';
        $GLOBALS['app_env']['TELEGRAM_CHAT_ID'] = '12345';

        $captured = new class implements HttpClientInterface {
            public ?string $lastBody = null;
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->lastBody = (string) $request->getBody();
                return (new ResponseFactory())->createResponse(200);
            }
        };
        $this->httpClient = $captured;

        $this->workflow->enqueue('ai_dev_auto', ['task' => 'Implement feature X', 'milestone_id' => 5], $this->workspaceId, $this->projectId, $this->userId);
        $summary = $this->runner()->runDue(10);

        $this->assertSame(1, $summary['completed']);
        $this->assertStringContainsString('Implement feature X', (string) $captured->lastBody, 'task ต้องถูก dispatch ผ่าน Telegram channel');
    }

    public function testWorkflowTriggersEnqueueJobs(): void
    {
        $this->workflow->onRevisionCommitted($this->workspaceId, $this->projectId, 55);
        $this->workflow->onMilestoneClosed($this->workspaceId, $this->projectId, 66);
        $this->workflow->onDeploymentStatusChanged($this->workspaceId, $this->projectId, 77, 'deployed');

        $this->assertCount(1, $this->repo->list(null, 'timeline_update', 10));
        $this->assertCount(1, $this->repo->list(null, 'project_update', 10));
        $this->assertCount(1, $this->repo->list(null, 'notification', 10));
    }

    // ===== helpers =====

    private function seedRevision(string $status): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO revisions (project_id, workspace_id, status, summary, dev_user_id, submitted_by, submitted_at, committed_at)
             VALUES (:pid, :ws, :status, "m8 work", :dev, :by, :submitted, :committed)'
        );
        $submitted = date('Y-m-d H:i:s', time() - 3600);
        $stmt->execute([
            'pid' => $this->projectId, 'ws' => $this->workspaceId, 'status' => $status,
            'dev' => $this->userId, 'by' => $this->userId, 'submitted' => $submitted,
            'committed' => $status === 'committed' ? date('Y-m-d H:i:s', time() - 1800) : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function insertUser(string $name): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES (:name, :email, NULL, "active", 0)');
        $stmt->execute(['name' => $name, 'email' => $name . uniqid() . '@test.local']);

        return (int) $this->db->lastInsertId();
    }
}
