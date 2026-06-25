<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Auth\ApiTokenRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ApiTokenController
{
    public function __construct(private readonly ApiTokenRepositoryInterface $tokenRepo)
    {
    }

    /**
     * POST /api/v1/auth/tokens
     * Permission: api_token.create
     * Body: { "token_name": string }
     *
     * ⚠️ raw_token แสดงผลแค่ "ครั้งเดียว" ในการตอบกลับนี้ -- DB เก็บแค่ hash
     * เก็บไว้เองให้ดี ถ้าหายต้อง revoke แล้วสร้างใบใหม่ กู้คืนไม่ได้
     */
    public function create(Request $request, Response $response): Response
    {
        $workspaceId = (int) $request->getAttribute('workspace_id');
        $userId = (int) $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        if (empty($body['token_name'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ token_name', [], 422);
        }

        $result = $this->tokenRepo->create(
            workspaceId: $workspaceId,
            createdByUserId: $userId,
            tokenName: (string) $body['token_name'],
            scopes: null,
            aiConsumerId: isset($body['ai_consumer_id']) ? (int) $body['ai_consumer_id'] : null,
            projectId: isset($body['project_id']) ? (int) $body['project_id'] : null
        );

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'api_token',
            entityId: $result['id'],
            afterValue: ['token_name' => $body['token_name']]
        );

        return ApiResponse::success($response, [
            'id' => $result['id'],
            'token_name' => $body['token_name'],
            'project_id' => isset($body['project_id']) ? (int) $body['project_id'] : null,
            'raw_token' => $result['raw_token'],
            'warning' => 'เก็บ raw_token นี้ไว้ตอนนี้เท่านั้น จะไม่แสดงซ้ำอีก',
        ], [], 201);
    }

    /**
     * GET /api/v1/auth/tokens
     * Permission: api_token.view
     */
    public function index(Request $request, Response $response): Response
    {
        $workspaceId = (int) $request->getAttribute('workspace_id');
        $tokens = $this->tokenRepo->listByWorkspace($workspaceId);

        return ApiResponse::success($response, $tokens);
    }

    /**
     * DELETE /api/v1/auth/tokens/{id}
     * Permission: api_token.revoke
     */
    public function revoke(Request $request, Response $response, array $args): Response
    {
        $tokenId = (int) $args['id'];
        $revoked = $this->tokenRepo->revoke($tokenId);

        if (!$revoked) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ token นี้ใน workspace ปัจจุบัน', [], 404);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'api_token',
            entityId: $tokenId,
            action: 'revoke_token'
        );

        return ApiResponse::success($response, ['id' => $tokenId, 'status' => 'revoked']);
    }
}
