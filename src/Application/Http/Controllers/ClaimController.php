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

        // DIAGNOSTIC: log claim flow start
        error_log('[PMOIS auth DIAG] claim/start: claim_token_present=' . ($claimToken !== '' ? 'yes' : 'no')
            . ' fingerprint_generated=yes');

        try {
            $result = $this->authService->beginClaim($fingerprint, $claimToken);
        } catch (\App\Domain\Auth\AuthException $e) {
            error_log('[PMOIS auth DIAG] claim/start: AuthException=' . $e->errorCode . ' — ' . $e->getMessage());
            return $response
                ->withHeader('Location', '/app/index.html?error=' . urlencode($e->errorCode))
                ->withStatus(302);
        }

        $response = $this->lineLoginController->withOAuthCookiePublic($response, $fingerprint);

        // DIAGNOSTIC: log redirect to LINE
        $cookieHeaders = $response->getHeader('Set-Cookie');
        error_log('[PMOIS auth DIAG] claim/start: redirect to LINE auth_url=' . substr($result['auth_url'], 0, 100)
            . ' set_cookie_count=' . count($cookieHeaders)
            . ' set_cookie_has_oauth=' . (count($cookieHeaders) > 0 ? 'yes' : 'no'));

        // 302 ไป LINE Authorization ทันที — ผู้ใช้ไม่เห็น JSON
        return $response->withHeader('Location', $result['auth_url'])->withStatus(302);
    }
}
