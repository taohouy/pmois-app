<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Audit\AuditTrailRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * AiAccessControlMiddleware
 *
 * Deny-by-default ตาม CTO Decision (Phase 3 Specification Package หมวด 2.4):
 *   1. AI token (ai_consumer_id IS NOT NULL) ต้องเป็น GET เท่านั้น
 *   2. AI token ต้องเรียกได้แค่ path ใน AI_ALLOWED_PATHS เท่านั้น
 *   3. ทั้ง 2 กฎนี้ทำงาน "ไม่พึ่ง permission config เลย" -- เป็น defense-in-depth
 *      แยกชั้นจาก PermissionResolver ทั่วไป แม้ AI consumer จะถูก assign role ที่มี
 *      สิทธิ์เขียนข้อมูลได้ (human error) ก็ยัง reject อยู่ดี
 *   4. ทุกครั้งที่ reject ต้องบันทึก audit_trails (action='ai_access_denied')
 *
 * ตำแหน่งใน pipeline: ต้องรันทันทีหลัง AuthTokenMiddleware (ก่อน WorkspaceContext/route)
 * เพื่อบล็อกเร็วที่สุด ไม่ปล่อยให้ไปถึง Controller เลยถ้าไม่ผ่าน
 */
final class AiAccessControlMiddleware implements MiddlewareInterface
{
    /**
     * Allowlist พาธที่ AI token เรียกได้ -- path ใหม่ในอนาคตจะถูก reject โดย default
     * จนกว่าจะถูกเพิ่มเข้ามาตรงนี้อย่างชัดเจน (deny-by-default จริง)
     */
    private const AI_ALLOWED_PATHS = [
        '/api/v1/pmo-context',
        '/api/v1/projects/status',
        '/api/v1/governance/summary',
        '/api/v1/decisions/recent',
    ];

    public function __construct(private readonly AuditTrailRepositoryInterface $auditRepo)
    {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $aiConsumerId = $request->getAttribute('ai_consumer_id');

        // ไม่ใช่ AI token (human token ปกติ) -- ผ่านไปเลย ไม่เกี่ยวกับ middleware นี้
        if ($aiConsumerId === null) {
            return $handler->handle($request);
        }

        $userId = $request->getAttribute('user_id');
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        if ($method !== 'GET') {
            $this->logDenial($userId, $aiConsumerId, $path, $method, 'non_get_method');
            return $this->forbidden('AI token ใช้ได้เฉพาะ read-only operation (GET) เท่านั้น');
        }

        if (!in_array($path, self::AI_ALLOWED_PATHS, true)) {
            $this->logDenial($userId, $aiConsumerId, $path, $method, 'path_not_allowed');
            return $this->forbidden('AI token ไม่มีสิทธิ์เข้าถึง endpoint นี้ (deny-by-default)');
        }

        return $handler->handle($request);
    }

    private function logDenial(mixed $userId, int $aiConsumerId, string $path, string $method, string $reason): void
    {
        $this->auditRepo->record(
            userId: $userId !== null ? (int) $userId : null,
            action: 'ai_access_denied',
            entityType: 'ai_consumer',
            entityId: $aiConsumerId,
            beforeValue: null,
            afterValue: ['path' => $path, 'method' => $method, 'reason' => $reason],
            ipAddress: null,
            userAgent: null,
        );
    }

    private function forbidden(string $message): Response
    {
        $response = (new ResponseFactory())->createResponse();
        return ApiResponse::error($response, 'FORBIDDEN', $message, [], 403);
    }
}
