<?php

declare(strict_types=1);

/**
 * Print a claim URL for an existing admin (M9 Installation step 5)
 *
 * Usage:
 *   php bin/admin-claim-url.php <email> [--base-url=https://pmois.example.com]
 *
 * Admin เปิด URL นี้ → Login with LINE → line_user_id ถูก bind กับบัญชี admin
 * (claim token: HMAC-SHA256, อายุ 24 ชม., รูปแบบเดียวกับ InvitationService)
 */

require __DIR__ . '/../vendor/autoload.php';

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

$email = $argv[1] ?? '';
$baseUrl = 'https://localhost';
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--base-url=')) {
        $baseUrl = rtrim(substr($arg, 11), '/');
    }
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php bin/admin-claim-url.php <email> [--base-url=https://...]\n");
    exit(1);
}

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $GLOBALS['app_env']['DB_HOST'] ?? '127.0.0.1',
    $GLOBALS['app_env']['DB_PORT'] ?? '3306',
    $GLOBALS['app_env']['DB_DATABASE'] ?? '');
try {
    $db = new PDO($dsn, $GLOBALS['app_env']['DB_USERNAME'] ?? '', $GLOBALS['app_env']['DB_PASSWORD'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "DB connect failed: {$e->getMessage()}\n");
    exit(1);
}

$stmt = $db->prepare('SELECT id FROM users WHERE email = :email AND is_platform_admin = 1 LIMIT 1');
$stmt->execute(['email' => $email]);
$userId = $stmt->fetchColumn();

if ($userId === false) {
    fwrite(STDERR, "platform admin with email {$email} not found (run bin/create-admin.php first)\n");
    exit(1);
}

// ลงนาม claim token ด้วยกลไกเดียวกับ InvitationService (HMAC-SHA256 + APP_SECRET)
$body = rtrim(strtr(base64_encode((string) json_encode([
    'workspace_id' => 0,
    'project_id' => 0,
    'user_id' => (int) $userId,
    'role_code' => 'ADMIN',
    'exp' => time() + 86400,
])), '+/', '-_'), '=');
$secret = $GLOBALS['app_env']['APP_SECRET'] ?? 'pmois-dev-secret-change-in-production';
$sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $body, $secret, true)), '+/', '-_'), '=');

echo "Claim URL (valid 24 hours, one-time binding):\n";
echo $baseUrl . "/claim/" . $body . '.' . $sig . "\n";
