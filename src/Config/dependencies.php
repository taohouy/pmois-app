<?php

declare(strict_types=1);

use App\Domain\Ai\AiConsumerRepositoryInterface;
use App\Domain\Ai\AiContextAggregationService;
use App\Domain\Ai\AiContextExportRepositoryInterface;
use App\Domain\Ai\MarkdownFormatterService;
use App\Domain\Audit\AuditTrailRepositoryInterface;
use App\Domain\Auth\ApiTokenRepositoryInterface;
use App\Domain\Decision\DecisionRegisterRepositoryInterface;
use App\Domain\Decision\DecisionRegisterService;
use App\Domain\Governance\GovernanceAdoptionItemRepositoryInterface;
use App\Domain\Governance\GovernanceAdoptionRepositoryInterface;
use App\Domain\Governance\GovernanceAdoptionService;
use App\Domain\Governance\GovernanceRecordRepositoryInterface;
use App\Domain\Governance\GovernanceVersionItemRepositoryInterface;
use App\Domain\Governance\GovernanceVersionRepositoryInterface;
use App\Domain\Governance\GovernanceVersionService;
use App\Domain\Identity\PermissionResolver;
use App\Domain\Identity\RolePermissionRepositoryInterface;
use App\Domain\Identity\RoleRepositoryInterface;
use App\Domain\Identity\UserRepositoryInterface;
use App\Domain\Knowledge\AttachmentRepositoryInterface;
use App\Domain\Knowledge\AttachmentService;
use App\Domain\Knowledge\KnowledgeArticleRepositoryInterface;
use App\Domain\Knowledge\KnowledgeLinkRepositoryInterface;
use App\Domain\Knowledge\KnowledgeLinkService;
use App\Domain\Project\ProjectMemberRepositoryInterface;
use App\Domain\Project\ProjectStatusUpdateRepositoryInterface;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Rfc\RfcCommentRepositoryInterface;
use App\Domain\Rfc\RfcRepositoryInterface;
use App\Domain\Rfc\RfcService;
use App\Domain\Workspace\WorkspaceMemberRepositoryInterface;
use App\Domain\Workspace\WorkspaceModuleSettingRepositoryInterface;
use App\Domain\Workspace\WorkspaceRepositoryInterface;
use App\Infrastructure\Persistence\MySQL\MySqlAiConsumerRepository;
use App\Infrastructure\Persistence\MySQL\MySqlAiContextExportRepository;
use App\Infrastructure\Persistence\MySQL\MySqlApiTokenRepository;
use App\Infrastructure\Persistence\MySQL\MySqlAttachmentRepository;
use App\Infrastructure\Persistence\MySQL\MySqlAuditTrailRepository;
use App\Infrastructure\Persistence\MySQL\MySqlDecisionRegisterRepository;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceAdoptionItemRepository;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceAdoptionRepository;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceRecordRepository;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceVersionItemRepository;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceVersionRepository;
use App\Infrastructure\Persistence\MySQL\MySqlKnowledgeArticleRepository;
use App\Infrastructure\Persistence\MySQL\MySqlKnowledgeLinkRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectMemberRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectStatusUpdateRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectRepository;
use App\Infrastructure\Persistence\MySQL\MySqlRfcCommentRepository;
use App\Infrastructure\Persistence\MySQL\MySqlRfcRepository;
use App\Infrastructure\Persistence\MySQL\MySqlRolePermissionRepository;
use App\Infrastructure\Persistence\MySQL\MySqlRoleRepository;
use App\Infrastructure\Persistence\MySQL\MySqlUserRepository;
use App\Infrastructure\Persistence\MySQL\MySqlWorkspaceMemberRepository;
use App\Infrastructure\Persistence\MySQL\MySqlWorkspaceModuleSettingRepository;
use App\Infrastructure\Persistence\MySQL\MySqlWorkspaceRepository;
use Psr\Container\ContainerInterface;

