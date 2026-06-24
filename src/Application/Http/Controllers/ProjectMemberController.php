<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Identity\RoleRepositoryInterface;
use App\Domain\Project\ProjectMemberRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ProjectMemberController
{
    public function __construct(
        private readonly ProjectMemberRepositoryInterface $memberRepo,
        private readonly RoleRepositoryInterface $roleRepo
    ) {
    }

    /**
     * GET /api/v1/projects/{project_id}/members
     * Permission: project.view
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        $projectId = (int) $args['project_id'];
        $members = $this->memberRepo->listMembers($projectId);

        return ApiResponse::success($response, $members);
    }

    /**
     * POST /api/v1/projects/{project_id}/members
     * Permission: project_member.manage
     * Body: { "user_id": int, "role_code": string }
     */
    public function add(Request $request, Response $response, array $args): Response
    {
        $projectId = (int) $args['project_id'];
        $body = (array) $request->getParsedBody();

        if (empty($body['user_id']) || empty($body['role_code'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ user_id และ role_code', [], 422);
        }

        $role = $this->roleRepo->findByCode((string) $body['role_code']);
        if ($role === null) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'role_code ไม่ถูกต้อง', [], 422);
        }

        // addMember() ใน Repository เช็คอยู่แล้วว่า project นี้อยู่ใน workspace context จริง
        // (ดู MySqlProjectMemberRepository::assertProjectBelongsToCurrentWorkspace)
        try {
            $this->memberRepo->addMember($projectId, (int) $body['user_id'], $role->id);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ project นี้ใน workspace ปัจจุบัน', [], 404);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'project_member',
            entityId: (int) $body['user_id'],
            afterValue: ['project_id' => $projectId, 'role_code' => $role->code]
        );

        return ApiResponse::success($response, [
            'project_id' => $projectId,
            'user_id' => (int) $body['user_id'],
            'role_code' => $role->code,
        ], [], 201);
    }

    /**
     * DELETE /api/v1/projects/{project_id}/members/{user_id}
     * Permission: project_member.manage
     */
    public function remove(Request $request, Response $response, array $args): Response
    {
        $projectId = (int) $args['project_id'];
        $targetUserId = (int) $args['user_id'];

        try {
            $removed = $this->memberRepo->removeMember($projectId, $targetUserId);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ project นี้ใน workspace ปัจจุบัน', [], 404);
        }

        if (!$removed) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบสมาชิกนี้ใน project', [], 404);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'project_member',
            entityId: $targetUserId,
            action: 'remove_project_member'
        );

        return ApiResponse::success($response, ['project_id' => $projectId, 'user_id' => $targetUserId, 'status' => 'removed']);
    }
}
