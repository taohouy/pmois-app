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

/**
 * RequiresPermissionMiddleware
 *
 * Middleware ที่ผูกกับ route แบบ parameterized ด้วย permission code เดียว
 * (ตาม Phase 0 Specification Package v0.1 หมวด 4.4 — แยกออกจาก Controller
 * เพื่อไม่ให้ logic การเช็คสิทธิ์กระจัดกระจาย)
 *
 * วิธีใช้ตอน register route (ตัวอย่างแนวคิด ไม่ใช่ syntax ตายตัว):
 *   $app->post('/projects', ProjectController::class . ':create')
 *       ->add(new RequiresPermissionMiddleware($resolver, 'project.create'));
 *
 * รองรับ permission พิเศษ 'workspace.create' ที่ bypass ไปเช็ค is_platform_admin
 * ผ่าน PermissionResolver::can() อยู่แล้ว (ไม่ต้องมี logic แยกในมิดเดิลแวร์นี้)
 */
final class RequiresPermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly string $permissionCode
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $userId = $request->getAttribute('user_id');
        $workspaceId = $request->getAttribute('workspace_id');

        // project_id อ่านจาก route argument ถ้ามี (เช่น /projects/{id}/members)
        $route = $request->getAttribute('__route__'); // ขึ้นกับ routing library จริงตอน implement
        $projectId = $this->extractProjectIdFromRequest($request);

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

    /**
     * 🔴 Assumption: วิธี extract project_id จาก route ขึ้นกับ routing library ที่ใช้จริง
     * (Slim 4 ใช้ $request->getAttribute('route')->getArgument('id') ผ่าน Slim\Routing\RouteContext)
     * ใส่ไว้เป็น placeholder ให้ implement ตอนต่อกับ Slim 4 จริง
     */
    private function extractProjectIdFromRequest(Request $request): ?int
    {
        $routeArgProjectId = $request->getAttribute('route_arg_project_id');
        return $routeArgProjectId !== null ? (int) $routeArgProjectId : null;
    }

    private function forbidden(string $message): Response
    {
        $response = (new ResponseFactory())->createResponse();
        return ApiResponse::error($response, 'FORBIDDEN', $message, [], 403);
    }
}
