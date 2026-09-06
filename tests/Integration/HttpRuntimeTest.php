<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Http\AppErrorMiddleware;
use App\Application\Http\Controllers\LineLoginController;
use App\Domain\Auth\PmoisAuthenticationService;
use App\Infrastructure\Http\HttpClientInterface;
use App\Infrastructure\Persistence\MySQL\MySqlOAuthStateRepository;
use App\Infrastructure\Persistence\MySQL\MySqlUserRepository;
use App\Infrastructure\Persistence\MySQL\MySqlUserSessionRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * HttpRuntimeTest — M9 UAT Runtime Fix (CTO UAT Defects Issue 1–4)
 *
 * ทดสอบผ่าน Slim App จริง (routes + middleware เดียวกับ production):
 *  - GET / ก่อน login → 302 /auth/line ; หลัง login → 302 dashboard
 *  - GET /auth/line (web) → 302 LINE Authorization (ไม่มี JSON)
 *  - GET /api/v1/auth/line (api) → JSON
 *  - Callback สำเร็จ → 302 dashboard + session cookie
 *  - Invalid route → generic 404 ไม่มี stack trace
 *  - Runtime exception → generic 500 ไม่มี internals
 *  - APP_DEBUG=false ต้องไม่เปิดเผย internal information
 */
final class HttpRuntimeTest extends TestCase
{
    private const CHANNEL_ID = '1234567890';
    private const VERIFY_URL_FRAGMENT = 'oauth2/v2.1/verify';

    private PDO $db;
    private int $workspaceId;
    private int $userId;
    private LineLoginController $controller;
    private PmoisAuthenticationService $auth;
    public ?\Closure $httpHandler = null;

    protected function setUp(): void
    {
        $GLOBALS['app_env']['APP_SECRET'] = 'test-secret';
        $GLOBALS['app_env']['APP_DEBUG'] = false;

        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $stmt = $this->db->prepare('INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES ("rt-user", :email, NULL, "active", 0)');
        $stmt->execute(['email' => 'rt-' . uniqid() . '@test.local']);
        $this->userId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, "RT WS", "active", :user)');
        $stmt->execute(['code' => 'rt-ws-' . uniqid(), 'user' => $this->userId]);
        $this->workspaceId = (int) $this->db->lastInsertId();

        $memberRole = (int) $this->db->query("SELECT id FROM roles WHERE code = 'MEMBER' LIMIT 1")->fetchColumn();
        $stmt = $this->db->prepare(
            "INSERT INTO workspace_members (workspace_id, user_id, role_id, status) VALUES (:ws, :user, :role, 'active')"
        );
        $stmt->execute(['ws' => $this->workspaceId, 'user' => $this->userId, 'role' => $memberRole]);

        $lineLogin = new \App\Domain\Auth\LineLoginService(
            $this->fakeHttp(),
            new \Slim\Psr7\Factory\RequestFactory(),
            new StreamFactory(),
            self::CHANNEL_ID,
            'channel-secret',
            'https://pmois.local/auth/line/callback'
        );

        $this->auth = new PmoisAuthenticationService(
            $lineLogin,
            new MySqlOAuthStateRepository($this->db),
            new MySqlUserSessionRepository($this->db),
            new MySqlUserRepository($this->db),
            new \App\Domain\Auth\InvitationService(
                $this->createStub(\App\Domain\Project\ProjectRepositoryInterface::class),
                new \App\Infrastructure\Persistence\MySQL\MySqlWorkspaceMemberRepository($this->db, $this->workspaceId),
                new MySqlUserRepository($this->db),
                $lineLogin,
                new \App\Infrastructure\Persistence\MySQL\MySqlRoleRepository($this->db)
            ),
            $this->db
        );

