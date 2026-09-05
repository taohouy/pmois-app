<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Auth\InvitationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Invitation (admin) — สร้าง placeholder user + active membership + claim token
 * Permission: workspace.create (is_platform_admin เท่านั้น ตาม PermissionResolver rule 1)
 */
final class InvitationController
{
    public function __construct(private readonly InvitationService $invitationService)
    {
    }

    public function create(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        foreach (['workspace_id', 'project_id'] as $required) {
            if (empty($body[$required])) {
                return ApiResponse::error($response, 'VALIDATION_ERROR', "{$required} is required", [], 422);
            }
        }

        $actorId = (int) $request->getAttribute('user_id');

        $token = $this->invitationService->createInvitation(
            (int) $body['workspace_id'],
            (int) $body['project_id'],
            $actorId,
            (string) ($body['role_code'] ?? 'MEMBER'),
            $actorId
        );

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'invitation', entityId: $actorId, afterValue: ['workspace_id' => (int) $body['workspace_id']], action: 'invitation_created');

        return ApiResponse::success($response, ['claim_url' => '/claim/' . $token], [], 201);
    }
}
