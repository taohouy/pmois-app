<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Auth\InvitationService;
use App\Domain\Auth\LineLoginService;
use App\Domain\Auth\OAuthStateRepositoryInterface;
use App\Domain\Auth\PmoisAuthenticationService;
use App\Domain\Auth\UserSessionRepositoryInterface;
use App\Domain\Identity\RoleRepositoryInterface;
use App\Domain\Identity\UserRepositoryInterface;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Workspace\WorkspaceMemberRepositoryInterface;
use App\Domain\Auth\AuthException;
use App\Infrastructure\Http\HttpClientInterface;
use App\Infrastructure\Persistence\MySQL\MySqlOAuthStateRepository;
use App\Infrastructure\Persistence\MySQL\MySqlRoleRepository;
use App\Infrastructure\Persistence\MySQL\MySqlUserRepository;
use App\Infrastructure\Persistence\MySQL\MySqlUserSessionRepository;
use App\Infrastructure\Persistence\MySQL\MySqlWorkspaceMemberRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * LineAuthenticationTest — CTO Review §1 (LINE Login security) + §3 (required tests)
 *
 * ครอบคลุม: state (valid/missing/mismatch/reused/expired), ID token (invalid/expired/wrong aud),
 * fail-closed identity (unknown LINE account, inactive membership), valid authenticated login,
 * claim (verified identity only, reuse, expiry, tampered, duplicate binding,
 * client-supplied line_user_id ห้าม bind)
 *
 * LINE HTTP endpoints ถูก mock ผ่าน ClientInterface (สถานการณ์เดียวกับ production:
 * token exchange + verify endpoint) — รันบน DB ที่ migrate ถึง 0057 แล้ว
 */
final class LineAuthenticationTest extends TestCase
{
    private const CHANNEL_ID = '1234567890';
    private const VERIFY_URL_FRAGMENT = 'oauth2/v2.1/verify';

    private PDO $db;
    private int $workspaceId;
    private int $adminUserId;
    private PmoisAuthenticationService $auth;
    private FakeLineHttpClient $http;
    private InvitationService $invitationService;
    private UserRepositoryInterface $userRepo;

    protected function setUp(): void
    {
        $GLOBALS['app_env']['APP_SECRET'] = 'test-secret-for-claim-tokens';

        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $this->adminUserId = $this->insertUser('auth-admin', null, true);
        $this->workspaceId = $this->insertWorkspace($this->adminUserId);

        $this->http = new FakeLineHttpClient();

        $lineLogin = new LineLoginService(
            $this->http,
            new RequestFactory(),
            new StreamFactory(),
            self::CHANNEL_ID,
            'channel-secret',
            'https://pmois.local/auth/line/callback'
        );

        $this->userRepo = new MySqlUserRepository($this->db);
        $invitationService = new InvitationService(
            $this->createStub(ProjectRepositoryInterface::class),
            new MySqlWorkspaceMemberRepository($this->db, $this->workspaceId),
            $this->userRepo,
            $lineLogin,
            new MySqlRoleRepository($this->db)
        );
        $this->invitationService = $invitationService;

        $this->auth = new PmoisAuthenticationService(
            $lineLogin,
            new MySqlOAuthStateRepository($this->db),
            new MySqlUserSessionRepository($this->db),
            $this->userRepo,
            $invitationService,
            $this->db
        );
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        unset($GLOBALS['app_env']['APP_SECRET']);
    }

    // ===== §1.1 OAuth State =====

    public function testBeginLoginPersistsSecureState(): void
    {
        $result = $this->auth->beginLogin('fp-1');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result['state']);
        $this->assertStringContainsString('nonce=', $result['auth_url']);

