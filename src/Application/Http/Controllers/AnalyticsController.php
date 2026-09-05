<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Analytics\AnalyticsService;
use App\Domain\Identity\PermissionResolver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Analytics API (M7) — read-only
 * workspace-level: workspace.view • portfolio: is_platform_admin
 */
final class AnalyticsController
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly PermissionResolver $permissionResolver,
    ) {
    }

    /** GET /api/v1/analytics/kpis */
    public function kpis(Request $request, Response $response): Response
    {
        return ApiResponse::success($response, $this->analyticsService->kpis());
    }

    /** GET /api/v1/analytics/milestones?months=12 */
    public function milestones(Request $request, Response $response): Response
    {
        $months = max(1, min(24, (int) ($request->getQueryParams()['months'] ?? 12)));

        return ApiResponse::success($response, $this->analyticsService->milestoneAnalytics($months));
    }

    /** GET /api/v1/analytics/productivity — CTO/Dev productivity metrics */
    public function productivity(Request $request, Response $response): Response
    {
        return ApiResponse::success($response, $this->analyticsService->productivity());
    }

    /** GET /api/v1/analytics/health */
    public function health(Request $request, Response $response): Response
    {
        return ApiResponse::success($response, $this->analyticsService->healthAnalytics());
    }

    /** GET /api/v1/analytics/dependencies */
    public function dependencies(Request $request, Response $response): Response
    {
        return ApiResponse::success($response, $this->analyticsService->dependencyAnalytics());
    }

    /** GET /api/v1/analytics/workspace */
    public function workspace(Request $request, Response $response): Response
    {
        return ApiResponse::success($response, $this->analyticsService->workspaceAnalytics());
    }

    /** GET /api/v1/analytics/report — full workspace report (export/print) */
    public function report(Request $request, Response $response): Response
    {
        return ApiResponse::success($response, $this->analyticsService->report());
    }

    /** GET /api/v1/analytics/portfolio — is_platform_admin เท่านั้น */
    public function portfolio(Request $request, Response $response): Response
    {
        if (!$this->permissionResolver->isPlatformAdmin((int) $request->getAttribute('user_id'))) {
            return ApiResponse::error($response, 'FORBIDDEN', 'portfolio analytics is platform-admin only', [], 403);
        }

        return ApiResponse::success($response, $this->analyticsService->portfolioAnalytics());
    }
}
