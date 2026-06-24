<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Identity\RoleRepositoryInterface;
use App\Domain\Workspace\WorkspaceMemberRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * WorkspaceMemberController
 *
 * หมายเหตุ: route ใช้ {user_id} เป็น path param แทน {id} ทั่วไป เพราะ
 * workspace_members ไม่มี "id เดี่ยว" ที่ Repository รองรับ query ตรงๆ
 * (คีย์จริงคือ workspace_id+user_id คู่กัน) -- เขียนตรงกับ Repository ที่มีอยู่
 * แทนการเติม method ใหม่ที่ไม่จำเป็น
 */
final class WorkspaceMemberController
{
    public function __construct(
        private readonly WorkspaceMemberRepositoryInterface $memberRepo,
        private readonly RoleRepositoryInterface $roleRepo
    ) {
    }

    /**
     * GET /api/v1/workspace-members
     * Permission: workspace.view (ดูสมาชิกถือเป็นส่วนหนึ่งของดูข้อมูล workspace)
     */
    public function index(Request $request, Response $response): Response
    {
        $workspaceId = (int) $request->getAttribute('workspace_id');
        $members = $this->memberRepo->listMembers($workspaceId);

        return ApiResponse::success($response, $members);
    }

    /**
     * POST /api/v1/workspace-members
     * Permission: workspace_member.invite
     * Body: { "user_id": int, "role_code": string }
     */
    public function invite(Request $request, Response $response): Response
    {
        $workspaceId = (int) $request->getAttribute('workspace_id');
        $body = (array) $request->getParsedBody();

        if (empty($body['user_id']) || empty($body['role_code'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ user_id และ role_code', [], 422);
        }

        $role = $this->roleRepo->findByCode((string) $body['role_code']);
        if ($role === null) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'role_code ไม่ถูกต้อง', [], 422);
        }

        $this->memberRepo->addMember($workspaceId, (int) $body['user_id'], $role->id);

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'workspace_member',
            entityId: (int) $body['user_id'],
            afterValue: ['role_code' => $role->code]
        );

        return ApiResponse::success($response, [
            'user_id' => (int) $body['user_id'],
            'role_code' => $role->code,
        ], [], 201);
    }

    /**
     * PUT /api/v1/workspace-members/{user_id}
     * Permission: workspace_member.update_role
     * Body: { "role_code": string }
     */
    public function updateRole(Request $request, Response $response, array $args): Response
    {
        $workspaceId = (int) $request->getAttribute('workspace_id');
        $targetUserId = (int) $args['user_id'];
        $body = (array) $request->getParsedBody();

        if (empty($body['role_code'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ role_code', [], 422);
        }

        $role = $this->roleRepo->findByCode((string) $body['role_code']);
        if ($role === null) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'role_code ไม่ถูกต้อง', [], 422);
        }

        $updated = $this->memberRepo->updateRole($workspaceId, $targetUserId, $role->id);
        if (!$updated) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบสมาชิกนี้ใน workspace', [], 404);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'workspace_member',
            entityId: $targetUserId,
            afterValue: ['role_code' => $role->code]
        );

        return ApiResponse::success($response, ['user_id' => $targetUserId, 'role_code' => $role->code]);
    }

    /**
     * DELETE /api/v1/workspace-members/{user_id}
     * Permission: workspace_member.remove
     */
    public function remove(Request $request, Response $response, array $args): Response
    {
        $workspaceId = (int) $request->getAttribute('workspace_id');
        $targetUserId = (int) $args['user_id'];

        $removed = $this->memberRepo->removeMember($workspaceId, $targetUserId);
        if (!$removed) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบสมาชิกนี้ใน workspace', [], 404);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'workspace_member',
            entityId: $targetUserId,
            action: 'remove_member'
        );

        return ApiResponse::success($response, ['user_id' => $targetUserId, 'status' => 'removed']);
    }
}
