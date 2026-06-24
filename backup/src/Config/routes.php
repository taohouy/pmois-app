<?php

declare(strict_types=1);

use App\Application\Http\Controllers\ProjectController;
use App\Application\Http\Controllers\WorkspaceController;
use App\Application\Middleware\AuditLoggingMiddleware;
use App\Application\Middleware\AuthTokenMiddleware;
use App\Application\Middleware\RequiresPermissionMiddleware;
use App\Application\Middleware\WorkspaceContextMiddleware;
use App\Domain\Identity\PermissionResolver;
use Psr\Container\ContainerInterface;
use Slim\App;

/**
 * Route Registration -- Foundation Layer (Phase 0)
 *
 * แก้ไขรอบที่ 2 (พบจากการทดสอบจริงบน server -- audit_trails.workspace_id ยัง NULL
 * แม้แก้ AuthTokenMiddleware/dependencies.php แล้ว): ต้นเหตุจริงคือการเรียก
 * $container->get(AuditLoggingMiddleware::class) แบบ "eager" ตรงนี้ ทำให้ Slim
 * สร้าง AuditTrailRepository (พร้อม workspaceId=null ที่ readonly) ไปตั้งแต่ตอน
 * register route -- ก่อนที่ AuthTokenMiddleware จะมีโอกาสรันเลยด้วยซ้ำ
 *
 * แก้โดยส่ง "ชื่อคลาส" (string) ให้ Slim resolve ผ่าน container เองตอน dispatch
 * จริง (lazy) ซึ่งจะเกิดทีหลัง AuthTokenMiddleware เซ็ตค่าใน container แล้ว
 *
 * แก้ไขรอบแรก: /health เคยโดน AuthTokenMiddleware เช็คด้วยเพราะ middleware เดิม
 * ถูก add() แบบ global ใน public/index.php ซึ่งครอบทุก route -- ย้ายมาผูกกับ
 * route group /api/v1 โดยตรงแทน แล้วให้ /health เป็น route แยกนอก group นี้
 */
return function (App $app, ContainerInterface $container): void {

    // ===== Health check -- อยู่นอก group /api/v1 ที่มี Auth middleware เพื่อให้ "ไม่ต้อง auth" จริง =====
    $app->get('/api/v1/health', function ($request, $response) {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // ===== ทุก route ในกลุ่มนี้ต้องผ่าน Auth + WorkspaceContext + AuditLogging =====
    $app->group('/api/v1', function ($group) use ($container) {

        // ===== Workspace =====
        $group->post('/workspaces', WorkspaceController::class . ':create');

        $group->get('/workspaces/{id}', WorkspaceController::class . ':show')
            ->add(new RequiresPermissionMiddleware(
                $container->get(PermissionResolver::class),
                'workspace.view'
            ));

        // ===== Project =====
        $group->get('/projects', ProjectController::class . ':index')
            ->add(new RequiresPermissionMiddleware(
                $container->get(PermissionResolver::class),
                'project.view'
            ));

        $group->post('/projects', ProjectController::class . ':create')
            ->add(new RequiresPermissionMiddleware(
                $container->get(PermissionResolver::class),
                'project.create'
            ));

        $group->put('/projects/{id}/close', ProjectController::class . ':close')
            ->add(new RequiresPermissionMiddleware(
                $container->get(PermissionResolver::class),
                'project.close'
            ));

        // ===== ส่วนที่เหลือตาม API Endpoint Specification v0.1 หมวด 3 =====
        // workspace-members, project members, workspace-module-settings, auth/tokens
        // ใช้ pattern เดียวกัน เขียนต่อจาก template ของ 2 controller ข้างบนได้ทันที

    })
        // ลำดับการ add() มีผล: Slim รัน middleware ที่ ->add() "หลังสุด" ก่อน (LIFO)
        // ต้องการลำดับจริง: AuthToken -> WorkspaceContext -> (route + RequiresPermission) -> AuditLogging
        ->add(AuditLoggingMiddleware::class)                          // ✅ แก้: ส่งชื่อคลาส ให้ Slim resolve ผ่าน container "ตอน dispatch จริง" ไม่ใช่ตอน register route
        ->add(WorkspaceContextMiddleware::class)
        ->add(new AuthTokenMiddleware($container->get(PDO::class), $container));
};