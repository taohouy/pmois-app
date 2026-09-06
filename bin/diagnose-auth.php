<?php

/**
 * PMOIS Auth Diagnostic — Runtime test for OAuth state round-trip
 *
 * Usage:
 *   php -S 0.0.0.0:8080 -t public
 *   php bin/diagnose-auth.php
 *
 * Or standalone:
 *   php bin/diagnose-auth.php
 *
 * This script simulates the full OAuth round-trip locally without LINE:
 *   1. beginLogin → state persisted + fingerprint cookie
 *   2. completeCallback with same state + fingerprint → session created
 *   3. Reports exactly what happens at each step
 *
 * Then tests the claim flow similarly.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Load .env
$envFile = __DIR__ . '/../.env';
$GLOBALS['app_env'] = [];
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $GLOBALS['app_env'][$key] = trim($value, "\"'");
    }
}
$GLOBALS['app_env']['APP_DEBUG'] = false;

echo "=== PMOIS Auth Diagnostic ===\n\n";

/** Stub ProjectRepositoryInterface ที่ implement ทุก method เป็น no-op */
function createStubProjectRepository(): \App\Domain\Project\ProjectRepositoryInterface
{
    return new class implements \App\Domain\Project\ProjectRepositoryInterface {
        public function findById(int $id): ?\App\Domain\Project\Project { return null; }
        public function listByWorkspace(): array { return []; }
        public function create(string $code, string $name, ?string $description, int $ownerUserId, int $workspaceId, ?int $parentProjectId = null, string $developmentMode = 'manual', ?string $abbreviation = null, ?string $startDate = null, ?int $sourceTemplateId = null): \App\Domain\Project\Project { throw new \RuntimeException('not implemented'); }
        public function updateStatus(int $id, string $status): bool { return false; }
        public function updateWorkspace(int $id, int $newWorkspaceId): bool { return false; }
        public function updateParent(int $id, ?int $parentProjectId): bool { return false; }
        public function updateProgress(int $id, int $progressPercent, string $health): bool { return false; }
        public function updateCurrentMilestone(int $id, ?int $milestoneId): bool { return false; }
    };
}

// Connect to DB
$dsn = $GLOBALS['app_env']['TEST_DB_DSN'] ?? getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;port=3307;dbname=pmois_test;charset=utf8mb4';
$user = $GLOBALS['app_env']['TEST_DB_USER'] ?? getenv('TEST_DB_USER') ?: 'root';
$pass = $GLOBALS['app_env']['TEST_DB_PASS'] ?? getenv('TEST_DB_PASS') ?: '';

echo "DB: $dsn\n";

try {
    $db = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    echo "DB connected: YES\n\n";
} catch (\Throwable $e) {
    echo "DB connected: NO — " . $e->getMessage() . "\n";
    echo "Start temp MySQL on port 3307 and run: mysql < deploy/PMOIS_v2_Database_Install.sql\n";
    exit(1);
}

// Build services
$nonceHolder = new class { public string $value = ''; };

