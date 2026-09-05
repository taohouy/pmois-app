<?php

declare(strict_types=1);

/**
 * Integration Validation (M9 — pre-UAT gate)
 *
 * Usage: php bin/validate-integrations.php
 * Exit 0 = ทุกอย่างพร้อม UAT, Exit 1 = มี FAIL
 *
 * Checks: PHP/extensions, DB, APP_SECRET, LINE config+connectivity,
 * GitLab connectivity, Telegram (ถ้าตั้งค่า), Storage writable, Automation tables
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

$env = $GLOBALS['app_env'];
$results = [];
$failures = 0;

function report(string $name, string $status, string $detail = ''): void
{
    global $failures;
    $mark = match ($status) { 'PASS' => '✅', 'WARN' => '⚠️ ', default => '❌' };
    if ($status === 'FAIL') {
        $failures++;
    }
    printf("%s %-28s %s %s\n", $mark, $name, $status, $detail);
}

function httpStatus(string $url, int $timeout = 8): ?int
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_errno($ch);
    curl_close($ch);

    return $err === 0 ? $code : null;
}

// 1. PHP version
$ok = version_compare(PHP_VERSION, '8.1.0', '>=');
report('PHP >= 8.1', $ok ? 'PASS' : 'FAIL', PHP_VERSION);

// 2. Extensions
$missing = array_diff(['pdo_mysql', 'curl', 'openssl', 'mbstring', 'json'], get_loaded_extensions());
report('PHP extensions', $missing === [] ? 'PASS' : 'FAIL', $missing === [] ? 'pdo_mysql, curl, openssl, mbstring, json' : 'missing: ' . implode(',', $missing));

// 3. DB connectivity
try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $env['DB_HOST'] ?? '127.0.0.1', $env['DB_PORT'] ?? '3306', $env['DB_DATABASE'] ?? '');
    $db = new PDO($dsn, $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->query('SELECT 1');
    report('Database connectivity', 'PASS', ($env['DB_HOST'] ?? '127.0.0.1') . ':' . ($env['DB_PORT'] ?? '3306') . '/' . ($env['DB_DATABASE'] ?? ''));

    // 3.1 schema sanity
    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    report('Schema installed', count($tables) >= 40 ? 'PASS' : 'FAIL', count($tables) . ' tables');
} catch (PDOException $e) {
    report('Database connectivity', 'FAIL', $e->getMessage());
    fwrite(STDERR, "DB is required for the remaining checks — aborting.\n");
    exit(1);
}

// 4. APP_SECRET
$secretOk = ($env['APP_SECRET'] ?? '') !== '';
report('APP_SECRET configured', $secretOk ? 'PASS' : 'FAIL', $secretOk ? '' : 'claim tokens จะถูกลงนามด้วย dev fallback — ห้ามใช้ production');

// 5. LINE Login
$lineConfigured = ($env['LINE_CHANNEL_ID'] ?? '') !== '' && ($env['LINE_CHANNEL_SECRET'] ?? '') !== '' && ($env['LINE_REDIRECT_URI'] ?? '') !== '';
$code = $lineConfigured ? httpStatus('https://access.line.me') : null;
report('LINE Login config', $lineConfigured ? 'PASS' : 'FAIL', $lineConfigured ? 'channel_id/secret/redirect_uri set' : 'missing env');
report('LINE connectivity', $code !== null && $code < 500 ? 'PASS' : 'FAIL', 'https://access.line.me → HTTP ' . ($code ?? 'unreachable'));

// 6. GitLab
$gitlabUrl = 'https://gitlab.com';
try {
    $row = $db->query("SELECT base_url FROM git_providers WHERE code = 'gitlab' AND status = 'active' LIMIT 1")->fetchColumn();
    if ($row !== false && $row !== null) {
        $gitlabUrl = (string) $row;
    }
} catch (PDOException) {
}
$code = httpStatus($gitlabUrl);
report('GitLab connectivity', $code !== null && $code < 500 ? 'PASS' : 'FAIL', "{$gitlabUrl} → HTTP " . ($code ?? 'unreachable'));

// 7. Telegram (ถ้าตั้งค่า)
$telegramConfigured = ($env['TELEGRAM_BOT_TOKEN'] ?? '') !== '' && ($env['TELEGRAM_CHAT_ID'] ?? '') !== '';
if ($telegramConfigured) {
    $ch = curl_init('https://api.telegram.org/bot' . $env['TELEGRAM_BOT_TOKEN'] . '/getMe');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $ok = $code === 200 && is_string($body) && str_contains($body, '"ok":true');
    report('Telegram bot (getMe)', $ok ? 'PASS' : 'FAIL', "HTTP {$code}");
} else {
    report('Telegram bot (getMe)', 'WARN', 'TELEGRAM_BOT_TOKEN/CHAT_ID not configured — notifications skipped');
}

// 8. Storage writable
$storagePath = $env['STORAGE_BASE_PATH'] ?? (dirname(__DIR__) . '/storage/uploads');
if (!is_dir($storagePath)) {
    @mkdir($storagePath, 0775, true);
}
$probe = $storagePath . '/.healthcheck-' . uniqid();
$ok = @file_put_contents($probe, 'ok') !== false && @unlink($probe);
report('Storage writable', $ok ? 'PASS' : 'FAIL', $storagePath);

// 9. Automation tables
try {
    $db->query('SELECT 1 FROM automation_jobs LIMIT 1');
    $db->query('SELECT 1 FROM notifications LIMIT 1');
    report('Automation tables', 'PASS', 'automation_jobs + notifications');
} catch (PDOException $e) {
    report('Automation tables', 'FAIL', $e->getMessage());
}

echo "\n" . ($failures === 0 ? "ALL CHECKS PASSED — พร้อม UAT\n" : "{$failures} CHECK(S) FAILED\n");
exit($failures === 0 ? 0 : 1);
