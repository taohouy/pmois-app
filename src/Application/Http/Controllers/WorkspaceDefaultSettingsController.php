<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Registry\WorkspaceDefaultSettingsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Workspace Default Settings — GET: ใครก็ได้ใน workspace, PUT: workspace.settings.manage (route level)
 */
final class WorkspaceDefaultSettingsController
{
    public function __construct(private readonly WorkspaceDefaultSettingsService $settingsService)
    {
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $settings = $this->settingsService->get((int) $args['id']);

        return ApiResponse::success($response, $settings !== null ? $settings->toArray() : null);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();

        try {
            $settings = $this->settingsService->upsert((int) $args['id'], $body, (int) $request->getAttribute('user_id'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'workspace_default_settings',
            entityId: $settings->id,
            afterValue: $settings->toArray(),
            action: 'workspace_defaults_updated'
        );

        return ApiResponse::success($response, $settings->toArray());
    }
}
