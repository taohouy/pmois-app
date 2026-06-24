<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Http\AuditContext;
use App\Domain\Audit\AuditTrailRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * AuditLoggingMiddleware
 *
 * อ่านข้อมูลจาก AuditContext object (ดู AuditContext.php) ไม่ใช่จาก request attribute
 * string-based ตรงๆ -- เพราะ PSR-7 Request immutability ทำให้ attribute ที่ Controller
 * ตั้งผ่าน withAttribute() มองไม่เห็นจาก middleware ชั้นนอกที่ถือ Request ตัวเดิมไว้
 * (พบปัญหานี้ระหว่างเขียน Controller จริง -- ดูรายละเอียดใน AuditContext.php)
 *
 * Audit Action Mapping (CTO Decision -- Phase 0 Specification Package v0.1):
 *   Default:  POST->create, PUT->update, PATCH->update, DELETE->delete
 *   Override: ถ้า Controller เรียก AuditContext::record() พร้อม $action ให้ใช้ค่านั้นแทน
 *             เช่น 'close_project', 'approve_rfc', 'convert_rfc_to_decision'
 *
 * Retention: Keep Forever -- ไม่มี business-level deletion
 */
final class AuditLoggingMiddleware implements MiddlewareInterface
{
    private const DEFAULT_ACTION_MAP = [
        'POST' => 'create',
        'PUT' => 'update',
        'PATCH' => 'update',
        'DELETE' => 'delete',
    ];

    public function __construct(private readonly AuditTrailRepositoryInterface $auditRepo)
    {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);

        $method = $request->getMethod();
        $statusOk = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;

        if (!array_key_exists($method, self::DEFAULT_ACTION_MAP) || !$statusOk) {
            return $response;
        }

        /** @var AuditContext|null $auditContext */
        $auditContext = $request->getAttribute('audit_context');

        if ($auditContext === null || !$auditContext->hasEntity()) {
            // Controller ไม่ได้เรียก record() -- ไม่ log (ดีกว่า log ข้อมูลเปล่าที่ไม่มีความหมาย)
            return $response;
        }

        $userId = $request->getAttribute('user_id');

        // ใช้ action ที่ Controller ระบุผ่าน AuditContext ถ้ามี -- ไม่งั้น fallback ไป default mapping
        $action = $auditContext->getAction() ?? self::DEFAULT_ACTION_MAP[$method];

        $this->auditRepo->record(
            userId: $userId !== null ? (int) $userId : null,
            action: $action,
            entityType: $auditContext->getEntityType(),
            entityId: $auditContext->getEntityId(),
            beforeValue: $auditContext->getBeforeValue(),
            afterValue: $auditContext->getAfterValue(),
            ipAddress: $request->getServerParams()['REMOTE_ADDR'] ?? null,
            userAgent: $request->getHeaderLine('User-Agent') ?: null,
        );

        return $response;
    }
}
