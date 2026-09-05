<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Project\ProjectStructureService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Project Hierarchy — Move Workspace / Change Parent / Promote (permission: project.structure.update)
 * Project ID ไม่มีวันเปลี่ยนในทุก flow (M0 Item 4 constraint)
 */
final class ProjectStructureController
{
    public function __construct(private readonly ProjectStructureService $projectStructureService)
    {
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        $projectId = (int) $args['id'];
        $actorId = (int) $request->getAttribute('user_id');
        $reason = $body['reason'] ?? null;
        $action = (string) ($body['action'] ?? '');

        try {
            switch ($action) {
                case 'move_workspace':
                    if (empty($body['new_workspace_id'])) {
                        return ApiResponse::error($response, 'VALIDATION_ERROR', 'new_workspace_id is required', [], 422);
                    }
                    $this->projectStructureService->moveWorkspace($projectId, (int) $body['new_workspace_id'], $actorId, $reason);
                    break;
                case 'change_parent':
                    $newParentId = isset($body['new_parent_id']) && $body['new_parent_id'] !== null ? (int) $body['new_parent_id'] : null;
                    $this->projectStructureService->changeParent($projectId, $newParentId, $actorId, $reason);
                    break;
                case 'promote':
                    $this->projectStructureService->promoteToRoot($projectId, $actorId, $reason);
                    break;
                default:
                    return ApiResponse::error($response, 'VALIDATION_ERROR', 'action must be move_workspace, change_parent or promote', [], 422);
            }
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'project',
            entityId: $projectId,
            afterValue: ['action' => $action],
            action: 'structure_change'
        );

        return ApiResponse::success($response, ['id' => $projectId, 'action' => $action]);
    }
}
