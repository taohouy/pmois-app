<?php

// PHP 7.4 compatibility polyfills
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle !== '' && substr($haystack, -strlen($needle)) === $needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle !== '' && strpos($haystack, $needle) !== false;
    }
}

// Direct PDO without Composer autoloader

$env = [];
foreach (file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$k, $v] = array_map('trim', explode('=', $line, 2));
    $env[$k] = trim($v, "\"'");
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $env['DB_HOST'] ?? '127.0.0.1',
    $env['DB_PORT'] ?? '3306',
    $env['DB_DATABASE']
);

$pdo = new PDO(
    'mysql:host=141.98.17.5;port=3306;dbname=pmo_jaidee;charset=utf8mb4',
    'jdcloud',
    'Look@om30',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

echo "=== Permission Matrix ===\n";
$stmt = $pdo->query('
    SELECT r.code as role, rp.permission_code
    FROM role_permissions rp
    JOIN roles r ON r.id = rp.role_id
    ORDER BY r.code, rp.permission_code
');
foreach ($stmt->fetchAll() as $row) {
    echo $row['role'] . ' -> ' . $row['permission_code'] . "\n";
}

echo "\n=== project.create grants ===\n";
$stmt = $pdo->query("
    SELECT r.code as role
    FROM role_permissions rp
    JOIN roles r ON r.id = rp.role_id
    WHERE rp.permission_code = 'project.create'
");
foreach ($stmt->fetchAll() as $row) {
    echo $row['role'] . "\n";
}

echo "\n=== All 8 new M0 permissions ===\n";
$stmt = $pdo->query("
    SELECT r.code as role, rp.permission_code
    FROM role_permissions rp
    JOIN roles r ON r.id = rp.role_id
    WHERE rp.permission_code IN (
        'project.structure.update',
        'project.progress.update',
        'milestone.close',
        'milestone.open',
        'revision.create',
        'revision.review',
        'repository.manage',
        'ai_assignment.manage'
    )
    ORDER BY rp.permission_code, r.code
");
foreach ($stmt->fetchAll() as $row) {
    echo $row['permission_code'] . ' -> ' . $row['role'] . "\n";
}

echo "\n=== Verify Q11: project.create restricted to ADMIN/CTO only ===\n";
$stmt = $pdo->query("
    SELECT r.code as role
    FROM role_permissions rp
    JOIN roles r ON r.id = rp.role_id
    WHERE rp.permission_code = 'project.create'
");
$roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
$expected = ['ADMIN', 'CTO'];
$actual = $stmt->fetchAll(PDO::FETCH_COLUMN);
sort($actual);
sort($expected);
echo "Expected: " . implode(', ', $expected) . "\n";
echo "Actual:   " . implode(', ', $actual) . "\n";
echo "Match: " . ($actual === $expected ? "YES ✅" : "NO ❌") . "\n";

echo "\n=== Verify 8 new permissions count per role ===\n";
$stmt = $pdo->query("
    SELECT r.code as role, COUNT(*) as count
    FROM role_permissions rp
    JOIN roles r ON r.id = rp.role_id
    WHERE rp.permission_code IN (
        'project.structure.update',
        'project.progress.update',
        'milestone.close',
        'milestone.open',
        'revision.create',
        'revision.review',
        'repository.manage',
        'ai_assignment.manage'
    )
    GROUP BY r.code
    ORDER BY r.code
");
foreach ($stmt->fetchAll() as $row) {
    echo $row['role'] . ': ' . $row['count'] . " permissions\n";
}