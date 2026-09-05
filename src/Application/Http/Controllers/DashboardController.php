<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Dashboard\DashboardService;
use App\Domain\Identity\PermissionResolver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Dashboard API (M2 — API First, read-only)
 *
 *  - workspace-level endpoints: workspace.view (สมาชิก workspace)
 *  - portfolio endpoint: is_platform_admin เท่านั้น (มุมมอง CEO/Portfolio Owner — cross-workspace)
 */
final class DashboardController
{
    private const MAX_LIMIT = 50;

    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly PermissionResolver $permissionResolver,
    ) {
    }

    /** GET /api/v1/dashboards/workspace */
    public function workspace(Request $request, Response $response): Response
    {
        return ApiResponse::success($response, $this->dashboardService->workspaceDashboard());
    }

    /** GET /api/v1/dashboards/portfolio — is_platform_admin เท่านั้น */
    public function portfolio(Request $request, Response $response): Response
    {
        if (!$this->permissionResolver->isPlatformAdmin((int) $request->getAttribute('user_id'))) {
            return ApiResponse::error($response, 'FORBIDDEN', 'Portfolio dashboard is platform-admin only', [], 403);
        }

        return ApiResponse::success($response, $this->dashboardService->portfolioDashboard());
    }

    /** GET /api/v1/dashboards/progress-summary */
    public function progressSummary(Request $request, Response $response): Response
    {
        return ApiResponse::success($response, $this->dashboardService->progressSummary());
    }

    /** GET /api/v1/dashboards/health-summary */
    public function healthSummary(Request $request, Response $response): Response
    {
        return ApiResponse::success($response, $this->dashboardService->healthSummary());
    }

    /** GET /api/v1/dashboards/statistics */
    public function statistics(Request $request, Response $response): Response
    {
        return ApiResponse::success($response, $this->dashboardService->statistics());
    }

    /** GET /api/v1/dashboards/recent-activities?limit= */
    public function recentActivities(Request $request, Response $response): Response
    {
        $limit = $this->limitFromQuery($request);

        return ApiResponse::success($response, $this->dashboardService->recentActivities($limit));
    }

    private function limitFromQuery(Request $request): int
    {
        $raw = $request->getQueryParams()['limit'] ?? null;
        if ($raw === null || !is_numeric($raw)) {
            return DashboardService::DEFAULT_ACTIVITY_LIMIT;
        }

        return max(1, min(self::MAX_LIMIT, (int) $raw));
    }
}
