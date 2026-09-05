<?php

declare(strict_types=1);

namespace App\Domain\Automation;

use App\Domain\Notification\NotificationService;
use App\Domain\Project\ProjectStatusUpdater;
use App\Infrastructure\Http\HttpClientInterface;
use App\Infrastructure\Persistence\MySQL\MySqlProfileCompletenessProvider;
use App\Infrastructure\Persistence\MySQL\MySqlProjectRepository;
use App\Infrastructure\Persistence\MySQL\MySqlRepositoryRegistryRepository;
use App\Infrastructure\Persistence\MySQL\MySqlRevisionRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectStatusUpdateRepository;
use App\Infrastructure\Persistence\MySQL\MySqlMilestoneRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectTechStackRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectEnvironmentRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectReleaseRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectMemberAssignmentRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectAiAssignmentRepository;
use App\Domain\Governance\GovernanceAdoptionRepositoryInterface;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceAdoptionRepository;
use PDO;

/**
 * AutomationJobRunner — Background Jobs engine (M8)
 *
 * Claim due jobs (queued + scheduled_at <= NOW()) แล้ว execute ตาม handler registry (config)
 * Retry policy: attempts < max_attempts → กลับไป queued (backoff 60s × attempts); ครบ → failed
 *
 * Handlers สร้าง workspace-scoped repositories ณ execution time จาก job.workspace_id
 * เพราะ worker ทำงานข้าม workspace
 */
final class AutomationJobRunner
{
    /** job_type => handler method (config — เพิ่ม job type ใหม่แก้ที่เดียว) */
    private const HANDLERS = [
        'timeline_update' => 'handleTimelineUpdate',
        'project_update' => 'handleProjectUpdate',
        'notification' => 'handleNotification',
        'gitlab_sync' => 'handleGitlabSync',
        'ai_dev_auto' => 'handleAiDevAuto',
    ];

    private const BACKOFF_SECONDS = 60;

