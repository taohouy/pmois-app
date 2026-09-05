<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * WorkspaceContextMiddleware
 *
 * รันต่อจาก AuthTokenMiddleware เสมอ — ดึง workspace_id ที่ AuthTokenMiddleware
 * แนบมาแล้ว แล้ว "set ลง DI container" เพื่อให้ Repository/Service ที่ฉีด
 * 'current_workspace_id' ผ่าน constructor ได้ค่าถูกต้องต่อ request
 *
 * ⚠️ จุดนี้เคยเป็น gap: ก่อนหน้า middleware นี้ pass-through อย่างเดียว ทำให้
 * container resolve repository ด้วย workspace_id = null ทุก request
 * (controllers resolve หลัง middleware ทำงาน — Slim resolve route callable lazily)
 */
final class WorkspaceContextMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $workspaceId = $request->getAttribute('workspace_id');

        if ($workspaceId !== null) {
            $this->container->set('current_workspace_id', (int) $workspaceId);
        }

        return $handler->handle($request);
    }
}
