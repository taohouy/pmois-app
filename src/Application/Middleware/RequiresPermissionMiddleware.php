<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Identity\PermissionResolver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Routing\RouteContext;

/**
 * RequiresPermissionMiddleware
 *
 * แก้ไข (พบระหว่างต่อ Controller ที่เหลือ): เดิม extractProjectIdFromRequest()
 * อ่านจาก request attribute 'route_arg_project_id' ที่ "ไม่มีใครเคยตั้งค่าจริง"
 * เลย -- แปลว่า project-level role override ไม่เคยถูกเช็คจริงสำหรับ route ที่ผูก
 * กับ project (เช่น /projects/{id}/close) ตกไปใช้ workspace role เสมอ
 *
 * แก้โดยรับชื่อ route argument ที่เป็น project id แบบชัดเจนตอน register route
 * (ไม่เดาเอาเองจาก path) แล้วดึงค่าจริงผ่าน Slim\Routing\RouteContext
 */
final class RequiresPermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly string $permissionCode,
        private readonly ?string $projectIdRouteArgName = null
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $userId = $request->getAttribute('user_id');
        $workspaceId = $request->getAttribute('workspace_id');
        $projectId = $this->extractProjectId($request);

        if ($userId === null) {
            return $this->forbidden('Missing authenticated user context');
        }

        $allowed = $this->resolver->can(
            userId: (int) $userId,
            workspaceId: $workspaceId !== null ? (int) $workspaceId : null,
            projectId: $projectId,
            permissionCode: $this->permissionCode
        );

        if (!$allowed) {
            return $this->forbidden("Missing permission: {$this->permissionCode}");
        }

        return $handler->handle($request);
    }

    private function extractProjectId(Request $request): ?int
    {
        if ($this->projectIdRouteArgName === null) {
            return null; // route นี้ไม่ใช่ permission ที่ผูกกับ project โดยเฉพาะ
        }

        $route = RouteContext::fromRequest($request)->getRoute();
        if ($route === null) {
            return null;
        }

        $value = $route->getArgument($this->projectIdRouteArgName);
        return $value !== null ? (int) $value : null;
    }

    private function forbidden(string $message): Response
    {
        $response = (new ResponseFactory())->createResponse();
        return ApiResponse::error($response, 'FORBIDDEN', $message, [], 403);
    }
}
