<?php

declare(strict_types=1);

use App\Domain\Audit\AuditTrailRepositoryInterface;
use App\Domain\Identity\PermissionResolver;
use App\Domain\Identity\RolePermissionRepositoryInterface;
use App\Domain\Identity\RoleRepositoryInterface;
use App\Domain\Identity\UserRepositoryInterface;
use App\Domain\Project\ProjectMemberRepositoryInterface;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Workspace\WorkspaceMemberRepositoryInterface;
use App\Domain\Workspace\WorkspaceModuleSettingRepositoryInterface;
use App\Domain\Workspace\WorkspaceRepositoryInterface;
use App\Infrastructure\Persistence\MySQL\MySqlApiTokenRepository;
use App\Infrastructure\Persistence\MySQL\MySqlAuditTrailRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectMemberRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectRepository;
use App\Infrastructure\Persistence\MySQL\MySqlRolePermissionRepository;
use App\Infrastructure\Persistence\MySQL\MySqlRoleRepository;
use App\Infrastructure\Persistence\MySQL\MySqlUserRepository;
use App\Infrastructure\Persistence\MySQL\MySqlWorkspaceMemberRepository;
use App\Infrastructure\Persistence\MySQL\MySqlWorkspaceModuleSettingRepository;
use App\Infrastructure\Persistence\MySQL\MySqlWorkspaceRepository;
use Psr\Container\ContainerInterface;

/**
 * DI Bindings สำหรับ Foundation Layer (Phase 0)
 *
 * แก้ไข (พบ bug จริงจากการทดสอบบน server): เอา indirection ที่อ่านจาก
 * 'request_workspace_id' ออก (ไม่มีใครเคย set ค่านี้จริง) ใช้ default เป็น null
 * ตรงๆ แทน -- ตัวที่ set ค่าจริงคือ AuthTokenMiddleware ผ่าน $container->set()
 * (ดู AuthTokenMiddleware.php) ซึ่งจะ override ค่า default นี้ก่อน Controller/Repository
 * ถูกสร้างขึ้นเสมอ เพราะ middleware รันก่อน route handler ตาม pipeline
 */
return [

    PDO::class => function (): PDO {
        $env = $GLOBALS['app_env'] ?? [];
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $env['DB_HOST'] ?? '127.0.0.1',
            $env['DB_PORT'] ?? '3306',
            $env['DB_DATABASE'] ?? ''
        );
        return new PDO($dsn, $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    },

    // ค่า default -- AuthTokenMiddleware จะ override ด้วย container->set() เสมอ
    // ก่อนที่ Controller/Repository ใดๆ จะถูกสร้างขึ้นจริง (ดูเหตุผลเต็มใน AuthTokenMiddleware.php)
    'current_workspace_id' => null,

    // ===== Top-level repositories (ไม่ scope) =====
    WorkspaceRepositoryInterface::class => fn (ContainerInterface $c) =>
        new MySqlWorkspaceRepository($c->get(PDO::class)),

    UserRepositoryInterface::class => fn (ContainerInterface $c) =>
        new MySqlUserRepository($c->get(PDO::class)),

    RoleRepositoryInterface::class => fn (ContainerInterface $c) =>
        new MySqlRoleRepository($c->get(PDO::class)),

    RolePermissionRepositoryInterface::class => fn (ContainerInterface $c) =>
        new MySqlRolePermissionRepository($c->get(PDO::class)),

    // ===== Scoped repositories =====
    ProjectRepositoryInterface::class => fn (ContainerInterface $c) =>
        new MySqlProjectRepository($c->get(PDO::class), $c->get('current_workspace_id')),

    ProjectMemberRepositoryInterface::class => fn (ContainerInterface $c) =>
        new MySqlProjectMemberRepository($c->get(PDO::class), $c->get('current_workspace_id')),

    WorkspaceMemberRepositoryInterface::class => fn (ContainerInterface $c) =>
        new MySqlWorkspaceMemberRepository($c->get(PDO::class), $c->get('current_workspace_id')),

    WorkspaceModuleSettingRepositoryInterface::class => fn (ContainerInterface $c) =>
        new MySqlWorkspaceModuleSettingRepository($c->get(PDO::class), $c->get('current_workspace_id')),

    MySqlApiTokenRepository::class => fn (ContainerInterface $c) =>
        new MySqlApiTokenRepository($c->get(PDO::class), $c->get('current_workspace_id')),

    // ===== Write-mostly / Immutable =====
    AuditTrailRepositoryInterface::class => fn (ContainerInterface $c) =>
        new MySqlAuditTrailRepository($c->get(PDO::class), $c->get('current_workspace_id')),

    // ===== Service =====
    PermissionResolver::class => fn (ContainerInterface $c) =>
        new PermissionResolver($c->get(PDO::class)),

];