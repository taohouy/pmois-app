#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * PMOIS Pilot — API Token Provisioner
 *
 * สร้าง ADMIN API Token สำหรับ admin@jaidee.digital / JaideeDigital workspace
 * ใช้ครั้งเดียวเพื่อ bootstrap pilot access — raw_token จะแสดงผลครั้งเดียวเท่านั้น
 *
 * Pre-requisite: รัน `php database/migrate.php run` ก่อน
 *
 * Usage:
 *   php database/provision_pilot_token.php
 */

$rootDir = dirname(__DIR__);
$envFile = $rootDir . '/.env';

if (!file_exists($envFile)) {
    fwrite(STDERR, "ไม่พบ .env — รัน: cp .env.example .env แล้วตั้งค่าก่อน\n");
    exit(1);
}

$env = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = array_map('trim', explode('=', $line, 2));
    $env[$k] = trim($v, "\"'");
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $env['DB_HOST'] ?? '127.0.0.1',
        $env['DB_PORT'] ?? '3306',
        $env['DB_DATABASE']
    ),
    $env['DB_USERNAME'],
    $env['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// ── Resolve workspace + user ─────────────────────────────────────────────────
$row = $pdo->query(
    "SELECT w.id AS workspace_id, u.id AS user_id
     FROM workspaces w
     JOIN users u ON u.email = 'admin@jaidee.digital'
     WHERE w.code = 'JAIDEEDIGITAL'
     LIMIT 1"
)->fetch();

if ($row === false) {
    fwrite(STDERR, "ไม่พบ workspace JAIDEEDIGITAL หรือ user admin@jaidee.digital\n");
    fwrite(STDERR, "กรุณารัน: php database/migrate.php run ก่อน\n");
    exit(1);
}

// ── Check: token ชื่อนี้มีอยู่แล้วหรือยัง ────────────────────────────────────
$existing = $pdo->prepare(
    "SELECT id FROM api_tokens
     WHERE workspace_id = :wid AND token_name = 'PMOIS Pilot Admin Token' AND status = 'active'
     LIMIT 1"
);
$existing->execute(['wid' => $row['workspace_id']]);
if ($existing->fetch() !== false) {
    fwrite(STDERR, "Token 'PMOIS Pilot Admin Token' มีอยู่แล้วใน workspace นี้\n");
    fwrite(STDERR, "ถ้าต้องการสร้างใหม่ ให้ revoke token เดิมก่อน\n");
    exit(1);
}

// ── Generate + store token ───────────────────────────────────────────────────
$rawToken  = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $rawToken);

$stmt = $pdo->prepare(
    "INSERT INTO api_tokens (workspace_id, created_by_user_id, token_name, token_hash, scopes, status)
     VALUES (:workspace_id, :user_id, 'PMOIS Pilot Admin Token', :token_hash, NULL, 'active')"
);
$stmt->execute([
    'workspace_id' => $row['workspace_id'],
    'user_id'      => $row['user_id'],
    'token_hash'   => $tokenHash,
]);

$tokenId = $pdo->lastInsertId();

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║         PMOIS Pilot Admin Token — SAVE THIS NOW             ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
echo "║ Token ID   : {$tokenId}\n";
echo "║ Token Name : PMOIS Pilot Admin Token\n";
echo "║ Workspace  : JaideeDigital\n";
echo "║ User       : admin@jaidee.digital\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
echo "║ Bearer Token (ใช้ใน Authorization header):                   ║\n";
echo "║\n";
echo "║   {$rawToken}\n";
echo "║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
echo "║  ⚠️  raw token นี้จะไม่แสดงซ้ำอีก — เก็บไว้ให้ดี           ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Verify: GET /api/v1/projects\n";
echo "Header: Authorization: Bearer {$rawToken}\n";
echo "\n";
