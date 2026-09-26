<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Identity\PermissionResolver;
use App\Domain\Workspace\WorkspaceMemberRepositoryInterface;
use App\Domain\Workspace\WorkspaceRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class WorkspaceController
{
    public function __construct(
        private readonly WorkspaceRepositoryInterface $workspaceRepo,
        private readonly WorkspaceMemberRepositoryInterface $workspaceMemberRepo,
        private readonly PermissionResolver $permissionResolver
    ) {
    }

    public function create(Request $request, Response $response): Response
    {
        $userId = (int) $request->getAttribute('user_id');

        if (!$this->permissionResolver->isPlatformAdmin($userId)) {
            return ApiResponse::error(
                $response,
                'FORBIDDEN',
                'เฉพาะ Platform Admin เท่านั้นที่สร้าง workspace ใหม่ได้',
                [],
                403
            );
        }

        $body = (array) $request->getParsedBody();

        if (empty($body['code']) || empty($body['name'])) {
            return ApiResponse::error(
                $response,
                'VALIDATION_ERROR',
                'ต้องระบุ code และ name',
                [
                    ['field' => 'code', 'message' => 'required'],
                    ['field' => 'name', 'message' => 'required'],
                ],
                422
            );
        }

        $workspace = $this->workspaceRepo->create(
            code: $body['code'],
            name: $body['name'],
            description: $body['description'] ?? null,
            createdByUserId: $userId
        );

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'workspace',
            entityId: $workspace->id,
            afterValue: ['code' => $workspace->code, 'name' => $workspace->name]
        );

        return ApiResponse::success($response, [
            'id' => $workspace->id,
            'code' => $workspace->code,
            'name' => $workspace->name,
            'description' => $workspace->description,
            'status' => $workspace->status,
        ], [], 201);
    }

    /**
     * GET /api/v1/workspaces/{id}
     *
     * แก้ไข (พบช่องโหว่ระหว่างเตรียม test): เพิ่มการเช็ค "ผู้เรียกเป็นสมาชิกของ
     * workspace {id} ที่ขอดูจริงไหม" เพราะ WorkspaceRepository เป็น top-level
     * ไม่ scope ด้วย workspace_id ตาม design (ถูกต้องแล้วในระดับ Repository)
     * แต่ Controller ต้องเป็นคนเช็ค membership เพิ่มเอง ไม่ใช่พึ่ง permission
     * code เพียวๆ เพราะ permission code บอกแค่ "มีสิทธิ์ดู workspace บางอัน"
     * ไม่ได้บอกว่า "มีสิทธิ์ดู workspace อันนี้โดยเฉพาะ"
     *
     * ใช้ 404 (ไม่ใช่ 403) ตอนไม่ใช่สมาชิก เพื่อไม่ leak ว่า workspace id นี้มีอยู่จริง
     */
    public function listAll(Request $request, Response $response): Response
    {
        $workspaces = $this->workspaceRepo->listAll();

        $data = array_map(static fn ($w) => [
            'id' => $w->id,
            'code' => $w->code,
            'name' => $w->name,
            'description' => $w->description,
            'status' => $w->status,
            'created_at' => $w->createdAt,
        ], $workspaces);

        return ApiResponse::success($response, $data, [
            'pagination' => ['page' => 1, 'per_page' => count($data), 'total' => count($data)],
        ]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $targetWorkspaceId = (int) $args['id'];
        $userId = (int) $request->getAttribute('user_id');

        $workspace = $this->workspaceRepo->findById($targetWorkspaceId);

        if ($workspace === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ workspace', [], 404);
        }

        $roleId = $this->workspaceMemberRepo->findRoleIdForUser($targetWorkspaceId, $userId);

        if ($roleId === null) {
            // ไม่ใช่สมาชิก -- ตอบ 404 เหมือนไม่มีอยู่จริง ไม่ตอบ 403 เพื่อไม่ leak การมีอยู่ของ workspace
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ workspace', [], 404);
        }

        return ApiResponse::success($response, [
            'id' => $workspace->id,
            'code' => $workspace->code,
            'name' => $workspace->name,
            'description' => $workspace->description,
            'status' => $workspace->status,
        ]);
    }

    /**
     * PUT /api/v1/workspaces/{id}
     * Permission: workspace.update
     *
     * ใช้สำหรับทั้ง Edit Workspace (name/description/status) และ Activate/Deactivate
     * (ส่งมาเฉพาะ status) จากหน้า Projects UI — ใช้ WorkspaceRepositoryInterface::update()
     * ที่มีอยู่แล้ว ไม่เพิ่ม Repository Method ใหม่
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $workspace = $this->workspaceRepo->findById($id);

        if ($workspace === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ workspace', [], 404);
        }

        $body = (array) $request->getParsedBody();
        $name = !empty($body['name']) ? (string) $body['name'] : $workspace->name;
        $description = array_key_exists('description', $body)
            ? ($body['description'] !== '' ? (string) $body['description'] : null)
            : $workspace->description;
        $status = !empty($body['status']) ? (string) $body['status'] : $workspace->status;

        if (!in_array($status, ['active', 'planning', 'on_hold'], true)) {
            return ApiResponse::error(
                $response,
                'VALIDATION_ERROR',
                'status ไม่ถูกต้อง',
                [['field' => 'status', 'message' => 'must be active, planning, or on_hold']],
                422
            );
        }

        $this->workspaceRepo->update($id, $name, $description, $status);

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'workspace',
            entityId: $id,
            beforeValue: ['name' => $workspace->name, 'description' => $workspace->description, 'status' => $workspace->status],
            afterValue: ['name' => $name, 'description' => $description, 'status' => $status]
        );

        return ApiResponse::success($response, [
            'id' => $id,
            'code' => $workspace->code,
            'name' => $name,
            'description' => $description,
            'status' => $status,
        ]);
    }
}
