<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Notification\NotificationService;
use App\Domain\Project\RevisionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * CTO Review Workflow API (M3/M5) — permission: revision.review (ADMIN/CTO)
 */
final class RevisionReviewController
{
    public function __construct(
        private readonly RevisionService $revisionService,
        private readonly NotificationService $notifications,
    ) {
    }

    /** POST /api/v1/revisions/{id}/review — body: {decision: approved|rejected, review_note?} */
    public function review(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        $decision = (string) ($body['decision'] ?? '');

        try {
            $revision = $this->revisionService->review(
                (int) $args['id'],
                $decision,
                $body['review_note'] ?? null,
                (int) $request->getAttribute('user_id')
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        } catch (\DomainException $e) {
            return ApiResponse::error($response, $e->getMessage(), 'review rule violated', [], 409);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'revision',
            entityId: $revision->id,
            afterValue: ['status' => $revision->status],
            beforeValue: ['status' => 'submitted'],
            action: 'revision_reviewed'
        );

        // M5: Telegram notification (best-effort)
        $this->notifications->notify('revision_reviewed', $revision->workspaceId, $revision->projectId, [$revision->id, $decision, $revision->summary]);

        return ApiResponse::success($response, $revision->toArray());
    }
}
