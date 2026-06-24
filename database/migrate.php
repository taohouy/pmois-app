<?php

declare(strict_types=1);

/**
 * PMOIS Custom Migration Runner
 *
 * แก้ไขจากเวอร์ชันก่อน: เลิกห่อ exec($sql) ด้วย beginTransaction()/commit()
 *
 * เหตุผล (พบจากการทดสอบจริงบน server):
 * MySQL ทำ "implicit commit" ทุกครั้งที่เจอ DDL statement (CREATE TABLE, ALTER TABLE ฯลฯ)
 * แม้เราเปิด transaction ไว้ก่อนหน้าก็ตาม -- พอ CREATE TABLE รันเสร็จ MySQL ปิด transaction
 * ไปเองเงียบๆ แล้วพอโค้ดเดิมเรียก commit() ต่อจะเจอ error "There is no active transaction"
 * เพราะ DDL ไม่สามารถ rollback ได้จริงอยู่แล้วใน MySQL การห่อ transaction จึงไม่มีประโยชน์
 * และกลับทำให้ error -- จึงตัดออกทั้งหมด
 *
 * Usage:
 *   php database/migrate.php run
 *   php database/migrate.php status
 */

$rootDir = dirname(__DIR__);
$envFile = $rootDir . '/.env';

if (!file_exists($envFile)) {
    fwrite(STDERR, "ไม่พบไฟล์ .env ที่ {$envFile} -- กรุณาก็อปจาก .env.example แล้วตั้งค่าก่อน\n");
    exit(1);
}

function loadEnv(string $path): array
{
    $env = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        $env[$key] = $value;
    }
    return $env;
}

function envValue(array $env, string $key, ?string $default = null): string
{
    if (isset($env[$key])) {
        return $env[$key];
    }
    if ($default !== null) {
        return $default;
    }
    fwrite(STDERR, "Missing required .env key: {$key}\n");
    exit(1);
}

$env = loadEnv($envFile);

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    envValue($env, 'DB_HOST', '127.0.0.1'),
    envValue($env, 'DB_PORT', '3306'),
    envValue($env, 'DB_DATABASE')
);

try {
    $pdo = new PDO(
        $dsn,
        envValue($env, 'DB_USERNAME'),
        envValue($env, 'DB_PASSWORD'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "DB connection failed: {$e->getMessage()}\n");
    exit(1);
}

$pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS schema_migrations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        version VARCHAR(10) NOT NULL,
        filename VARCHAR(255) NOT NULL,
        checksum VARCHAR(64) NOT NULL,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        applied_by VARCHAR(100) NOT NULL DEFAULT 'local-script',
        UNIQUE KEY uq_schema_migrations_version (version)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

$migrationDir = __DIR__ . '/migrations';
$allFiles = glob($migrationDir . '/[0-9]*.sql') ?: [];
sort($allFiles);

$migrationFiles = array_values(array_filter(
    $allFiles,
    static fn (string $f): bool => !str_ends_with($f, '.rollback.sql')
));

function versionFromFilename(string $path): string
{
    return substr(basename($path), 0, 4);
}

$appliedRows = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$appliedSet = array_flip($appliedRows);

$command = $argv[1] ?? 'status';

if ($command === 'status') {
    echo "Version | Status    | File\n";
    echo "--------|-----------|--------------------------------------------\n";
    if (empty($migrationFiles)) {
        echo "(ไม่พบไฟล์ migration ใดๆ ใน database/migrations/)\n";
    }
    foreach ($migrationFiles as $file) {
        $version = versionFromFilename($file);
        $status = isset($appliedSet[$version]) ? 'applied' : 'pending';
        printf("%-7s | %-9s | %s\n", $version, $status, basename($file));
    }
    exit(0);
}

if ($command === 'run') {
    $appliedCount = 0;
    foreach ($migrationFiles as $file) {
        $version = versionFromFilename($file);
        if (isset($appliedSet[$version])) {
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            fwrite(STDERR, "อ่านไฟล์ไม่ได้: {$file}\n");
            exit(1);
        }
        $checksum = hash('sha256', $sql);

        echo 'Applying ' . $version . ' — ' . basename($file) . ' ... ';

        try {
            // ไม่ห่อด้วย transaction แล้ว -- DDL ของ MySQL auto-commit เสมออยู่แล้ว
            // (ดูเหตุผลเต็มใน docblock ด้านบน)
            $pdo->exec($sql);

            $stmt = $pdo->prepare(
                'INSERT INTO schema_migrations (version, filename, checksum, applied_by)
                 VALUES (:version, :filename, :checksum, :applied_by)'
            );
            $stmt->execute([
                'version' => $version,
                'filename' => basename($file),
                'checksum' => $checksum,
                'applied_by' => 'local-script',
            ]);

            echo "OK\n";
            $appliedCount++;
        } catch (Throwable $e) {
            fwrite(STDERR, "FAILED: {$e->getMessage()}\n");
            fwrite(STDERR, "หยุดการ migrate — กรุณาแก้ไขไฟล์ {$file} แล้วรันใหม่\n");
            fwrite(STDERR, "หมายเหตุ: ถ้า CREATE TABLE รันสำเร็จไปแล้วก่อน error (เช่น error เกิดที่ INSERT\n");
            fwrite(STDERR, "ลง schema_migrations) อาจต้องลบ table ที่สร้างไปแล้วด้วยมือก่อนรันใหม่ เพราะ\n");
            fwrite(STDERR, "DDL ที่ commit ไปแล้วจะไม่ rollback ให้อัตโนมัติ\n");
            exit(1);
        }
    }

    if ($appliedCount === 0) {
        echo "ไม่มี migration ใหม่ที่ต้อง apply (ทุกอย่าง up-to-date)\n";
    } else {
        echo "Applied {$appliedCount} migration(s) สำเร็จ\n";
    }
    exit(0);
}

fwrite(STDERR, "Unknown command: {$command}\n");
fwrite(STDERR, "Usage: php database/migrate.php [run|status]\n");
exit(1);
