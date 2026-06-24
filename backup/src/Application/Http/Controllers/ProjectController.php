<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Project\ProjectRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ProjectController
{
    public function __construct(private readonly ProjectRepositoryInterface $projectRepo)
    {
    }

    /**
     * GET /api/v1/projects
     * Permission: project.view (เช็คผ่าน RequiresPermissionMiddleware ที่ผูกกับ route นี้)
     */
    public function index(Request $request, Response $response): Response
    {
        $projects = $this->projectRepo->listByWorkspace();

        $data = array_map(static fn ($p) => [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'status' => $p->status,
        ], $projects);

        return ApiResponse::success($response, $data, [
            'pagination' => ['page' => 1, 'per_page' => count($data), 'total' => count($data)],
        ]);
    }

    /**
     * POST /api/v1/projects
     * Permission: project.create
     */
    public function create(Request $request, Response $response): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        if (empty($body['code']) || empty($body['name'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ code และ name', [], 422);
        }

        $project = $this->projectRepo->create(
            code: $body['code'],
            name: $body['name'],
            description: $body['description'] ?? null,
            ownerUserId: $userId
        );

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'project',
            entityId: $project->id,
            afterValue: ['code' => $project->code, 'name' => $project->name]
        );

        return ApiResponse::success($response, [
            'id' => $project->id,
            'code' => $project->code,
            'name' => $project->name,
            'status' => $project->status,
        ], [], 201);
    }

    /**
     * PUT /api/v1/projects/{id}/close
     * Permission: project.close (แยกจาก project.update ตาม Permission Code List v0.1)
     *
     * ตัวอย่างการใช้ audit_action override ตาม CTO Decision:
     * แทนที่จะ log เป็น "update" เฉยๆ ใช้ "close_project" เพื่อให้ audit log อ่านง่ายขึ้น
     */
    public function close(Request $request, Response $response, array $args): Response
    {
        $projectId = (int) $args['id'];
        $project = $this->projectRepo->findById($projectId);

        if ($project === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ project', [], 404);
        }

        $this->projectRepo->updateStatus($projectId, 'closed');

        // ระบุ action override ตรงๆ ผ่าน AuditContext::record() ตาม CTO Decision
        // (Phase 0 Spec หมวด 7, ตัวอย่าง close_project) แทนการพึ่ง default mapping ('update')
        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'project',
            entityId: $projectId,
            afterValue: ['status' => 'closed'],
            beforeValue: ['status' => $project->status],
            action: 'close_project'
        );

        return ApiResponse::success($response, ['id' => $projectId, 'status' => 'closed']);
    }
}
