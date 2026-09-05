<?php

declare(strict_types=1);

/**
 * PMOIS v2 — Automation Worker (M8 Background Jobs)
 *
 * Usage:
 *   php bin/automation-worker.php                 # run due jobs ครั้งเดียว (เหมาะกับ cron: * * * * *)
 *   php bin/automation-worker.php --limit=20      # รันสูงสุด 20 jobs
 *   php bin/automation-worker.php --watch=30      # วนลูป รันทุก 30 วินาที (dev ใช้ได้ production แนะนำ cron)
 *
 * ทางเลือกผ่าน HTTP (สำหรับ platform ที่ไม่มี cron): POST /api/v1/automation/run (automation.manage)
 */

require __DIR__ . '/../vendor/autoload.php';

// ===== .env (แบบเดียวกับ public/index.php) =====
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

use DI\ContainerBuilder;
use App\Domain\Automation\AutomationJobRunner;

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../src/Config/dependencies.php');
$container = $containerBuilder->build();

$limit = 10;
$watchInterval = 0;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int) substr($arg, 8));
    }
    if (str_starts_with($arg, '--watch=')) {
        $watchInterval = max(5, (int) substr($arg, 8));
    }
}

$runner = $container->get(AutomationJobRunner::class);

do {
    $summary = $runner->runDue($limit);
    printf(
        "[%s] claimed=%d completed=%d failed=%d\n",
        date('Y-m-d H:i:s'),
        $summary['claimed'],
        $summary['completed'],
        $summary['failed']
    );

    if ($watchInterval === 0) {
        break;
    }
    sleep($watchInterval);
} while (true);