return [

    PDO::class => function (): PDO {
        $env = $GLOBALS['app_env'] ?? [];
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $env['DB_HOST'] ?? '127.0.0.1', $env['DB_PORT'] ?? '3306', $env['DB_DATABASE'] ?? ''
        );
        return new PDO($dsn, $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    },

    'current_workspace_id' => null,

    // ===== Foundation =====
    WorkspaceRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlWorkspaceRepository($c->get(PDO::class)),
    UserRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlUserRepository($c->get(PDO::class)),
    RoleRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlRoleRepository($c->get(PDO::class)),
    RolePermissionRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlRolePermissionRepository($c->get(PDO::class)),
    ProjectRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlProjectRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    ProjectMemberRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlProjectMemberRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    WorkspaceMemberRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlWorkspaceMemberRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    WorkspaceModuleSettingRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlWorkspaceModuleSettingRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    ApiTokenRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlApiTokenRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    AuditTrailRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlAuditTrailRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    PermissionResolver::class => fn (ContainerInterface $c) => new PermissionResolver($c->get(PDO::class)),

    // ===== Phase 1: Governance =====
    GovernanceRecordRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlGovernanceRecordRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    GovernanceVersionRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlGovernanceVersionRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    GovernanceVersionItemRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlGovernanceVersionItemRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    GovernanceAdoptionRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlGovernanceAdoptionRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    GovernanceAdoptionItemRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlGovernanceAdoptionItemRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    GovernanceVersionService::class => fn (ContainerInterface $c) => new GovernanceVersionService($c->get(PDO::class), $c->get(GovernanceVersionRepositoryInterface::class)),
    GovernanceAdoptionService::class => fn (ContainerInterface $c) => new GovernanceAdoptionService(
        $c->get(GovernanceAdoptionRepositoryInterface::class), $c->get(GovernanceAdoptionItemRepositoryInterface::class), $c->get(GovernanceVersionItemRepositoryInterface::class)
    ),

    // ===== Phase 1: RFC =====
    RfcRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlRfcRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    RfcCommentRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlRfcCommentRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    RfcService::class => fn (ContainerInterface $c) => new RfcService($c->get(RfcRepositoryInterface::class), $c->get(DecisionRegisterRepositoryInterface::class)),

    // ===== Phase 1: Decision Register =====
    DecisionRegisterRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlDecisionRegisterRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    DecisionRegisterService::class => fn (ContainerInterface $c) => new DecisionRegisterService($c->get(DecisionRegisterRepositoryInterface::class)),

    // ===== Phase 2: Knowledge Base =====
    KnowledgeArticleRepositoryInterface::class => fn (ContainerInterface $c) =>
        new MySqlKnowledgeArticleRepository($c->get(PDO::class), $c->get('current_workspace_id')),

    AttachmentRepositoryInterface::class => fn (ContainerInterface $c) =>
        new MySqlAttachmentRepository($c->get(PDO::class), $c->get('current_workspace_id')),

    KnowledgeLinkRepositoryInterface::class => fn (ContainerInterface $c) =>
        new MySqlKnowledgeLinkRepository($c->get(PDO::class), $c->get('current_workspace_id')),

    AttachmentService::class => fn (ContainerInterface $c) => new AttachmentService(
        $c->get(AttachmentRepositoryInterface::class),
        ($GLOBALS['app_env']['STORAGE_BASE_PATH'] ?? (dirname(__DIR__, 2) . '/storage/uploads'))
    ),

    KnowledgeLinkService::class => fn (ContainerInterface $c) => new KnowledgeLinkService(
        $c->get(KnowledgeLinkRepositoryInterface::class), $c->get(PermissionResolver::class)
    ),

    // ===== Phase 3: AI Context Platform =====
    AiConsumerRepositoryInterface::class => fn (ContainerInterface $c) =>
        new MySqlAiConsumerRepository($c->get(PDO::class), $c->get('current_workspace_id')),

    ProjectStatusUpdateRepositoryInterface::class => fn (ContainerInterface $c) =>
        new MySqlProjectStatusUpdateRepository($c->get(PDO::class), $c->get('current_workspace_id')),

    AiContextExportRepositoryInterface::class => fn (ContainerInterface $c) =>
        new MySqlAiContextExportRepository($c->get(PDO::class), $c->get('current_workspace_id')),

    AiContextAggregationService::class => fn (ContainerInterface $c) => new AiContextAggregationService(
        $c->get(\App\Domain\Governance\GovernanceAdoptionRepositoryInterface::class),
        $c->get(ProjectRepositoryInterface::class),
        $c->get(ProjectStatusUpdateRepositoryInterface::class),
        $c->get(\App\Domain\Decision\DecisionRegisterRepositoryInterface::class),
        $c->get(AiContextExportRepositoryInterface::class)
    ),

    MarkdownFormatterService::class => fn (ContainerInterface $c) => new MarkdownFormatterService(),

];
