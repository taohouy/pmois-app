<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Automation\AutomationJobRepositoryInterface;
use App\Domain\Automation\AutomationJobRunner;
use App\Domain\Automation\AutomationWorkflowService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Automation API (M8)
 * list: automation.view • enqueue/retry/run: automation.manage
 */
final class AutomationController
{
    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly AutomationWorkflowService $workflowService,
        private readonly AutomationJobRepositoryInterface $jobRepository,
        private readonly AutomationJobRunner $jobRunner,
    ) {
    }

    /** GET /api/v1/automation/jobs?status=&job_type=&limit= */
    public function index(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $jobs = $this->jobRepository->list(
            isset($query['status']) && $query['status'] !== '' ? (string) $query['status'] : null,
            isset($query['job_type']) && $query['job_type'] !== '' ? (string) $query['job_type'] : null,
            max(1, min(self::MAX_LIMIT, (int) ($query['limit'] ?? 50)))
        );

        return ApiResponse::success($response, array_map(static fn ($j) => $j->toArray(), $jobs));
    }

    /** POST /api/v1/automation/jobs — enqueue manual job */
    public function enqueue(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        try {
            $job = $this->workflowService->enqueue(
                (string) ($body['job_type'] ?? ''),
                (array) ($body['payload'] ?? []),
                isset($body['workspace_id']) && $body['workspace_id'] !== null ? (int) $body['workspace_id'] : (int) $request->getAttribute('workspace_id'),
                isset($body['project_id']) && $body['project_id'] !== '' ? (int) $body['project_id'] : null,
                (int) $request->getAttribute('user_id'),
                isset($body['scheduled_at']) && $body['scheduled_at'] !== '' ? (string) $body['scheduled_at'] : null
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'automation_job', entityId: $job->id, afterValue: ['job_type' => $job->jobType], action: 'automation_enqueued');

        return ApiResponse::success($response, $job->toArray(), [], 201);
    }

    /** POST /api/v1/automation/jobs/{id}/retry — requeue failed job */
    public function retry(Request $request, Response $response, array $args): Response
    {
        if (!$this->workflowService->retry((int) $args['id'])) {
            return ApiResponse::error($response, 'NOT_FOUND', 'failed job not found (only failed jobs can be retried)', [], 404);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'automation_job', entityId: (int) $args['id'], afterValue: ['status' => 'queued'], action: 'automation_retried');

        return ApiResponse::success($response, ['id' => (int) $args['id'], 'status' => 'queued']);
    }

    /** POST /api/v1/automation/run — run due jobs now (cron ก็เรียกได้ผ่าน CLI worker) */
    public function run(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $limit = max(1, min(50, (int) ($body['limit'] ?? 10)));
        $summary = $this->jobRunner->runDue($limit);

        return ApiResponse::success($response, $summary);
    }

    /** POST /api/v1/automation/ai-dev-auto — AI Dev Auto Integration trigger */
    public function aiDevAuto(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $task = (string) ($body['task'] ?? '');
        $projectId = isset($body['project_id']) ? (int) $body['project_id'] : 0;

        if ($task === '' || $projectId === 0) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'project_id and task are required', [], 422);
        }

        $payload = ['task' => $task];
        if (isset($body['milestone_id'])) {
            $payload['milestone_id'] = (int) $body['milestone_id'];
        }

        try {
            $job = $this->workflowService->enqueue(
                'ai_dev_auto',
                $payload,
                (int) $request->getAttribute('workspace_id'),
                $projectId,
                (int) $request->getAttribute('user_id')
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'automation_job', entityId: $job->id, afterValue: ['task' => $task], action: 'ai_dev_auto_dispatched');

        return ApiResponse::success($response, $job->toArray(), [], 201);
    }
}
