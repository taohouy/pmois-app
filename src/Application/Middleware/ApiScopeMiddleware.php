<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * ApiScopeMiddleware (M5 — API Scope Management)
 *
 * Backward Compatible ตาม M0 R5 กฎเดิม (scopes เป็น informational):
 *  - token ที่ **ไม่มี scopes** (legacy) → ผ่านทุก endpoint (พฤติกรรมเดิมไม่เปลี่ยน)
 *  - token ที่ **ระบุ scopes** → ต้องมี scope ที่ endpoint กำหนด ถึงจะผ่าน
 *    (scope ระดับพอร์ต: read/write/ai_context — mapping เป็น config)
 *  - session (Web UI) ไม่ตรวจ scope — ใช้ PermissionResolver อยู่แล้ว
 *
 * Scope catalog (config — เพิ่มได้ที่ SCOPES):
 *   read       — GET endpoints ทั่วไป
 *   write      — POST/PATCH/PUT/DELETE
 *   ai_context — PMO context endpoints สำหรับ AI agent
 */
final class ApiScopeMiddleware implements MiddlewareInterface
{
    public const SCOPES = ['read', 'write', 'ai_context'];

    /** path (regex) => scope พิเศษ — ที่เหลือใช้ method mapping */
    private const PATH_SCOPES = [
        '#^/api/v1/pmo-context#' => 'ai_context',
        '#^/api/v1/governance/summary#' => 'ai_context',
        '#^/api/v1/decisions/recent#' => 'ai_context',
        '#^/api/v1/projects/status#' => 'ai_context',
    ];

    public function process(Request $request, RequestHandler $handler): Response
    {
        $tokenScopes = $request->getAttribute('token_scopes');

        // session / legacy token (ไม่มี scopes) → ผ่าน (backward compatible)
        if ($tokenScopes === null || $tokenScopes === []) {
            return $handler->handle($request);
        }

        $required = $this->requiredScope($request);

        if (!in_array($required, $tokenScopes, true) && !in_array('*', $tokenScopes, true)) {
            $response = (new ResponseFactory())->createResponse(403);
            $response->getBody()->write((string) json_encode([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'SCOPE_INSUFFICIENT', 'message' => "token requires scope '{$required}'", 'details' => []],
                'meta' => ['timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)],
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }

    /**
     * required scope ของ request — public static เพื่อให้ test ตรวจ mapping ได้โดยตรง
     */
    public static function requiredScope(Request $request): string
    {
        $path = $request->getUri()->getPath();

        foreach (self::PATH_SCOPES as $pattern => $scope) {
            if (preg_match($pattern, $path) === 1) {
                return $scope;
            }
        }

        return $request->getMethod() === 'GET' ? 'read' : 'write';
    }
}
