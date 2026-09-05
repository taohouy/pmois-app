<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Identity\PermissionResolver;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * API Platform monitoring (M5) — metrics + health
 */
final class PlatformController
{
    public function __construct(
        private readonly PDO $db,
        private readonly PermissionResolver $permissionResolver,
    ) {
    }

    /** GET /api/v1/platform/metrics — is_platform_admin เท่านั้น */
    public function metrics(Request $request, Response $response): Response
    {
        if (!$this->permissionResolver->isPlatformAdmin((int) $request->getAttribute('user_id'))) {
            return ApiResponse::error($response, 'FORBIDDEN', 'platform metrics is admin only', [], 403);
        }

        $scalar = function (string $sql): int {
            $stmt = $this->db->query($sql);
            $row = $stmt === false ? false : $stmt->fetch(PDO::FETCH_ASSOC);

            return $row !== false ? (int) reset($row) : 0;
        };

        $notifStmt = $this->db->query("SELECT status, COUNT(*) AS c FROM notifications GROUP BY status");
        $notifications = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($notifStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $notifications[(string) $row['status']] = (int) $row['c'];
        }

        $activeTokens = $scalar("SELECT COUNT(*) FROM api_tokens WHERE status = 'active'");
        $activeSessions = $scalar("SELECT COUNT(*) FROM user_sessions WHERE revoked_at IS NULL AND expires_at > NOW()");
        $auditToday = $scalar("SELECT COUNT(*) FROM audit_trails WHERE created_at >= CURDATE()");
        $notificationsToday = $scalar("SELECT COUNT(*) FROM notifications WHERE created_at >= CURDATE()");

        return ApiResponse::success($response, [
            'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'api_tokens' => ['active' => $activeTokens],
            'sessions' => ['active' => $activeSessions],
            'audit_events_today' => $auditToday,
            'notifications' => ['today' => $notificationsToday] + $notifications,
        ]);
    }
}