        $row = $this->db->prepare('SELECT * FROM oauth_login_states WHERE state_hash = :h');
        $row->execute(['h' => hash('sha256', $result['state'])]);
        $state = $row->fetch();
        $this->assertNotFalse($state, 'state ต้องถูก persist');
        $this->assertSame('login', $state['purpose']);
        $this->assertSame(hash('sha256', 'fp-1'), $state['fingerprint_hash'], 'state ต้องผูกกับ browser fingerprint');
        $this->assertNull($state['used_at']);
    }

    public function testMissingStateRejected(): void
    {
        $this->expectException(\App\Domain\Auth\AuthException::class);
        $this->expectExceptionMessage('STATE_INVALID');

        $this->auth->completeCallback(str_repeat('a', 64), 'auth-code', 'fp-1');
    }

    public function testMismatchedFingerprintRejected(): void
    {
        $this->expectException(\App\Domain\Auth\AuthException::class);
        $this->expectExceptionMessage('STATE_MISMATCH');

        $start = $this->auth->beginLogin('fp-browser-A');
        $this->mockSuccessfulLine('U-bound', $start['state']);
        $this->auth->completeCallback($start['state'], 'auth-code', 'fp-browser-B');
    }

    public function testReusedStateRejected(): void
    {
        $lineUserId = 'U-reuse';
        $this->seedBoundActiveUser($lineUserId);

        $start = $this->auth->beginLogin('fp-reuse');
        $this->mockSuccessfulLine($lineUserId, $start['state']);

        $this->auth->completeCallback($start['state'], 'auth-code', 'fp-reuse');

        // state เป็น one-time — callback รอบที่สองต้องถูกปฏิเสธ
        try {
            $this->auth->completeCallback($start['state'], 'auth-code', 'fp-reuse');
            $this->fail('expected STATE_REUSED (AuthException)');
        } catch (\App\Domain\Auth\AuthException $e) {
            $this->assertSame('STATE_REUSED', $e->errorCode);
        }
    }

    public function testExpiredStateRejected(): void
    {
        $start = $this->auth->beginLogin('fp-exp');
        // ใช้เวลาจาก PHP (ไม่ใช่ MySQL NOW()) ให้ timezone ตรงกับที่ service ใช้เขียน expires_at
        $this->db->prepare('UPDATE oauth_login_states SET expires_at = :past WHERE state_hash = :h')
            ->execute(['h' => hash('sha256', $start['state']), 'past' => date('Y-m-d H:i:s', time() - 60)]);

        $this->expectException(\App\Domain\Auth\AuthException::class);
        $this->expectExceptionMessage('STATE_EXPIRED');

        $this->auth->completeCallback($start['state'], 'auth-code', 'fp-exp');
    }

    // ===== §1.2 ID Token verification =====

    public function testInvalidIdTokenRejected(): void
    {
        $this->expectException(\App\Domain\Auth\AuthException::class);
        $this->expectExceptionMessage('ID_TOKEN_INVALID');

        $start = $this->auth->beginLogin('fp-invalid');
        // LINE verify endpoint ปฏิเสธ token (signature ไม่ผ่าน)
        $this->http->setHandler(function (RequestInterface $request) use ($start): ResponseInterface {
            if (str_contains((string) $request->getUri(), self::VERIFY_URL_FRAGMENT)) {
                return FakeLineHttpClient::jsonResponse(['error' => 'invalid_token', 'error_description' => 'Invalid IdToken.']);
            }
            return FakeLineHttpClient::jsonResponse(['access_token' => 'at', 'id_token' => 'faked']);
        });

        $this->auth->completeCallback($start['state'], 'auth-code', 'fp-invalid');
    }

    public function testWrongAudienceIdTokenRejected(): void
    {
        $this->expectException(\App\Domain\Auth\AuthException::class);
        $this->expectExceptionMessage('ID_TOKEN_INVALID');

        $start = $this->auth->beginLogin('fp-aud');
        $this->mockVerifyClaims(['iss' => 'https://access.line.me', 'sub' => 'U-x', 'aud' => '9999999999', 'exp' => time() + 600, 'iat' => time()]);

        $this->auth->completeCallback($start['state'], 'auth-code', 'fp-aud');
    }

    public function testExpiredIdTokenRejected(): void
    {
        $this->expectException(\App\Domain\Auth\AuthException::class);
        $this->expectExceptionMessage('ID_TOKEN_INVALID');

        $start = $this->auth->beginLogin('fp-exp-token');
        $this->mockVerifyClaims(['iss' => 'https://access.line.me', 'sub' => 'U-x', 'aud' => self::CHANNEL_ID, 'exp' => time() - 100, 'iat' => time() - 200]);

        $this->auth->completeCallback($start['state'], 'auth-code', 'fp-exp-token');
    }

    public function testNonceMismatchIdTokenRejected(): void
    {
        $this->expectException(\App\Domain\Auth\AuthException::class);
        $this->expectExceptionMessage('ID_TOKEN_INVALID');

        $start = $this->auth->beginLogin('fp-nonce');
        $this->mockVerifyClaims(['iss' => 'https://access.line.me', 'sub' => 'U-x', 'aud' => self::CHANNEL_ID, 'exp' => time() + 600, 'iat' => time(), 'nonce' => 'attacker-nonce']);

        $this->auth->completeCallback($start['state'], 'auth-code', 'fp-nonce');
    }

    // ===== §1.3 PMOIS authentication (fail-closed) =====

    public function testUnauthorizedLineAccountRejected(): void
    {
        $this->expectException(\App\Domain\Auth\AuthException::class);
        $this->expectExceptionMessage('UNAUTHORIZED_IDENTITY');

        // LINE account ที่ไม่ถูก bind กับ PMOIS user ใดๆ — ห้าม login (ไม่ auto-create)
        $start = $this->auth->beginLogin('fp-unknown');
        $this->mockSuccessfulLine('U-stranger', $start['state']);
        $this->auth->completeCallback($start['state'], 'auth-code', 'fp-unknown');
    }

    public function testInactiveMembershipRejected(): void
    {
        $this->expectException(\App\Domain\Auth\AuthException::class);
        $this->expectExceptionMessage('UNAUTHORIZED_IDENTITY');

        // user ถูก bind แต่ suspended
        $this->insertUser('suspended-user', 'U-suspended', false, 'suspended');

        $start = $this->auth->beginLogin('fp-inactive');
        $this->mockSuccessfulLine('U-suspended', $start['state']);
        $this->auth->completeCallback($start['state'], 'auth-code', 'fp-inactive');
    }

    public function testValidAuthenticatedLoginCreatesPmoisSession(): void
    {
        $lineUserId = 'U-valid';
        $this->seedBoundActiveUser($lineUserId);

        $start = $this->auth->beginLogin('fp-valid');
        $this->mockSuccessfulLine($lineUserId, $start['state']);
        $result = $this->auth->completeCallback($start['state'], 'auth-code', 'fp-valid');

        $this->assertSame('login', $result['purpose']);
        $this->assertFalse($result['claimed']);
        $this->assertSame($this->workspaceId, $result['workspace_id']);

        // session ต้องถูกสร้างและ resolve ได้ (hash-only storage)
        $session = $this->auth->resolveSession($result['session_token']);
        $this->assertNotNull($session, 'PMOIS session ต้องถูกสร้าง');
        $this->assertSame($result['user_id'], (int) $session['user_id']);

        // session token ไม่ถูกเก็บแบบ raw
        $rawCheck = $this->db->prepare('SELECT COUNT(*) AS c FROM user_sessions WHERE session_token_hash = :t');
        $rawCheck->execute(['t' => $result['session_token']]);
        $this->assertSame(0, (int) $rawCheck->fetchColumn(), 'raw session token ห้ามถูกเก็บลง DB');
    }

    // ===== §1.4 Claim flow =====

    public function testClaimBindsVerifiedLineIdentityOnly(): void
    {
        $claimToken = $this->invitationService->createInvitation($this->workspaceId, 0, $this->adminUserId, 'MEMBER', $this->adminUserId);

        $start = $this->auth->beginClaim('fp-claim', $claimToken);
        // verified sub จาก LINE (mock) — client ไม่มีช่องทางส่ง line_user_id เข้ามา
        $this->mockSuccessfulLine('U-verified-claim', $start['state']);
        $result = $this->auth->completeCallback($start['state'], 'auth-code', 'fp-claim');

        $this->assertTrue($result['claimed']);

        $bound = $this->db->prepare('SELECT line_user_id FROM users WHERE id = :id');
        $bound->execute(['id' => $result['user_id']]);
        $this->assertSame('U-verified-claim', $bound->fetchColumn(), 'bind ต้องใช้ verified sub เท่านั้น');
    }

    public function testClientSuppliedFakeLineUserIdCannotBind(): void
    {
        // ยืนยันเชิงโครงสร้าง: ไม่มี HTTP route ใดรับ line_user_id จาก client อีกต่อไป
        $routes = file_get_contents(__DIR__ . '/../../src/Config/routes.php');
        $this->assertStringNotContainsString("ClaimController::class . ':process'", $routes, 'POST /claim (client-supplied line_user_id) ต้องถูกลบออก');
        $this->assertStringNotContainsString("ClaimController::class . ':validate'", $routes, 'GET /claim/{token} ต้องเป็น start เท่านั้น');
    }

    public function testClaimReuseRejected(): void
    {
        $claimToken = $this->invitationService->createInvitation($this->workspaceId, 0, $this->adminUserId, 'MEMBER', $this->adminUserId);

        $start = $this->auth->beginClaim('fp-claim-1', $claimToken);
        $this->mockSuccessfulLine('U-claim-once', $start['state']);
        $this->auth->completeCallback($start['state'], 'auth-code', 'fp-claim-1');

        // reused claim token — account ถูก claim ไปแล้ว
        $this->expectException(\App\Domain\Auth\AuthException::class);
        $this->expectExceptionMessage('CLAIM_ALREADY_USED');

        $this->invitationService->processClaim($claimToken, 'U-claim-once', 'Attacker', '');
    }

    public function testClaimTokenExpiryRejected(): void
    {
        $expired = $this->craftSignedToken([
            'workspace_id' => $this->workspaceId,
            'project_id' => 0,
            'user_id' => $this->adminUserId,
            'role_code' => 'MEMBER',
            'exp' => time() - 100,
        ]);

        $this->expectException(\App\Domain\Auth\AuthException::class);
        $this->expectExceptionMessage('CLAIM_TOKEN_INVALID');

        $this->auth->beginClaim('fp-exp-claim', $expired);
    }

    public function testTamperedClaimTokenRejected(): void
    {
        $token = $this->invitationService->createInvitation($this->workspaceId, 0, $this->adminUserId, 'MEMBER', $this->adminUserId);
        [$body, $sig] = explode('.', $token);
        // แก้ payload ให้ชี้ user อื่น แต่ไม่ได้ re-sign (HMAC จะไม่ตรง)
        $payload = json_decode($this->base64UrlDecode($body), true);
        $payload['user_id'] = $this->adminUserId;
        $tampered = $this->base64UrlEncode((string) json_encode($payload)) . '.' . $sig;

        $this->expectException(\App\Domain\Auth\AuthException::class);
        $this->expectExceptionMessage('CLAIM_TOKEN_INVALID');

        $this->auth->beginClaim('fp-tamper', $tampered);
    }

    public function testDuplicateLineBindingRejected(): void
    {
        // user1 claim ด้วย LINE account S สำเร็จ
        $claim1 = $this->invitationService->createInvitation($this->workspaceId, 0, $this->adminUserId, 'MEMBER', $this->adminUserId);
        $start1 = $this->auth->beginClaim('fp-dup-1', $claim1);
        $this->mockSuccessfulLine('U-duplicate', $start1['state']);
        $this->auth->completeCallback($start1['state'], 'auth-code', 'fp-dup-1');

        // user2 พยายาม claim ด้วย LINE account เดียวกัน — ต้องถูกปฏิเสธ
        $claim2 = $this->invitationService->createInvitation($this->workspaceId, 0, $this->adminUserId, 'MEMBER', $this->adminUserId);
        $start2 = $this->auth->beginClaim('fp-dup-2', $claim2);
        $this->mockSuccessfulLine('U-duplicate', $start2['state']);

        $this->expectException(\App\Domain\Auth\AuthException::class);
        $this->expectExceptionMessage('LINE_ALREADY_BOUND');

        $this->auth->completeCallback($start2['state'], 'auth-code', 'fp-dup-2');
    }

    // ===== helpers =====

    private function mockSuccessfulLine(string $sub, string $expectedState): void
    {
        $this->mockVerifyClaims([
            'iss' => 'https://access.line.me',
            'sub' => $sub,
            'aud' => self::CHANNEL_ID,
            'exp' => time() + 600,
            'iat' => time(),
            'name' => 'Test User',
            'picture' => '',
            // nonce จะถูกเติมโดย handler จริง (ตรวจเทียบกับ state row)
        ], $expectedState);
    }

    private function mockVerifyClaims(array $claims, ?string $expectedState = null): void
    {
        $this->http->setHandler(function (RequestInterface $request) use ($claims, $expectedState): ResponseInterface {
            if (str_contains((string) $request->getUri(), self::VERIFY_URL_FRAGMENT)) {
                $body = (string) $request->getBody();
                parse_str($body, $form);
                $finalClaims = $claims;
                if ($expectedState !== null) {
                    // คืน nonce ที่ถูกต้องตาม state row (เหมือน LINE สะท้อน nonce กลับมา)
                    $stmt = $this->db->prepare('SELECT nonce FROM oauth_login_states WHERE state_hash = :h');
                    $stmt->execute(['h' => hash('sha256', $expectedState)]);
                    $finalClaims['nonce'] = (string) $stmt->fetchColumn();
                }
                return FakeLineHttpClient::jsonResponse($finalClaims);
            }
            // token exchange
            return FakeLineHttpClient::jsonResponse(['access_token' => 'at-123', 'id_token' => 'mocked.jwt.token', 'expires_in' => 3600]);
        });
    }

    private function seedBoundActiveUser(string $lineUserId): int
    {
        $userId = $this->insertUser('bound-user', $lineUserId, false, 'active');

        $stmt = $this->db->prepare(
            "INSERT INTO workspace_members (workspace_id, user_id, role_id, status)
             SELECT :ws, :user_id, id, 'active' FROM roles WHERE code = 'MEMBER' LIMIT 1"
        );
        $stmt->execute(['ws' => $this->workspaceId, 'user_id' => $userId]);

        return $userId;
    }

    private function insertUser(string $name, ?string $lineUserId, bool $isAdmin = false, string $status = 'active'): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password_hash, status, is_platform_admin, line_user_id, auth_provider)
             VALUES (:name, :email, NULL, :status, :admin, :line_user_id, "line")'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $name . uniqid() . '@test.local',
            'status' => $status,
            'admin' => $isAdmin ? 1 : 0,
            'line_user_id' => $lineUserId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function insertWorkspace(int $createdBy): int
    {
        $stmt = $this->db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, "Auth Test WS", "active", :user)');
        $stmt->execute(['code' => 'auth-ws-' . uniqid(), 'user' => $createdBy]);

        return (int) $this->db->lastInsertId();
    }

    /** ปลอม claim token ที่ sign ถูกต้องด้วย APP_SECRET เดียวกับ InvitationService (สำหรับทดสอบ exp) */
    private function craftSignedToken(array $payload): string
    {
        $body = $this->base64UrlEncode((string) json_encode($payload));
        $secret = $GLOBALS['app_env']['APP_SECRET'] ?? 'pmois-dev-secret-change-in-production';
        $sig = $this->base64UrlEncode(hash_hmac('sha256', $body, $secret, true));

        return $body . '.' . $sig;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}

/**
 * Fake HTTP client — mock LINE token/verify endpoints
 */
final class FakeLineHttpClient implements HttpClientInterface
{
    /** @var callable(RequestInterface): ResponseInterface|null */
    private $handler;

    public function setHandler(callable $handler): void
    {
        $this->handler = $handler;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if ($this->handler === null) {
            throw new \RuntimeException('FakeLineHttpClient: no handler configured');
        }

        return ($this->handler)($request);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function jsonResponse(array $data): ResponseInterface
    {
        $response = (new ResponseFactory())->createResponse(200);
        $response->getBody()->write((string) json_encode($data));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
