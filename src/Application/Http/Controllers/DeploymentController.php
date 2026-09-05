<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Automation\AutomationWorkflowService;
use App\Domain\Notification\NotificationService;
use App\Domain\Project\DeploymentService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Deployment Tracking API (M3/M5/M8) — write: project.release.manage, read: project.view
 */
final class DeploymentController
{
    public function __construct(
        private readonly DeploymentService $deploymentService,
        private readonly NotificationService $notifications,
        private readonly AutomationWorkflowService $automationWorkflow,
    ) {
    }

    /** GET /api/v1/projects/{project_id}/deployments */
    public function index(Request $request, Response $response, array $args): Response
    {
        $deployments = $this->deploymentService->listByProject((int) $args['project_id']);

        return ApiResponse::success($response, array_map(static fn ($d) => $d->toArray(), $deployments));
    }

    /** POST /api/v1/projects/{project_id}/deployments — body: {release_id?, environment_id?, notes?} */
    public function create(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        $body['project_id'] = $body['project_id'] ?? (int) $args['project_id'];

        try {
            $deployment = $this->deploymentService->create($body, (int) $request->getAttribute('user_id'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'project_deployment', entityId: $deployment->id, afterValue: ['status' => $deployment->status]);

        return ApiResponse::success($response, $deployment->toArray(), [], 201);
    }

    /** PATCH /api/v1/deployments/{id}/transition — body: {status} */
    public function transition(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        $toStatus = (string) ($body['status'] ?? '');
        if ($toStatus === '') {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'status is required', [], 422);
        }

        try {
            $deployment = $this->deploymentService->transition((int) $args['id'], $toStatus, (int) $request->getAttribute('user_id'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'project_deployment', entityId: $deployment->id, afterValue: ['status' => $deployment->status], action: 'deployment_status_changed');

        $this->notifications->notify('deployment_status_changed', $deployment->workspaceId, $deployment->projectId, [$deployment->id, $deployment->status, $deployment->projectId]);

        // M8 Automation Workflow: Telegram Automation via queue
        $this->automationWorkflow->onDeploymentStatusChanged($deployment->workspaceId, $deployment->projectId, $deployment->id, $deployment->status);

        return ApiResponse::success($response, $deployment->toArray());
    }
}
