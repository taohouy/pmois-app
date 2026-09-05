<?php

declare(strict_types=1);

/**
 * Create the first PMOIS platform admin (M9 Installation step 4)
 *
 * Usage:
 *   php bin/create-admin.php <email> [name]
 *
 * ถ้า email มีอยู่แล้วแต่ไม่ใช่ admin → promote เป็น admin
 * ขั้นถัดไป: php bin/admin-claim-url.php <email> (ผูก LINE account)
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
$name = $argv[2] ?? 'PMOIS Admin';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php bin/create-admin.php <email> [name]\n");
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

$stmt = $db->prepare('SELECT id, is_platform_admin FROM users WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
$existing = $stmt->fetch();

if ($existing !== false) {
    if ((bool) $existing['is_platform_admin']) {
        echo "user #{$existing['id']} is already a platform admin\n";
        exit(0);
    }
    $db->prepare('UPDATE users SET is_platform_admin = 1 WHERE id = :id')->execute(['id' => $existing['id']]);
    echo "promoted existing user #{$existing['id']} ({$email}) to platform admin\n";
    exit(0);
}

$stmt = $db->prepare(
    "INSERT INTO users (name, email, password_hash, status, is_platform_admin, auth_provider)
     VALUES (:name, :email, NULL, 'active', 1, 'line')"
);
$stmt->execute(['name' => $name, 'email' => $email]);
$id = (int) $db->lastInsertId();

echo "created platform admin #{$id} ({$email})\n";
echo "next: php bin/admin-claim-url.php {$email}\n";
