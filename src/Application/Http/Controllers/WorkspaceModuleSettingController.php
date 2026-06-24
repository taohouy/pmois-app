<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Workspace\WorkspaceModuleSettingRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class WorkspaceModuleSettingController
{
    private const VALID_MODULES = ['task_management', 'milestone_tracking', 'risk_issue_tracking'];

    public function __construct(private readonly WorkspaceModuleSettingRepositoryInterface $settingRepo)
    {
    }

    /**
     * GET /api/v1/workspace-module-settings
     * Permission: workspace_module_setting.manage
     */
    public function index(Request $request, Response $response): Response
    {
        $workspaceId = (int) $request->getAttribute('workspace_id');
        $settings = $this->settingRepo->listForWorkspace($workspaceId);

        // เติม module ที่ยังไม่เคยตั้งค่าให้เห็นเป็น "ปิดอยู่" (default) ด้วย ไม่ใช่แค่ที่มี record
        $existing = array_column($settings, 'is_enabled', 'module_code');
        $result = [];
        foreach (self::VALID_MODULES as $code) {
            $result[] = [
                'module_code' => $code,
                'is_enabled' => (bool) ($existing[$code] ?? false),
            ];
        }

        return ApiResponse::success($response, $result);
    }

    /**
     * PUT /api/v1/workspace-module-settings/{module_code}
     * Permission: workspace_module_setting.manage
     * Body: { "is_enabled": bool }
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $workspaceId = (int) $request->getAttribute('workspace_id');
        $userId = (int) $request->getAttribute('user_id');
        $moduleCode = (string) $args['module_code'];

        if (!in_array($moduleCode, self::VALID_MODULES, true)) {
            return ApiResponse::error(
                $response,
                'VALIDATION_ERROR',
                'module_code ไม่ถูกต้อง ต้องเป็นหนึ่งใน: ' . implode(', ', self::VALID_MODULES),
                [],
                422
            );
        }

        $body = (array) $request->getParsedBody();
        if (!array_key_exists('is_enabled', $body)) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ is_enabled', [], 422);
        }

        $isEnabled = (bool) $body['is_enabled'];
        $this->settingRepo->setEnabled($workspaceId, $moduleCode, $isEnabled, $userId);

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'workspace_module_setting',
            entityId: $workspaceId, // ไม่มี id เดี่ยวของ record นี้ที่มีความหมายต่อผู้ดู log มากกว่า workspace_id+module_code
            afterValue: ['module_code' => $moduleCode, 'is_enabled' => $isEnabled],
            action: $isEnabled ? 'enable_module' : 'disable_module'
        );

        return ApiResponse::success($response, ['module_code' => $moduleCode, 'is_enabled' => $isEnabled]);
    }
}
