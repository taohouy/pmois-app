<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Automation\AutomationWorkflowService;
use App\Domain\Notification\NotificationService;
use App\Domain\Project\RevisionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Revision Management + Commit Tracking (M3/M5/M8)
 * submit/commit: revision.create • list/show: project.view
 */
final class RevisionController
{
    public function __construct(
        private readonly RevisionService $revisionService,
        private readonly NotificationService $notifications,
        private readonly AutomationWorkflowService $automationWorkflow,
    ) {
    }

    /** POST /api/v1/revisions */
    public function submit(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        try {
            $revision = $this->revisionService->submit($body, (int) $request->getAttribute('user_id'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        } catch (\DomainException $e) {
            return ApiResponse::error($response, $e->getMessage(), 'revision submit rule violated', [], 409);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'revision', entityId: $revision->id, afterValue: ['status' => $revision->status, 'summary' => $revision->summary], action: 'revision_submitted');

        // M5: Telegram notification (best-effort — ไม่มีผลต่อธุรกรรมหลัก)
        $this->notifications->notify('revision_submitted', $revision->workspaceId, $revision->projectId, [$revision->id, $revision->summary]);

        return ApiResponse::success($response, $revision->toArray(), [], 201);
    }

    /** PATCH /api/v1/revisions/{id}/commit */
    public function commit(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        $commitHash = (string) ($body['commit_hash'] ?? '');

        try {
            $revision = $this->revisionService->commit(
                (int) $args['id'],
                $commitHash,
                $body['branch'] ?? null,
                (string) ($body['push_status'] ?? 'success'),
                (int) $request->getAttribute('user_id')
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        } catch (\DomainException $e) {
            return ApiResponse::error($response, $e->getMessage(), 'commit rule violated', [], 409);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'revision', entityId: $revision->id, afterValue: ['commit_hash' => $revision->commitHash, 'push_status' => $revision->pushStatus], action: 'revision_committed');

        $this->notifications->notify('revision_committed', $revision->workspaceId, $revision->projectId, [$revision->id, $revision->branch, $revision->pushStatus]);

        // M8 Automation Workflow: Automatic Timeline Update (queued job — idempotent)
        $this->automationWorkflow->onRevisionCommitted($revision->workspaceId, $revision->projectId, $revision->id);

        return ApiResponse::success($response, $revision->toArray());
    }

    /** GET /api/v1/projects/{project_id}/revisions?status= */
    public function listByProject(Request $request, Response $response, array $args): Response
    {
        $status = $request->getQueryParams()['status'] ?? null;
        $revisions = $this->revisionService->listByProject((int) $args['project_id'], $status !== null ? (string) $status : null);

        return ApiResponse::success($response, array_map(static fn ($r) => $r->toArray(), $revisions));
    }

    /** GET /api/v1/revisions/{id} — รวม review history */
    public function show(Request $request, Response $response, array $args): Response
    {
        $revision = $this->revisionService->getById((int) $args['id']);
        if ($revision === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'revision not found', [], 404);
        }

        $data = $revision->toArray();
        $data['reviews'] = $this->revisionService->getReviews($revision->id);

        return ApiResponse::success($response, $data);
    }
}
