<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Project\ProjectTeamAssignmentService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Project Team Registry — ledger + history (permission: project.team.manage สำหรับ write)
 */
final class TeamAssignmentController
{
    public function __construct(private readonly ProjectTeamAssignmentService $teamService)
    {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $projectId = (int) $args['project_id'];
        $includeRevoked = isset($args['include']) && $args['include'] === 'revoked';

        $assignments = $this->teamService->listByProject($projectId, $includeRevoked);

        return ApiResponse::success($response, array_map(static fn ($a) => $a->toArray(), $assignments));
    }

    public function assign(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();

        foreach (['user_id', 'role_id'] as $required) {
            if (empty($body[$required])) {
                return ApiResponse::error($response, 'VALIDATION_ERROR', "{$required} is required", [], 422);
            }
        }

        try {
            $assignmentId = $this->teamService->assign(
                (int) $args['project_id'],
                (int) $body['user_id'],
                (int) $body['role_id'],
                (string) ($body['assignment_source'] ?? 'direct'),
                $body['note'] ?? null,
                (int) $request->getAttribute('user_id')
            );
        } catch (\DomainException $e) {
            return ApiResponse::error($response, $e->getMessage(), 'active assignment already exists for this user', [], 409);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'project_member_assignment',
            entityId: $assignmentId,
            afterValue: ['user_id' => (int) $body['user_id'], 'role_id' => (int) $body['role_id']],
            action: 'team_assigned'
        );

        return ApiResponse::success($response, ['id' => $assignmentId], [], 201);
    }

    public function revoke(Request $request, Response $response, array $args): Response
    {
        $ok = $this->teamService->revoke((int) $args['id'], (int) $request->getAttribute('user_id'));
        if (!$ok) {
            return ApiResponse::error($response, 'NOT_FOUND', 'active assignment not found', [], 404);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'project_member_assignment',
            entityId: (int) $args['id'],
            afterValue: ['revoked' => true],
            action: 'team_revoked'
        );

        return ApiResponse::success($response, ['id' => (int) $args['id'], 'revoked' => true]);
    }
}
