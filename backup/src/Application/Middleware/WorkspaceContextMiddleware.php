<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * WorkspaceContextMiddleware
 *
 * รันต่อจาก AuthTokenMiddleware เสมอ — แค่ทำให้ workspace_id ที่ resolve มาแล้ว
 * พร้อมใช้งานสำหรับ DI container ตอนสร้าง Repository instance (constructor injection)
 *
 * หมายเหตุ: endpoint `workspace.create` (Platform Admin only) ไม่ได้ "ข้าม" middleware นี้จริงๆ —
 * workspace_id ที่ได้มาจาก token ของผู้เรียกยังถูกแนบมาตามปกติ เพียงแต่ Controller ของ
 * endpoint นี้ "ไม่ใช้" workspace_id นั้นเลย (เพราะ action คือสร้าง workspace ใหม่ ไม่ใช่
 * แก้ไขของ workspace เดิม) — ตรวจสิทธิ์ผ่าน is_platform_admin ของ user_id ล้วนๆ
 * (ดู Foundation Module Design หมวด 2.3 — ปรับความเข้าใจให้ตรงกับ implementation จริง)
 */
final class WorkspaceContextMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        // workspace_id ถูกแนบไว้แล้วโดย AuthTokenMiddleware — ตรงนี้แค่เป็นจุดยืนยัน/ขยายในอนาคต
        // (เช่น ถ้าต้องเพิ่ม logic เช็ค workspace.status = 'active' ก่อนอนุญาต ก็มาเพิ่มที่นี่)
        return $handler->handle($request);
    }
}
