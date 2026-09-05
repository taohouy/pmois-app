<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Identity\PermissionResolver;
use App\Domain\Registry\AiProviderRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * AI Provider Registry (global) — manage: is_platform_admin เท่านั้น
 * (เหมือน workspace.create — เช็คใน controller เพราะไม่มี role_permissions row)
 */
final class AiProviderController
{
    public function __construct(
        private readonly AiProviderRepositoryInterface $providerRepo,
        private readonly PermissionResolver $permissionResolver,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $providers = $this->providerRepo->listAll();

        return ApiResponse::success($response, array_map(static fn ($p) => $p->toArray(), $providers));
    }

    public function create(Request $request, Response $response): Response
    {
        if (!$this->isPlatformAdmin($request)) {
            return ApiResponse::error($response, 'FORBIDDEN', 'AI provider registry is platform-admin only', [], 403);
        }

        $body = (array) $request->getParsedBody();
        if (empty($body['code']) || empty($body['name'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'code and name are required', [], 422);
        }

        if ($this->providerRepo->findByCode((string) $body['code']) !== null) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'provider code already exists', [], 422);
        }

        $provider = $this->providerRepo->create((string) $body['code'], (string) $body['name'], (int) $request->getAttribute('user_id'));

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(entityType: 'ai_provider', entityId: $provider->id, afterValue: ['code' => $provider->code]);

        return ApiResponse::success($response, $provider->toArray(), [], 201);
    }

    public function updateStatus(Request $request, Response $response, array $args): Response
    {
        if (!$this->isPlatformAdmin($request)) {
            return ApiResponse::error($response, 'FORBIDDEN', 'AI provider registry is platform-admin only', [], 403);
        }

        $body = (array) $request->getParsedBody();
        if (empty($body['status']) || !in_array($body['status'], ['active', 'inactive'], true)) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'status must be active or inactive', [], 422);
        }

        if (!$this->providerRepo->updateStatus((int) $args['id'], (string) $body['status'])) {
            return ApiResponse::error($response, 'NOT_FOUND', 'provider not found', [], 404);
        }

        return ApiResponse::success($response, ['id' => (int) $args['id'], 'status' => $body['status']]);
    }

    private function isPlatformAdmin(Request $request): bool
    {
        return $this->permissionResolver->isPlatformAdmin((int) $request->getAttribute('user_id'));
    }
}
