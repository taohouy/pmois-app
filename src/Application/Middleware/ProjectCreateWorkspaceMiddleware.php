<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Workspace\WorkspaceRepositoryInterface;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * ProjectCreateWorkspaceMiddleware (M3 Completion Gate — CTO Decision Round 6)
 *
 * ผูกเฉพาะ POST /api/v1/projects เท่านั้น (เช็ค method+path เอง — group middleware
 * ตัวนี้เป็น no-op ทันทีสำหรับ route อื่นทุกตัว ไม่กระทบ route อื่นแม้แต่น้อย)
 *
 * Business Rule ที่ CTO อนุมัติ: ผู้ใช้อยู่ Workspace Tab ไหนแล้วกด "+ เพิ่มโครงการ"
 * โครงการต้องถูกสร้างเข้า Workspace นั้นจริง ไม่ใช่ workspace ของ session เสมอไป
 *
 * วิธีทำโดยไม่แตะ ProjectCreationPipeline หรือ repository ทั้ง ~8 ตัวที่มันเรียกต่อ
 * (ทุกตัว extends BaseRepository ผูก $workspaceId ที่ constructor ตายตัว โดยรับค่าจาก
 * DI container key 'current_workspace_id' — ดู BaseRepository::__construct()):
 *
 * Slim resolve route callable (เช่น ProjectController) "lazily" — ต่อเมื่อ middleware
 * ทั้ง chain (group ทั้งหมด แล้วค่อยถึง route-specific) เรียก $handler->handle() จน
 * สุดแล้วเท่านั้น (ดู comment ใน WorkspaceContextMiddleware ที่ยืนยันพฤติกรรมนี้)
 * ดังนั้นถ้า middleware ตัวนี้ set container['current_workspace_id'] ทับด้วยค่าที่
 * validate แล้วก่อนเรียก $handler->handle() ต่อ ตอน Slim resolve ProjectController
 * (และทั้ง dependency tree ของ ProjectCreationPipeline) จริง จะได้ค่าที่ถูก override
 * แล้วทันที — ไม่ต้องแก้ repository หรือ pipeline แม้แต่ตัวเดียว ไม่แตะ Frozen API
 * Contract ใดๆ
 *
 * ⚠️ ตำแหน่งใน chain สำคัญมาก (พบจากการทดสอบจริง ไม่ใช่แค่ทฤษฎี): middleware ตัวนี้
 * ต้องถูก add "ทันทีหลัง" AuthTokenMiddleware เท่านั้น (ดู routes.php) — ตอนแรกวางไว้
 * แค่ก่อน AuditLoggingMiddleware (ดูเหมือนพอ เพราะเป็นตัวที่ต้องการแก้ audit_trails)
 * แต่พบว่า AiAccessControlMiddleware (รันก่อน WorkspaceContextMiddleware อยู่แล้ว
 * ตามลำดับเดิม) มี AuditTrailRepositoryInterface เป็น constructor dependency ของ
 * ตัวเอง (ใช้บันทึก ai_access_denied) — PHP-DI cache instance ของทุก entry ไว้ใช้ซ้ำ
 * ตลอด request เดียวกันไม่ว่าจะถูก resolve จากจุดไหนก่อนก็ตาม ถ้า override สายเกินไป
 * (แม้จะก่อน AuditLoggingMiddleware เองก็ตาม) audit_trails ของ project_created จะยัง
 * ได้ workspace เดิมของ session อยู่ดี เพราะ AuditTrailRepositoryInterface ถูก resolve
 * (และ cache) ไปแล้วตั้งแต่ AiAccessControlMiddleware ก่อนหน้านั้น จึงต้อง override ให้
 * เร็วที่สุดเท่าที่เป็นไปได้ในทั้ง pipeline — ทันทีที่ AuthTokenMiddleware set
 * workspace_id ของ session เสร็จ ก่อน repository ใดๆ ที่ผูกกับ 'current_workspace_id'
 * จะถูก resolve เป็นตัวแรกโดยไม่ตั้งใจจากที่อื่น
 *
 * Security: ไม่เชื่อ body.workspace_id ตรงๆ โดยไม่ validate — เช็คว่า workspace
 * ปลายทางมีอยู่จริงก่อน (404 ถ้าไม่มี) แล้วปล่อยให้ RequiresPermissionMiddleware
 * (route-specific, รันหลัง middleware ตัวนี้เสมอ) เช็ค permission 'project.create'
 * กับ workspace_id attribute ที่ถูก override แล้ว — ทำให้ permission check ตรวจกับ
 * workspace ปลายทางจริง ไม่ใช่ workspace เดิมของ session (ซึ่งจะผิดถ้าผู้ใช้มีสิทธิ์ใน
 * workspace ปลายทางแต่ไม่มีสิทธิ์ใน workspace ของ session)
 */
final class ProjectCreateWorkspaceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Container $container,
        private readonly WorkspaceRepositoryInterface $workspaceRepo,
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() !== 'POST' || $request->getUri()->getPath() !== '/api/v1/projects') {
            return $handler->handle($request);
        }

        $body = (array) $request->getParsedBody();

        if (empty($body['workspace_id'])) {
            // ไม่ระบุ = พฤติกรรมเดิมทุกประการ สร้างเข้า workspace ของ session
            return $handler->handle($request);
        }

        $targetWorkspaceId = (int) $body['workspace_id'];
        $sessionWorkspaceId = $request->getAttribute('workspace_id');

        if ($sessionWorkspaceId !== null && $targetWorkspaceId === (int) $sessionWorkspaceId) {
            // ระบุมาแต่ตรงกับ session อยู่แล้ว — ไม่ต้อง override อะไร
            return $handler->handle($request);
        }

        $targetWorkspace = $this->workspaceRepo->findById($targetWorkspaceId);
        if ($targetWorkspace === null) {
            return ApiResponse::error(
                (new ResponseFactory())->createResponse(),
                'NOT_FOUND',
                'ไม่พบพื้นที่ทำงานที่ระบุ',
                [],
                404
            );
        }

        // ผ่าน existence check แล้ว — override workspace context ของ request นี้ทั้งหมด
        // (permission ตัวจริงเช็คต่อโดย RequiresPermissionMiddleware ที่ผูกกับ route นี้)
        $this->container->set('current_workspace_id', $targetWorkspaceId);
        $request = $request->withAttribute('workspace_id', $targetWorkspaceId);

        return $handler->handle($request);
    }
}
