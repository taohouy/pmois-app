<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Project\AiAssignmentService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * AI Assignment (project_ai_assignments) — write: ai_assignment.manage (route level)
 * Assignment อ้างอิง ai_consumers registry เท่านั้น
 */
final class AiAssignmentController
{
    public function __construct(private readonly AiAssignmentService $aiAssignmentService)
    {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $includeRevoked = isset($args['include']) && $args['include'] === 'revoked';
        $assignments = $this->aiAssignmentService->listByProject((int) $args['project_id'], $includeRevoked);

        return ApiResponse::success($response, array_map(static fn ($a) => $a->toArray(), $assignments));
    }

    public function assign(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();

        foreach (['ai_consumer_id', 'role_id'] as $required) {
            if (empty($body[$required])) {
                return ApiResponse::error($response, 'VALIDATION_ERROR', "{$required} is required", [], 422);
            }
        }

        try {
            $assignmentId = $this->aiAssignmentService->assign(
                (int) $args['project_id'],
                (int) $body['ai_consumer_id'],
                (int) $body['role_id'],
                $body['purpose'] ?? null,
                (int) $request->getAttribute('user_id')
            );
        } catch (\DomainException $e) {
            return ApiResponse::error($response, $e->getMessage(), 'active AI assignment already exists for this consumer', [], 409);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'project_ai_assignment',
            entityId: $assignmentId,
            afterValue: ['ai_consumer_id' => (int) $body['ai_consumer_id'], 'role_id' => (int) $body['role_id']],
            action: 'ai_assigned'
        );

        return ApiResponse::success($response, ['id' => $assignmentId], [], 201);
    }

    public function revoke(Request $request, Response $response, array $args): Response
    {
        $ok = $this->aiAssignmentService->revoke((int) $args['id'], (int) $request->getAttribute('user_id'));
        if (!$ok) {
            return ApiResponse::error($response, 'NOT_FOUND', 'active AI assignment not found', [], 404);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'project_ai_assignment',
            entityId: (int) $args['id'],
            afterValue: ['revoked' => true],
            action: 'ai_revoked'
        );

        return ApiResponse::success($response, ['id' => (int) $args['id'], 'revoked' => true]);
    }
}