$httpClient = new class($nonceHolder) implements \App\Infrastructure\Http\HttpClientInterface {
    public function __construct(private readonly object $nonceHolder)
    {
    }

    private static function jsonResponse(array $data): \Psr\Http\Message\ResponseInterface
    {
        $response = (new \Slim\Psr7\Factory\ResponseFactory())->createResponse(200);
        $response->getBody()->write((string) json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $uri = (string) $request->getUri();
        if (str_contains($uri, 'oauth2/v2.1/verify')) {
            return self::jsonResponse([
                'iss' => 'https://access.line.me',
                'sub' => 'U-diag-test',
                'aud' => 'test-channel-id',
                'exp' => time() + 600,
                'iat' => time(),
                'name' => 'Diag Test',
                'picture' => '',
                'nonce' => $this->nonceHolder->value,
            ]);
        }
        if (str_contains($uri, 'oauth2/v2.1/token')) {
            return self::jsonResponse([
                'access_token' => 'diag-at',
                'id_token' => 'diag.id.token',
                'expires_in' => 3600,
            ]);
        }
        throw new \RuntimeException('Unexpected URI: ' . $uri);
    }
};

$lineLogin = new \App\Domain\Auth\LineLoginService(
    $httpClient,
    new \Slim\Psr7\Factory\RequestFactory(),
    new \Slim\Psr7\Factory\StreamFactory(),
    'test-channel-id',
    'test-channel-secret',
    'https://pmois.local/auth/line/callback'
);

$stateRepo = new \App\Infrastructure\Persistence\MySQL\MySqlOAuthStateRepository($db);
$sessionRepo = new \App\Infrastructure\Persistence\MySQL\MySqlUserSessionRepository($db);
$userRepo = new \App\Infrastructure\Persistence\MySQL\MySqlUserRepository($db);

// Create test admin user
$db->beginTransaction();
try {
    $db->prepare('INSERT INTO users (name, email, password_hash, status, is_platform_admin, line_user_id, auth_provider) VALUES ("Diag Admin", :email, NULL, "active", 1, :line, "line")')
        ->execute(['email' => 'diag-' . uniqid() . '@test.local', 'line' => 'U-diag-test']);
    $userId = (int) $db->lastInsertId();

    $db->prepare('INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, "Diag WS", "active", :u)')
        ->execute(['code' => 'diag-' . uniqid(), 'u' => $userId]);
    $wsId = (int) $db->lastInsertId();

    $roleRow = $db->query("SELECT id FROM roles WHERE code = 'ADMIN' LIMIT 1")->fetchColumn();
    if ($roleRow === false) {
        $roleRow = $db->query("SELECT id FROM roles WHERE code = 'MEMBER' LIMIT 1")->fetchColumn();
    }
    $db->prepare("INSERT INTO workspace_members (workspace_id, user_id, role_id, status) VALUES (:ws, :u, :r, 'active')")
        ->execute(['ws' => $wsId, 'u' => $userId, 'r' => (int) $roleRow]);

    $invitationService = new \App\Domain\Auth\InvitationService(
        createStubProjectRepository(),
        new \App\Infrastructure\Persistence\MySQL\MySqlWorkspaceMemberRepository($db, $wsId),
        new \App\Infrastructure\Persistence\MySQL\MySqlUserRepository($db),
        $lineLogin,
        new \App\Infrastructure\Persistence\MySQL\MySqlRoleRepository($db),
    );
} catch (\Throwable $e) {
    echo "Setup error: " . $e->getMessage() . "\n";
    $db->rollBack();
    exit(1);
}

$auth = new \App\Domain\Auth\PmoisAuthenticationService(
    $lineLogin,
    $stateRepo,
    $sessionRepo,
    $userRepo,
    $invitationService,
    $db
);

echo "--- Normal Login Flow ---\n";

// Step 1: beginLogin
$fingerprint = bin2hex(random_bytes(32));
echo "1. beginLogin(fingerprint=...)\n";

try {
    $start = $auth->beginLogin($fingerprint);
    $stateHash = hash('sha256', $start['state']);
    echo "   state generated: YES (prefix=" . substr($stateHash, 0, 8) . ")\n";
    echo "   auth_url: " . substr($start['auth_url'], 0, 120) . "...\n";
    echo "   auth_url has state param: " . (str_contains($start['auth_url'], 'state=') ? 'YES' : 'NO') . "\n";
    echo "   auth_url has nonce param: " . (str_contains($start['auth_url'], 'nonce=') ? 'YES' : 'NO') . "\n";

    // capture nonce จาก DB ให้ fake LINE verify echo กลับ (เหมือน LINE จริง)
    $nonceRow = $stateRepo->findByStateHash($stateHash);
    $nonceHolder->value = (string) ($nonceRow['nonce'] ?? '');
} catch (\Throwable $e) {
    echo "   beginLogin FAILED: " . get_class($e) . " — " . $e->getMessage() . "\n";
    $db->rollBack();
    exit(1);
}

// Step 2: Check state persisted in DB
$stateRow = $stateRepo->findByStateHash($stateHash);
echo "2. State persisted in DB:\n";
echo "   found: " . ($stateRow !== null ? 'YES' : 'NO') . "\n";
if ($stateRow !== null) {
    echo "   purpose: " . $stateRow['purpose'] . "\n";
    echo "   used_at: " . ($stateRow['used_at'] !== null ? 'YES (BAD)' : 'NO (OK)') . "\n";
    echo "   fingerprint_hash stored: " . substr($stateRow['fingerprint_hash'], 0, 8) . "...\n";
    echo "   fingerprint matches: " . (hash_equals($stateRow['fingerprint_hash'], hash('sha256', $fingerprint)) ? 'YES' : 'NO') . "\n";
}

// Step 3: completeCallback
echo "3. completeCallback(state, code, fingerprint):\n";
try {
    $result = $auth->completeCallback($start['state'], 'diag-code', $fingerprint);
    echo "   SUCCESS!\n";
    echo "   user_id: " . $result['user_id'] . "\n";
    echo "   workspace_id: " . ($result['workspace_id'] ?? 'null') . "\n";
    echo "   purpose: " . $result['purpose'] . "\n";
    echo "   claimed: " . ($result['claimed'] ? 'YES' : 'NO') . "\n";
    echo "   session_token present: " . (strlen($result['session_token']) === 64 ? 'YES (64 chars)' : 'NO (' . strlen($result['session_token']) . ')') . "\n";
} catch (\Throwable $e) {
    echo "   FAILED: " . get_class($e) . " — " . $e->getMessage() . "\n";
}

// Step 4: Verify state marked as used
$stateRowAfter = $stateRepo->findByStateHash($stateHash);
echo "4. State marked as used after callback:\n";
echo "   used_at: " . ($stateRowAfter !== null && $stateRowAfter['used_at'] !== null ? 'YES (OK)' : 'NO (BAD)') . "\n\n";

// Step 5: Test with wrong fingerprint (should fail)
echo "--- Mismatched Fingerprint Test ---\n";
$fingerprint2 = bin2hex(random_bytes(32));
try {
    $start2 = $auth->beginLogin($fingerprint2);
    $auth->completeCallback($start2['state'], 'diag-code', 'WRONG-FINGERPRINT');
    echo "   FAILED: should have thrown STATE_MISMATCH but succeeded\n";
} catch (\App\Domain\Auth\AuthException $e) {
    echo "   Correctly rejected: " . $e->errorCode . "\n";
} catch (\Throwable $e) {
    echo "   WRONG exception: " . get_class($e) . " — " . $e->getMessage() . "\n";
}

// Step 6: Test with empty fingerprint (should fail)
echo "\n--- Empty Fingerprint Test (cookie lost) ---\n";
$fingerprint3 = bin2hex(random_bytes(32));
try {
    $start3 = $auth->beginLogin($fingerprint3);
    $auth->completeCallback($start3['state'], 'diag-code', '');
    echo "   FAILED: should have thrown STATE_MISMATCH but succeeded\n";
} catch (\App\Domain\Auth\AuthException $e) {
    echo "   Correctly rejected: " . $e->errorCode . "\n";
} catch (\Throwable $e) {
    echo "   WRONG exception: " . get_class($e) . " — " . $e->getMessage() . "\n";
}

// Step 7: Verify cookie flags
echo "\n--- Cookie Flags Analysis ---\n";
$controller = new \App\Application\Http\Controllers\LineLoginController($auth);

// Check via reflection
$ref = new \ReflectionClass($controller);
$cookieFlagsMethod = $ref->getMethod('cookieFlags');
$cookieFlagsMethod->setAccessible(true);
$flags = $cookieFlagsMethod->invoke($controller);
echo "   cookieFlags(): " . $flags . "\n";
echo "   has HttpOnly: " . (str_contains($flags, 'HttpOnly') ? 'YES' : 'NO') . "\n";
echo "   has SameSite=Lax: " . (str_contains($flags, 'SameSite=Lax') ? 'YES' : 'NO') . "\n";
echo "   has Secure: " . (str_contains($flags, 'Secure') ? 'YES' : 'NO') . "\n";
echo "   has Domain: " . (str_contains($flags, 'Domain=') ? 'YES (' . substr($flags, strpos($flags, 'Domain=')) . ')' : 'NO — THIS IS THE ROOT CAUSE IF MISSING') . "\n";
echo "   OAUTH_COOKIE_DOMAIN env: " . ($GLOBALS['app_env']['OAUTH_COOKIE_DOMAIN'] ?? '(not set)') . "\n";
echo "   APP_DEBUG: " . ($GLOBALS['app_env']['APP_DEBUG'] ?? 'false') . "\n";

$db->rollBack();
echo "\n=== Diagnostic Complete ===\n";