        $this->controller = new LineLoginController($this->auth);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        unset($GLOBALS['app_env']['APP_SECRET'], $GLOBALS['app_env']['APP_DEBUG']);
        $this->httpHandler = null;
    }

    /**
     * Slim app จริง (routes/middleware เดียวกับ production pattern)
     */
    private function app(bool $debug): App
    {
        $app = new App(new ResponseFactory());
        $app->get('/', [$this->controller, 'root']);
        $app->get('/auth/line', [$this->controller, 'redirect']);
        $app->get('/auth/line/callback', [$this->controller, 'callback']);
        $app->get('/api/v1/auth/line', [$this->controller, 'apiRedirect']);
        $app->get('/api/v1/auth/line/callback', [$this->controller, 'apiCallback']);
        $app->get('/broken', function (): never {
            throw new \RuntimeException('secret internal detail about DB password at /var/www/vendor/x.php');
        });
        $app->add(new AppErrorMiddleware($debug));

        return $app;
    }

    private function request(string $method, string $uri, array $cookies = [], string $accept = 'application/json'): \Slim\Psr7\Request
    {
        $factory = new \Slim\Psr7\Factory\ServerRequestFactory();
        $request = $factory->createServerRequest($method, 'https://pmois.local' . $uri)
            ->withHeader('Accept', $accept);

        return $cookies !== [] ? $request->withCookieParams($cookies) : $request;
    }

    private function handle(App $app, \Slim\Psr7\Request $request): ResponseInterface
    {
        return $app->handle($request);
    }

    // ===== Issue 2: Root route =====

    public function testRootBeforeLoginRedirectsToLineLogin(): void
    {
        $response = $this->handle($this->app(false), $this->request('GET', '/'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/auth/line', $response->getHeaderLine('Location'));
    }

    public function testRootAfterLoginRedirectsToDashboard(): void
    {
        $sessionToken = $this->seedActiveSession();

        $response = $this->handle($this->app(false), $this->request('GET', '/', ['pmois_session' => $sessionToken]));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/app/dashboard.html', $response->getHeaderLine('Location'));
    }

    // ===== Issue 1 + 3: Web route 302 / API route JSON =====

    public function testWebAuthLineRedirectsToLineAuthorization(): void
    {
        $response = $this->handle($this->app(false), $this->request('GET', '/auth/line', [], 'text/html'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringStartsWith('https://access.line.me/oauth2/v2.1/authorize', $response->getHeaderLine('Location'));
        $this->assertStringNotContainsString('"success"', (string) $response->getBody(), 'browser ต้องไม่เห็น JSON');
        $this->assertStringContainsString('pmois_oauth_fp=', implode(';', $response->getHeader('Set-Cookie')));
    }

    public function testApiAuthLineReturnsJson(): void
    {
        $response = $this->handle($this->app(false), $this->request('GET', '/api/v1/auth/line'));

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertStringStartsWith('https://access.line.me', $body['data']['auth_url']);
    }

    // ===== Login success → callback → dashboard =====

    public function testSuccessfulLoginCallbackRedirectsToDashboard(): void
    {
        // bind LINE account กับ user ก่อน (fail-closed precondition)
        $this->db->prepare("UPDATE users SET line_user_id = 'U-rt' WHERE id = :id")->execute(['id' => $this->userId]);

        $start = $this->auth->beginLogin('fp-rt');
        $this->mockVerifyClaims(['iss' => 'https://access.line.me', 'sub' => 'U-rt', 'aud' => self::CHANNEL_ID, 'exp' => time() + 600, 'iat' => time()], $start['state']);

        $response = $this->handle(
            $this->app(false),
            $this->request('GET', '/auth/line/callback?code=good&state=' . urlencode($start['state']), ['pmois_oauth_fp' => 'fp-rt'])
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/app/projects.html', $response->getHeaderLine('Location'), 'login สำเร็จ → dashboard (projects)');
        $setCookie = implode(';', $response->getHeader('Set-Cookie'));
        $this->assertStringContainsString('pmois_session=', $setCookie, 'session cookie ต้องถูกตั้ง');
    }

    public function testFailedLoginCallbackRedirectsToLoginWithError(): void
    {
        $start = $this->auth->beginLogin('fp-bad');
        // ไม่มี user ที่ bind 'U-nobody' → UNAUTHORIZED_IDENTITY
        $this->mockVerifyClaims(['iss' => 'https://access.line.me', 'sub' => 'U-nobody', 'aud' => self::CHANNEL_ID, 'exp' => time() + 600, 'iat' => time()], $start['state']);

        $response = $this->handle(
            $this->app(false),
            $this->request('GET', '/auth/line/callback?code=good&state=' . urlencode($start['state']), ['pmois_oauth_fp' => 'fp-bad'], 'text/html')
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/app/index.html?error=UNAUTHORIZED_IDENTITY', $response->getHeaderLine('Location'));
    }

    // ===== Issue 4: Production error handling =====

    public function testInvalidRouteGeneric404NoStackTrace(): void
    {
        $response = $this->handle($this->app(false), $this->request('GET', '/no-such-route', [], 'text/html'));

        $this->assertSame(404, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringNotContainsString('Stack trace', $body);
        $this->assertStringNotContainsString('/var/www', $body);
        $this->assertStringNotContainsString('vendor', $body);
    }

    public function testInvalidRouteApiReturnsJsonEnvelope(): void
    {
        $response = $this->handle($this->app(false), $this->request('GET', '/api/v1/no-such-route'));

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('NOT_FOUND', $body['error']['code']);
        $this->assertSame('Not Found', $body['error']['message']);
    }

    public function testRuntimeExceptionGeneric500NoInternals(): void
    {
        $response = $this->handle($this->app(false), $this->request('GET', '/broken', [], 'application/json'));

        $this->assertSame(500, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringNotContainsString('secret internal detail', $body, 'ห้าม leak exception message');
        $this->assertStringNotContainsString('/var/www', $body, 'ห้าม leak source path');
        $this->assertStringNotContainsString('RuntimeException', $body, 'ห้าม leak exception class');
        $json = json_decode($body, true);
        $this->assertFalse($json['success']);
        $this->assertSame('INTERNAL_ERROR', $json['error']['code']);
        $this->assertSame('Internal Server Error', $json['error']['message']);
    }

    public function testDebugModeRethrowsForDevelopment(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->handle($this->app(true), $this->request('GET', '/broken'));
    }

    // ===== helpers =====

    private function seedActiveSession(): string
    {
        $token = bin2hex(random_bytes(32));
        $repo = new MySqlUserSessionRepository($this->db);
        $repo->create(
            $this->userId,
            hash('sha256', $token),
            'login',
            null,
            null,
            date('Y-m-d H:i:s', time() + 3600)
        );
        // align expiry กับ MySQL NOW() (timezone ฝั่ง DB อาจต่างจาก PHP)
        $this->db->prepare("UPDATE user_sessions SET expires_at = DATE_ADD(NOW(), INTERVAL 8 HOUR) WHERE session_token_hash = :h")
            ->execute(['h' => hash('sha256', $token)]);

        return $token;
    }

    private function mockVerifyClaims(array $claims, ?string $expectedState = null): void
    {
        $this->httpHandler = function ($request) use ($claims, $expectedState) {
            if (str_contains((string) $request->getUri(), self::VERIFY_URL_FRAGMENT)) {
                $final = $claims;
                if ($expectedState !== null) {
                    $stmt = $this->db->prepare('SELECT nonce FROM oauth_login_states WHERE state_hash = :h');
                    $stmt->execute(['h' => hash('sha256', $expectedState)]);
                    $final['nonce'] = (string) $stmt->fetchColumn();
                }
                return FakeLineHttpClient::jsonResponse($final);
            }
            return FakeLineHttpClient::jsonResponse(['access_token' => 'at', 'id_token' => 'mocked.jwt.token']);
        };
    }

    private function fakeHttp(): HttpClientInterface
    {
        return new class($this) implements HttpClientInterface {
            public function __construct(private readonly HttpRuntimeTest $test)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $handler = $this->test->httpHandler;
                if ($handler === null) {
                    throw new \RuntimeException('no HTTP handler configured for this test');
                }

                return $handler($request);
            }
        };
    }
}
