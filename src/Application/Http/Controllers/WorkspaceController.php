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
}
