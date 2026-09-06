<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Auth\PmoisAuthenticationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * LINE Login — ระบบรองรับ LINE เท่านั้น (CTO Constraint #1)
 *
 * M9 UAT Runtime Fix — แยก Web routes และ API routes ชัดเจน:
 *
 *   Web (browser — 302 redirects, ผู้ใช้ไม่เห็น JSON):
 *     GET /                      → ไม่ login: 302 /auth/line ; login แล้ว: 302 dashboard
 *     GET /auth/line             → 302 LINE Authorization URL ทันที
 *     GET /auth/line/callback    → verify + session → 302 dashboard (fail → 302 login?error=)
 *     GET /auth/error            → 302 login page with error
 *     POST /auth/logout          → revoke session + 302 login
 *
 *   API (JSON — สำหรับ API clients):
 *     GET /api/v1/auth/line           → JSON {auth_url, state}
 *     GET /api/v1/auth/line/callback  → JSON (session cookie เดียวกัน)
 *
 * Security (M1 R2 เดิมคงไว้): state one-time + fingerprint cookie + verified ID token
 * + fail-closed PMOIS session
 */
final class LineLoginController
{
    public const OAUTH_COOKIE = 'pmois_oauth_fp';
    public const SESSION_COOKIE = 'pmois_session';

    public function __construct(private readonly PmoisAuthenticationService $authService)
    {
    }

    // ===== Root (M9 UAT Fix Issue 2) =====

    /** GET / — ไม่ login → /auth/line ; login แล้ว → dashboard ; ไม่มี 404 */
    public function root(Request $request, Response $response): Response
    {
        $sessionToken = $this->getCookie($request, self::SESSION_COOKIE);

        if ($sessionToken !== null && $this->authService->resolveSession($sessionToken) !== null) {
            return $this->redirectTo($response, '/app/dashboard.html');
        }

        return $this->redirectTo($response, '/auth/line');
    }

    // ===== Web (browser) — 302 redirects =====

    /** GET /auth/line → 302 LINE Authorization URL ทันที (ไม่มี JSON) */
    public function redirect(Request $request, Response $response): Response
    {
        $fingerprint = bin2hex(random_bytes(32));

        $result = $this->authService->beginLogin($fingerprint);

        return $this->withOAuthCookie($response, $fingerprint)
            ->withHeader('Location', $result['auth_url'])
            ->withStatus(302);
    }

    /** GET /auth/line/callback — browser flow: verify → session → 302 dashboard (fail → 302 login?error=) */
    public function callback(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $state = (string) ($queryParams['state'] ?? '');
        $code = (string) ($queryParams['code'] ?? '');
        $fingerprint = (string) $this->getCookie($request, self::OAUTH_COOKIE);

        if ($state === '' || $code === '') {
            return $this->redirectTo($response, '/app/index.html?error=STATE_INVALID');
        }

        try {
            $result = $this->authService->completeCallback($state, $code, $fingerprint);
        } catch (\App\Domain\Auth\AuthException $e) {
            // Known auth error — error code ผ่านไปตรง ๆ (AUTH_FAILED / UNAUTHORIZED_IDENTITY / ... แยกชัดเจน)
            // รายละเอียด log ฝั่ง server เท่านั้น
            error_log('[PMOIS auth] callback: ' . $e->errorCode . ($e->getMessage() !== $e->errorCode ? ' — ' . $e->getMessage() : ''));
            return $this->redirectTo($response, '/app/index.html?error=' . urlencode($e->errorCode));
        } catch (\Throwable $e) {
            // Unexpected — AUTH_FAILED (login ไม่สำเร็จ) + log internals server-side
            error_log('[PMOIS auth] unexpected ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->redirectTo($response, '/app/index.html?error=AUTH_FAILED');
        }

        $response = $this->withSessionCookie($response, $result['session_token']);
        $response = $this->withExpiredCookie($response, self::OAUTH_COOKIE);

        return $this->redirectTo($response, '/app/projects.html');
    }

    /** GET /auth/error → 302 login page (fail-closed page ไม่พึ่ง session) */
    public function error(Request $request, Response $response): Response
    {
        return $this->redirectTo($response, '/app/index.html?error=UNAUTHORIZED');
    }

    /** POST /auth/logout — revoke session แล้ว 302 login */
    public function logout(Request $request, Response $response): Response
    {
        $sessionToken = $this->getCookie($request, self::SESSION_COOKIE);
        if ($sessionToken !== null) {
            $this->authService->logout($sessionToken);
        }

        return $this->withExpiredCookie($response, self::SESSION_COOKIE)
            ->withHeader('Location', '/app/index.html')
            ->withStatus(302);
    }

    // ===== API (JSON) — /api/v1/auth/* =====

    /** GET /api/v1/auth/line — JSON {auth_url, state} สำหรับ API clients */
    public function apiRedirect(Request $request, Response $response): Response
    {
        $fingerprint = bin2hex(random_bytes(32));

        $result = $this->authService->beginLogin($fingerprint);

        $response = $this->withOAuthCookie($response, $fingerprint);

        return ApiResponse::success($response, [
            'auth_url' => $result['auth_url'],
            'state' => $result['state'],
        ]);
    }

    /** GET /api/v1/auth/line/callback?code=&state= — JSON flow สำหรับ API clients */
    public function apiCallback(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $state = (string) ($queryParams['state'] ?? '');
        $code = (string) ($queryParams['code'] ?? '');
        $fingerprint = (string) $this->getCookie($request, self::OAUTH_COOKIE);

        if ($state === '' || $code === '') {
            return ApiResponse::error($response, 'STATE_INVALID', 'Missing state or authorization code', [], 401);
        }

        try {
            $result = $this->authService->completeCallback($state, $code, $fingerprint);
        } catch (\App\Domain\Auth\AuthException $e) {
            return ApiResponse::error($response, $e->errorCode, $this->errorMessage($e->errorCode), [], $this->errorStatus($e->errorCode));
        } catch (\Throwable $e) {
            error_log('[PMOIS auth] api callback unexpected ' . get_class($e) . ': ' . $e->getMessage());
            return ApiResponse::error($response, 'AUTH_FAILED', 'Authentication failed', [], 401);
        }

        $response = $this->withSessionCookie($response, $result['session_token']);
        $response = $this->withExpiredCookie($response, self::OAUTH_COOKIE);

        return ApiResponse::success($response, [
            'user_id' => $result['user_id'],
            'workspace_id' => $result['workspace_id'],
            'claimed' => $result['claimed'],
            'display_name' => $result['display_name'],
            'picture_url' => $result['picture_url'],
        ]);
    }

    // ===== helpers =====

    private function errorMessage(string $code): string
    {
        return match ($code) {
            'STATE_INVALID' => 'OAuth state missing or unknown',
            'STATE_EXPIRED' => 'OAuth state expired',
            'STATE_MISMATCH' => 'OAuth state does not match this browser',
            'STATE_REUSED' => 'OAuth state already used',
            'ID_TOKEN_INVALID' => 'LINE ID token failed verification',
            'OAUTH_EXCHANGE_FAILED' => 'Authorization code exchange failed',
            'UNAUTHORIZED_IDENTITY' => 'LINE account is not authorized to access PMOIS',
            'CLAIM_TOKEN_INVALID' => 'Claim token invalid or expired',
            'CLAIM_ALREADY_USED' => 'This account has already been claimed',
            'LINE_ALREADY_BOUND' => 'This LINE account is already bound to another PMOIS user',
            default => 'Authentication failed',
        };
    }

    private function errorStatus(string $code): int
    {
        return $code === 'UNAUTHORIZED_IDENTITY' ? 403 : 401;
    }

    private function redirectTo(Response $response, string $location): Response
    {
        return $response->withHeader('Location', $location)->withStatus(302);
    }

    private function getCookie(Request $request, string $name): ?string
    {
        $cookies = $request->getCookieParams();
        return isset($cookies[$name]) && is_string($cookies[$name]) ? $cookies[$name] : null;
    }

    private function cookieFlags(): string
    {
        $debug = (bool) ($GLOBALS['app_env']['APP_DEBUG'] ?? false);

        return 'Path=/; HttpOnly; SameSite=Lax' . ($debug ? '' : '; Secure');
    }

    public function withOAuthCookiePublic(Response $response, string $fingerprint): Response
    {
        return $this->withOAuthCookie($response, $fingerprint);
    }

    private function withOAuthCookie(Response $response, string $fingerprint): Response
    {
        return $response->withAddedHeader(
            'Set-Cookie',
            self::OAUTH_COOKIE . '=' . $fingerprint . '; ' . $this->cookieFlags() . '; Max-Age=600'
        );
    }

    private function withSessionCookie(Response $response, string $sessionToken): Response
    {
        $ttl = 8 * 3600;

        return $response->withAddedHeader(
            'Set-Cookie',
            self::SESSION_COOKIE . '=' . $sessionToken . '; ' . $this->cookieFlags() . '; Max-Age=' . $ttl
        );
    }

    private function withExpiredCookie(Response $response, string $name): Response
    {
        return $response->withAddedHeader('Set-Cookie', $name . '=; ' . $this->cookieFlags() . '; Max-Age=0');
    }
}