    public function __construct(
        private readonly PDO $db,
        private readonly AutomationJobRepositoryInterface $jobRepository,
        private readonly NotificationService $notifications,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array<string, mixed> {claimed, completed, failed, results}
     */
    public function runDue(int $limit = 10): array
    {
        $claimed = $this->jobRepository->claimDue($limit);
        $completed = 0;
        $failed = 0;
        $results = [];

        foreach ($claimed as $job) {
            try {
                $result = $this->handle($job);
                $this->jobRepository->markCompleted($job->id, $result);
                $completed++;
                $results[] = ['id' => $job->id, 'job_type' => $job->jobType, 'status' => 'completed'];
            } catch (\Throwable $e) {
                $this->jobRepository->markFailedWithRetry($job->id, $e->getMessage(), self::BACKOFF_SECONDS * max(1, $job->attempts));
                $failed++;
                $results[] = ['id' => $job->id, 'job_type' => $job->jobType, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return ['claimed' => count($claimed), 'completed' => $completed, 'failed' => $failed, 'results' => $results];
    }

    /**
     * @return array<string, mixed>
     */
    private function handle(AutomationJob $job): array
    {
        $handler = self::HANDLERS[$job->jobType] ?? null;
        if ($handler === null) {
            throw new \InvalidArgumentException("no handler for job_type '{$job->jobType}'");
        }

        return $this->{$handler}($job);
    }

    /**
     * Automatic Timeline Update — สร้าง project_status_updates จาก revision ที่ commit
     * (idempotent ผ่าน ProjectStatusUpdater)
     */
    private function handleTimelineUpdate(AutomationJob $job): array
    {
        $workspaceId = (int) $job->workspaceId;
        $revisionId = (int) ($job->payload['revision_id'] ?? 0);

        if ($revisionId === 0) {
            throw new \InvalidArgumentException('payload.revision_id is required');
        }

        $updater = new ProjectStatusUpdater(
            new MySqlProjectStatusUpdateRepository($this->db, $workspaceId),
            new MySqlRevisionRepository($this->db, $workspaceId)
        );
        $updater->updateOnRevisionCommitted($revisionId);

        return ['revision_id' => $revisionId, 'timeline_updated' => true];
    }

    /**
     * Automatic Project Update — recomputes profile completeness
     */
    private function handleProjectUpdate(AutomationJob $job): array
    {
        $workspaceId = (int) $job->workspaceId;
        $projectId = (int) ($job->payload['project_id'] ?? 0);

        if ($projectId === 0) {
            throw new \InvalidArgumentException('payload.project_id is required');
        }

        $calculator = new \App\Domain\Project\ProfileCompletenessCalculator(
            new MySqlGovernanceAdoptionRepository($this->db, $workspaceId),
            new MySqlProjectMemberAssignmentRepository($this->db, $workspaceId),
            new MySqlProjectAiAssignmentRepository($this->db, $workspaceId),
            new MySqlRepositoryRegistryRepository($this->db, $workspaceId),
            new MySqlMilestoneRepository($this->db, $workspaceId),
            new MySqlProjectEnvironmentRepository($this->db, $workspaceId),
            new MySqlProjectTechStackRepository($this->db, $workspaceId),
            new MySqlProjectReleaseRepository($this->db, $workspaceId)
        );
        $provider = new MySqlProfileCompletenessProvider(
            $calculator,
            new MySqlProjectRepository($this->db, $workspaceId),
            $this->db
        );

        return ['project_id' => $projectId, 'profile_completeness_percent' => $provider->refresh($projectId)];
    }

    /**
     * Telegram Automation — ส่งข้อความจาก payload
     */
    private function handleNotification(AutomationJob $job): array
    {
        $message = (string) ($job->payload['message'] ?? '');
        if ($message === '') {
            throw new \InvalidArgumentException('payload.message is required');
        }

        $this->notifications->sendMessage($message, $job->workspaceId, $job->projectId);

        return ['message' => $message, 'dispatched' => true];
    }

    /**
     * GitLab Integration (read-only sync check) — ตรวจว่า repository URL เข้าถึงได้
     * (M0 stance: PMOIS ไม่เขียน GitLab — เช็คสถานะ + บันทึกผลใน job result)
     */
    private function handleGitlabSync(AutomationJob $job): array
    {
        $workspaceId = (int) $job->workspaceId;
        $projectId = (int) ($job->payload['project_id'] ?? 0);

        if ($projectId === 0) {
            throw new \InvalidArgumentException('payload.project_id is required');
        }

        $repos = (new MySqlRepositoryRegistryRepository($this->db, $workspaceId))->findByProjectId($projectId);
        if ($repos === []) {
            throw new \InvalidArgumentException('project has no registered repositories');
        }

        $results = [];
        foreach ($repos as $repo) {
            try {
                $request = $this->createRequest('GET', $repo->repositoryUrl);
                $response = $this->httpClient->sendRequest($request);
                $results[] = ['repository_url' => $repo->repositoryUrl, 'http_status' => $response->getStatusCode()];
            } catch (\Throwable $e) {
                $results[] = ['repository_url' => $repo->repositoryUrl, 'error' => $e->getMessage()];
            }
        }

        foreach ($results as $r) {
            if (isset($r['error']) || (int) $r['http_status'] >= 400) {
                throw new \RuntimeException('gitlab sync check failed for ' . (string) $r['repository_url']);
            }
        }

        return ['project_id' => $projectId, 'repositories' => $results];
    }

    /**
     * AI Dev Auto Integration — dispatch task ไปยัง AI agent channel (Telegram)
     * AI agent ที่รับงานจะส่ง revision กลับผ่าน API เดิม (dev_ai_consumer_id)
     */
    private function handleAiDevAuto(AutomationJob $job): array
    {
        $task = (string) ($job->payload['task'] ?? '');
        if ($task === '') {
            throw new \InvalidArgumentException('payload.task is required');
        }

        $this->notifications->sendMessage(
            sprintf('🤖 AI Dev Auto task (project #%s, milestone #%s): %s',
                (string) $job->projectId,
                (string) ($job->payload['milestone_id'] ?? '-'),
                $task
            ),
            $job->workspaceId,
            $job->projectId
        );

        return ['task' => $task, 'dispatched' => true];
    }

    private function createRequest(string $method, string $url): \Psr\Http\Message\RequestInterface
    {
        // StreamFactory ฉีดผ่าน NotificationService ไม่ได้ — ใช้ slim factory ตรงนี้
        return (new \Slim\Psr7\Factory\RequestFactory())->createRequest($method, $url);
    }
}
