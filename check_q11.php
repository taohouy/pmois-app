<?php
$pdo = new PDO('mysql:host=141.98.17.5;port=3306;dbname=pmo_jaidee;charset=utf8mb4', 'jdcloud', 'Look@om30');
$stmt = $pdo->query('
    SELECT r.code as role
    FROM role_permissions rp
    JOIN roles r ON r.id = rp.role_id
    WHERE rp.permission_code = "project.create"
');
$roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Raw: "; var_dump($roles);
$expected = ['ADMIN', 'CTO'];
$actual = $roles;
sort($actual);
sort($expected);
echo "Expected: " . implode(', ', $expected) . "\n";
echo "Actual:   " . implode(', ', $actual) . "\n";
echo "Match: " . ($actual === $expected ? "YES ✅" : "NO ❌") . "\n";