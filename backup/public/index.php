<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// ===== โหลด .env แบบเดียวกับ database/migrate.php (เพื่อความสอดคล้อง ไม่ใช้ vlucas/phpdotenv) =====
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

// ===== DI Container =====
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../src/Config/dependencies.php');
$container = $containerBuilder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();

// ===== แก้ไข: ไม่ add() Auth/WorkspaceContext/AuditLogging แบบ global ที่นี่อีกแล้ว =====
// เพราะทำให้ /health โดน auth ไปด้วย (พบจากการทดสอบจริงบน server)
// ย้าย middleware เหล่านี้ไปผูกกับ route group /api/v1 โดยตรงใน routes.php แทน
// ที่นี่เหลือแค่ middleware ที่ "ต้องการครอบทุก route แบบไม่มีข้อยกเว้นจริงๆ" เท่านั้น

$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(
    (bool) ($GLOBALS['app_env']['APP_DEBUG'] ?? false),
    true,
    true
);

// ===== Routes (รวม middleware ผูกกับ group แล้วในไฟล์นี้) =====
(require __DIR__ . '/../src/Config/routes.php')($app, $container);

$app->run();
