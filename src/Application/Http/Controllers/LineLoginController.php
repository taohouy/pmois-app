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
 * Security (CTO Review §1):
 *  - state: cryptographically secure, persisted (one-time), bound to browser fingerprint cookie,
 *    verified + marked used ที่ callback
 *  - id_token: verified ผ่าน LINE verify endpoint + iss/aud/exp/iat/nonce checks
 *  - callback สร้าง authenticated PMOIS session (HttpOnly cookie) — fail-closed
 *    หากไม่มี bound user / inactive / ไม่มี active membership
 */
final class LineLoginController
{
    public const OAUTH_COOKIE = 'pmois_oauth_fp';
    public const SESSION_COOKIE = 'pmois_session';

    public function __construct(private readonly PmoisAuthenticationService $authService)
    {
    }

    public function redirect(Request $request, Response $response): Response
    {
        $fingerprint = bin2hex(random_bytes(32));

        $result = $this->authService->beginLogin($fingerprint);

        $response = $this->withOAuthCookie($response, $fingerprint);

        return ApiResponse::success($response, [
            'auth_url' => $result['auth_url'],
            'state' => $result['state'],
        ]);
    }

    public function callback(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $state = (string) ($queryParams['state'] ?? '');
        $code = (string) ($queryParams['code'] ?? '');
        $fingerprint = (string) $this->getCookie($request, self::OAUTH_COOKIE);
        $wantsHtml = $this->wantsHtml($request);

        if ($state === '' || $code === '') {
            return $wantsHtml
                ? $this->redirectApp($response, '/app/index.html?error=STATE_INVALID')
                : ApiResponse::error($response, 'STATE_INVALID', 'Missing state or authorization code', [], 401);
        }

        try {
            $result = $this->authService->completeCallback($state, $code, $fingerprint);
        } catch (\DomainException $e) {
            return $wantsHtml
                ? $this->redirectApp($response, '/app/index.html?error=' . $e->getMessage())
                : $this->authError($response, $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $wantsHtml
                ? $this->redirectApp($response, '/app/index.html?error=ID_TOKEN_INVALID')
                : $this->authError($response, $e->getMessage());
        } catch (\RuntimeException $e) {
            return $wantsHtml
                ? $this->redirectApp($response, '/app/index.html?error=OAUTH_EXCHANGE_FAILED')
                : $this->authError($response, $e->getMessage());
        }

        // authenticated PMOIS session (HttpOnly cookie) + เคลียร์ oauth fingerprint cookie
        $response = $this->withSessionCookie($response, $result['session_token']);
        $response = $this->withExpiredCookie($response, self::OAUTH_COOKIE);

        // Web UI: browser navigation → redirect เข้า app (session cookie พร้อมใช้)
        if ($wantsHtml) {
            return $this->redirectApp($response, '/app/projects.html');
        }

        return ApiResponse::success($response, [
            'user_id' => $result['user_id'],
            'workspace_id' => $result['workspace_id'],
            'claimed' => $result['claimed'],
            'display_name' => $result['display_name'],
            'picture_url' => $result['picture_url'],
        ]);
    }

    public function error(Request $request, Response $response): Response
    {
        return ApiResponse::error($response, 'UNAUTHORIZED', 'LINE user has no active workspace membership', [], 403);
    }

    public function logout(Request $request, Response $response): Response
    {
        $sessionToken = (string) $this->getCookie($request, self::SESSION_COOKIE);
        if ($sessionToken !== '') {
            $this->authService->logout($sessionToken);
        }

        $response = $this->withExpiredCookie($response, self::SESSION_COOKIE);

        return ApiResponse::success($response, ['logged_out' => true]);
    }

    // ===== helpers =====

    /** browser navigation (Accept: text/html) → redirect; API client → JSON (backward compatible) */
    private function wantsHtml(Request $request): bool
    {
        return str_contains($request->getHeaderLine('Accept'), 'text/html');
    }

    private function redirectApp(Response $response, string $path): Response
    {
        return $response->withHeader('Location', $path)->withStatus(302);
    }

    private function authError(Response $response, string $code): Response
    {
        // state/identity failures = 401 (หรือ 403 เมื่อเป็นสิทธิ์) — fail-closed เสมอ
        $permissionCodes = ['UNAUTHORIZED_IDENTITY'];
        $status = in_array($code, $permissionCodes, true) ? 403 : 401;

        $message = match ($code) {
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

        return ApiResponse::error($response, $code, $message, [], $status);
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
