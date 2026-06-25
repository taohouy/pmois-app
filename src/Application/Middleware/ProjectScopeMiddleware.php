<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Http\Responders\ApiResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Routing\RouteContext;

/**
 * ProjectScopeMiddleware
 *
 * บังคับ project isolation สำหรับ project-scoped token (api_tokens.project_id IS NOT NULL)
 *
 * กฎ:
 * - token_project_id = NULL  → workspace-level token (ADMIN) → ผ่านได้ทุก route
 * - token_project_id = X     → project-scoped token → ต้องมี {project_id} route arg ที่ตรงกับ X
 *                              route ที่ไม่มี project_id arg (workspace-level) ถูก deny ด้วย 403
 */
final class ProjectScopeMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $tokenProjectId = $request->getAttribute('token_project_id');

        if ($tokenProjectId === null) {
            return $handler->handle($request);
        }

        $route = RouteContext::fromRequest($request)->getRoute();
        $routeArgs = $route?->getArguments() ?? [];
        $routeProjectId = isset($routeArgs['project_id']) ? (int) $routeArgs['project_id'] : null;

        if ($routeProjectId === null) {
            return $this->forbidden('Project-scoped token cannot access workspace-level resources');
        }

        if ($routeProjectId !== $tokenProjectId) {
            return $this->forbidden('Token is not authorized for this project');
        }

        return $handler->handle($request);
    }

    private function forbidden(string $message): Response
    {
        $response = (new ResponseFactory())->createResponse();
        return ApiResponse::error($response, 'FORBIDDEN', $message, [], 403);
    }
}
