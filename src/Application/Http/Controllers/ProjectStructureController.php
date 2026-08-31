<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Project\ProjectStructureService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final class ProjectStructureController implements RequestHandler
{
    public function __construct(private readonly ProjectStructureService $projectStructureService)
    {
    }

    public function handle(Request $request): Response
    {
        $projectId = (int) $request->getAttribute('project_id');
        $actorId = (int) $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        $action = $data['action'] ?? '';

        switch ($action) {
            case 'move_workspace':
                $newWorkspaceId = (int) ($data['new_workspace_id'] ?? 0);
                $reason = $data['reason'] ?? null;
                $this->projectStructureService->moveWorkspace($request->getAttribute('project_id'), $newWorkspaceId, $request->getAttribute('user_id'), $reason);
                return ApiResponse::success(new \stdClass(), 'Project moved to new workspace');
            case 'change_parent':
                $newParentId = $data['new_parent_id'] !== null ? (int) $data['new_parent_id'] : null;
                $reason = $data['reason'] ?? null;
                $this->projectStructureService->changeParent($request->getAttribute('project_id'), $newParentId, $request->getAttribute('user_id'), $reason);
                return ApiResponse::success(new \stdClass(), 'Project parent changed');
            case 'promote':
                $reason = $data['reason'] ?? null;
                $this->projectStructureService->promoteToRoot($request->getAttribute('project_id'), $request->getAttribute('user_id'), $reason);
                return ApiResponse::success(new \stdClass(), 'Project promoted to workspace root');
            default:
                return ApiResponse::error('INVALID_ACTION', 'Invalid action', [], 400);
        }
    }
}