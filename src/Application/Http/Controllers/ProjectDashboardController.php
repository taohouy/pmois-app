<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Dashboard\DashboardService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Project-level Dashboard API (M2) — Project / Parent(children roll-up) / Timeline
 */
final class ProjectDashboardController
{
    private const MAX_LIMIT = 50;

    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    /** GET /api/v1/projects/{project_id}/dashboard — project dashboard + children roll-up เมื่อเป็น parent */
    public function show(Request $request, Response $response, array $args): Response
    {
        $dashboard = $this->dashboardService->projectDashboard((int) $args['project_id']);
        if ($dashboard === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'project not found in this workspace', [], 404);
        }

        return ApiResponse::success($response, $dashboard);
    }

    /** GET /api/v1/projects/{project_id}/timeline?limit= — merged event timeline */
    public function timeline(Request $request, Response $response, array $args): Response
    {
        $projectId = (int) $args['project_id'];
        $limit = $this->limitFromQuery($request);

        $timeline = $this->dashboardService->projectTimeline($projectId, $limit);

        // fail-closed: project ต้องอยู่ใน workspace นี้
        if ($timeline === [] && $this->dashboardService->projectDashboard($projectId) === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'project not found in this workspace', [], 404);
        }

        return ApiResponse::success($response, $timeline);
    }

    /** GET /api/v1/projects/{project_id}/activities?limit= — Project Activity History (M3) */
    public function activities(Request $request, Response $response, array $args): Response
    {
        $limit = $this->limitFromQuery($request);
        $activities = $this->dashboardService->projectActivities((int) $args['project_id'], $limit);

        if ($activities === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'project not found in this workspace', [], 404);
        }

        return ApiResponse::success($response, $activities);
    }

    private function limitFromQuery(Request $request): int
    {
        $raw = $request->getQueryParams()['limit'] ?? null;
        if ($raw === null || !is_numeric($raw)) {
            return DashboardService::DEFAULT_TIMELINE_LIMIT;
        }

        return max(1, min(self::MAX_LIMIT, (int) $raw));
    }
}
