<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Auth\PmoisAuthenticationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Claim flow (CTO Review 1.4) — bind ได้จาก LINE identity ที่ระบบยืนยันแล้วเท่านั้น
 *
 * GET /claim/{token} = เริ่ม claim: validate token -> persist claim state -> คืน LINE auth_url
 * Binding เกิดที่ /auth/line/callback เท่านั้น (sub มาจาก verified id_token)
 * ไม่มี endpoint ใดรับ line_user_id จาก client อีกต่อไป
 */
final class ClaimController
{
    public function __construct(
        private readonly PmoisAuthenticationService $authService,
        private readonly LineLoginController $lineLoginController,
    ) {
    }

    public function start(Request $request, Response $response, array $args): Response
    {
        $claimToken = (string) $args['token'];
        $fingerprint = bin2hex(random_bytes(32));

        try {
            $result = $this->authService->beginClaim($fingerprint, $claimToken);
        } catch (\App\Domain\Auth\AuthException $e) {
            // browser flow — กลับ login page พร้อม error code (ไม่มี JSON)
            return $this->lineLoginController->redirect($response, '/app/index.html?error=' . urlencode($e->errorCode));
        }

        $response = $this->lineLoginController->withOAuthCookiePublic($response, $fingerprint);

        // 302 ไป LINE Authorization ทันที — ผู้ใช้ไม่เห็น JSON
        return $response->withHeader('Location', $result['auth_url'])->withStatus(302);
    }
}
