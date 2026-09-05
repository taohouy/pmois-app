<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Automation\AutomationWorkflowService;
use App\Domain\Project\MilestoneService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Milestone Foundation — CRUD + close (permission ผูกที่ route: milestone.view/create/update/close)
 */
final class MilestoneController
{
    public function __construct(
        private readonly MilestoneService $milestoneService,
        private readonly AutomationWorkflowService $automationWorkflow,
    ) {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $milestones = $this->milestoneService->getByProjectId((int) $args['project_id']);

        return ApiResponse::success($response, array_map(static fn ($m) => [
            'id' => $m->id,
            'project_id' => $m->projectId,
            'code' => $m->code,
            'title' => $m->title,
            'status' => $m->status,
            'planned_date' => $m->plannedDate,
            'closed_by' => $m->closedBy,
            'closed_at' => $m->closedAt,
        ], $milestones));
    }

    public function create(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        if (empty($body['code']) || empty($body['title'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'code and title are required', [], 422);
        }

        $milestoneId = $this->milestoneService->create(
            (string) $body['code'],
            (string) $body['title'],
            (int) $args['project_id'],
            (int) $request->getAttribute('workspace_id'),
            $body['planned_date'] ?? null,
            (int) $request->getAttribute('user_id')
        );

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'milestone', entityId: $milestoneId, afterValue: ['code' => $body['code'], 'title' => $body['title']]);

        return ApiResponse::success($response, ['id' => $milestoneId], [], 201);
    }

    public function close(Request $request, Response $response, array $args): Response
    {
        $milestoneId = (int) $args['id'];
        $milestone = $this->milestoneService->getById($milestoneId);
        if ($milestone === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'milestone not found', [], 404);
        }

        $ok = $this->milestoneService->close($milestoneId, (int) $request->getAttribute('user_id'));
        if (!$ok) {
            return ApiResponse::error($response, 'NOT_FOUND', 'milestone not found', [], 404);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'milestone', entityId: $milestoneId, afterValue: ['status' => 'closed'], action: 'milestone_close');

        // M8 Automation Workflow: Automatic Project Update (queued job)
        $this->automationWorkflow->onMilestoneClosed($milestone->workspaceId, $milestone->projectId, $milestoneId);

        return ApiResponse::success($response, ['id' => $milestoneId, 'status' => 'closed']);
    }

    public function reopen(Request $request, Response $response, array $args): Response
    {
        $ok = $this->milestoneService->open((int) $args['id']);
        if (!$ok) {
            return ApiResponse::error($response, 'NOT_FOUND', 'milestone not found', [], 404);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'milestone', entityId: (int) $args['id'], afterValue: ['status' => 'open'], action: 'milestone_open');

        return ApiResponse::success($response, ['id' => (int) $args['id'], 'status' => 'open']);
    }

    public function updateTitle(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        if (empty($body['title'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'title is required', [], 422);
        }

        try {
            $this->milestoneService->updateTitle((int) $args['id'], (string) $body['title']);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'NOT_FOUND', 'milestone not found', [], 404);
        }

        return ApiResponse::success($response, ['id' => (int) $args['id'], 'title' => $body['title']]);
    }
}
