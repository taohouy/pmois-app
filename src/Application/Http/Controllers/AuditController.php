<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Dashboard\DashboardService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Audit API (M5) — permission: audit_trail.view (ADMIN/CTO/PMO_REVIEWER ตาม seed เดิม)
 */
final class AuditController
{
    private const MAX_LIMIT = 100;

    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    /** GET /api/v1/audit-logs?limit=&entity_type=&entity_id= */
    public function index(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $limit = max(1, min(self::MAX_LIMIT, (int) ($query['limit'] ?? 50)));

        $logs = $this->dashboardService->auditLogs(
            $limit,
            isset($query['entity_type']) && $query['entity_type'] !== '' ? (string) $query['entity_type'] : null,
            isset($query['entity_id']) && $query['entity_id'] !== '' ? (int) $query['entity_id'] : null
        );

        return ApiResponse::success($response, $logs);
    }
}
