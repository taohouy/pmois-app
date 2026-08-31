<?php
$pdo = new PDO('mysql:host=141.98.17.5;port=3306;dbname=pmo_jaidee;charset=utf8mb4', 'jdcloud', 'Look@om30');

echo "=== Migration Status (0033-0042) ===\n";
$stmt = $pdo->query('SELECT version FROM schema_migrations WHERE version BETWEEN "0033" AND "0042" ORDER BY version');
foreach ($stmt->fetchAll() as $row) {
    echo $row['version'] . " applied\n";
}

echo "\n=== Permission Verification ===\n";
$stmt = $pdo->query('
    SELECT r.code as role, rp.permission_code
    FROM role_permissions rp
    JOIN roles r ON r.id = rp.role_id
    WHERE rp.permission_code IN (
        "project.structure.update", "project.progress.update", "milestone.close", "milestone.open",
        "revision.create", "revision.review", "repository.manage", "ai_assignment.manage"
    )
    ORDER BY rp.permission_code, r.code
');
foreach ($stmt->fetchAll() as $row) {
    echo $row['permission_code'] . ' -> ' . $row['role'] . "\n";
}

echo "\n=== Project Structure Tables ===\n";
$tables = ['project_structure_history', 'milestones', 'repositories', 'revisions', 'revision_reviews', 'project_ai_assignments'];
foreach ($tables as $table) {
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM $table");
    $cnt = $stmt->fetchColumn();
    echo $table . ": " . $cnt . " rows\n";
}

echo "\n=== Project Structure Columns ===\n";
$stmt = $pdo->query('DESCRIBE projects');
foreach ($stmt->fetchAll() as $row) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}

echo "\n=== Permission Verification ===\n";
$stmt = $pdo->query('
    SELECT r.code as role
    FROM role_permissions rp
    JOIN roles r ON r.id = rp.role_id
    WHERE rp.permission_code = "project.create"
');
$roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
$expected = ['ADMIN', 'CTO'];
$actual = $stmt->fetchAll(PDO::FETCH_COLUMN);
sort($actual);
sort($expected);
echo "Expected: " . implode(', ', $expected) . "\n";
echo "Actual:   " . implode(', ', $actual) . "\n";
echo "Match: " . ($actual === $expected ? "YES ✅" : "NO ❌") . "\n";