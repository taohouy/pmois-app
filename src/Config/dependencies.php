<?php

declare(strict_types=1);

use App\Application\Http\Controllers\AiAssignmentController;
use App\Application\Http\Controllers\AiConsumerController;
use App\Application\Http\Controllers\AiContextController;
use App\Application\Http\Controllers\AiProviderController;
use App\Application\Http\Controllers\ApiTokenController;
use App\Application\Http\Controllers\AttachmentController;
use App\Application\Http\Controllers\ClaimController;
use App\Application\Http\Controllers\DashboardController;
use App\Application\Http\Controllers\DependencyController;
use App\Application\Http\Controllers\EnvironmentController;
use App\Application\Http\Controllers\GitProviderController;
use App\Application\Http\Controllers\GovernanceAdoptionController;
use App\Application\Http\Controllers\GovernanceRecordController;
use App\Application\Http\Controllers\GovernanceVersionController;
use App\Application\Http\Controllers\InvitationController;
use App\Application\Http\Controllers\KnowledgeArticleController;
use App\Application\Http\Controllers\KnowledgeLinkController;
use App\Application\Http\Controllers\LineLoginController;
use App\Application\Http\Controllers\MilestoneController;
use App\Application\Http\Controllers\ProjectController;
use App\Application\Http\Controllers\ProjectMemberController;
use App\Application\Http\Controllers\ProjectStatusUpdateController;
use App\Application\Http\Controllers\ProjectDashboardController;
use App\Application\Http\Controllers\ProjectStructureController;
use App\Application\Http\Controllers\ProjectStructureHistoryController;
use App\Application\Http\Controllers\ProjectTemplateController;
use App\Application\Http\Controllers\ReleaseController;
use App\Application\Http\Controllers\RepositoryController;
use App\Application\Http\Controllers\RevisionController;
use App\Application\Http\Controllers\RevisionReviewController;
use App\Application\Http\Controllers\DeploymentController;
use App\Application\Http\Controllers\RfcController;
use App\Application\Http\Controllers\TeamAssignmentController;
use App\Application\Http\Controllers\TechStackController;
use App\Application\Http\Controllers\WorkspaceController;
use App\Application\Http\Controllers\WorkspaceDefaultSettingsController;
use App\Application\Http\Controllers\WorkspaceMemberController;
use App\Application\Http\Controllers\WorkspaceModuleSettingController;
use App\Domain\Ai\AiConsumerRepositoryInterface;
use App\Domain\Ai\AiContextAggregationService;
use App\Domain\Ai\AiContextExportRepositoryInterface;
use App\Domain\Ai\MarkdownFormatterService;
use App\Domain\Audit\AuditTrailRepositoryInterface;
use App\Domain\Auth\ApiTokenRepositoryInterface;
use App\Domain\Auth\InvitationService;
use App\Domain\Auth\LineLoginService;
use App\Domain\Decision\DecisionRegisterRepositoryInterface;
use App\Domain\Decision\DecisionRegisterService;
use App\Domain\Governance\GovernanceAdoptionItemRepositoryInterface;
use App\Domain\Governance\GovernanceAdoptionRepositoryInterface;
use App\Domain\Governance\GovernanceAdoptionService;
use App\Domain\Governance\GovernanceAutoBindService;
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
use App\Domain\Project\AiAssignmentService;
use App\Domain\Project\EnvironmentService;
use App\Domain\Project\MilestoneRepositoryInterface;
use App\Domain\Project\MilestoneService;
use App\Domain\Project\ProfileCompletenessCalculator;
use App\Domain\Project\ProjectAiAssignmentRepositoryInterface;
use App\Domain\Project\ProjectCreationPipeline;
use App\Domain\Project\ProjectDependencyRepositoryInterface;
use App\Domain\Project\ProjectDependencyService;
use App\Domain\Project\ProjectEnvironmentRepositoryInterface;
use App\Domain\Project\ProjectMemberAssignmentRepositoryInterface;
use App\Domain\Project\ProjectMemberRepositoryInterface;
use App\Domain\Project\ProjectProfileCompletenessInterface;
use App\Domain\Project\ProjectReleaseRepositoryInterface;
use App\Domain\Project\ProjectReleaseService;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Project\ProjectStatusUpdateRepositoryInterface;
use App\Domain\Project\ProjectStructureHistoryRepositoryInterface;
use App\Domain\Project\ProjectStructureService;
use App\Domain\Project\ProjectTeamAssignmentService;
use App\Domain\Project\ProjectTechStackRepositoryInterface;
use App\Domain\Project\RepositoryRegistryRepositoryInterface;
use App\Domain\Project\RepositoryRegistryService;
use App\Domain\Project\TechStackService;
use App\Domain\Rfc\RfcCommentRepositoryInterface;
use App\Domain\Rfc\RfcRepositoryInterface;
use App\Domain\Rfc\RfcService;
use App\Domain\Registry\AiConsumerCodeResolverInterface;
use App\Domain\Registry\AiProviderRepositoryInterface;
use App\Domain\Registry\GitProviderRepositoryInterface;
use App\Domain\Registry\GovernanceVersionCheckerInterface;
use App\Domain\Registry\ProjectTemplateRepositoryInterface;
use App\Domain\Registry\ProjectTemplateService;
use App\Domain\Registry\WorkspaceDefaultSettingsRepositoryInterface;
use App\Domain\Registry\WorkspaceDefaultSettingsService;
use App\Domain\Registry\WorkspaceMemberCheckerInterface;
use App\Domain\Workspace\WorkspaceMemberRepositoryInterface;
use App\Domain\Workspace\WorkspaceModuleSettingRepositoryInterface;
use App\Domain\Workspace\WorkspaceRepositoryInterface;
use App\Infrastructure\Http\CurlHttpClient;
use App\Infrastructure\Persistence\MySQL\MySqlAiConsumerCodeResolver;
use App\Infrastructure\Persistence\MySQL\MySqlAiConsumerRepository;
use App\Infrastructure\Persistence\MySQL\MySqlAiContextExportRepository;
use App\Infrastructure\Persistence\MySQL\MySqlApiTokenRepository;
use App\Infrastructure\Persistence\MySQL\MySqlAttachmentRepository;
use App\Infrastructure\Persistence\MySQL\MySqlAuditTrailRepository;
use App\Infrastructure\Persistence\MySQL\MySqlDecisionRegisterRepository;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceAdoptionItemRepository;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceAdoptionRepository;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceRecordRepository;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceVersionChecker;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceVersionItemRepository;
use App\Infrastructure\Persistence\MySQL\MySqlGovernanceVersionRepository;
use App\Infrastructure\Persistence\MySQL\MySqlKnowledgeArticleRepository;
use App\Infrastructure\Persistence\MySQL\MySqlKnowledgeLinkRepository;
use App\Infrastructure\Persistence\MySQL\MySqlMilestoneRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProfileCompletenessProvider;
use App\Infrastructure\Persistence\MySQL\MySqlProjectAiAssignmentRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectDependencyRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectEnvironmentRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectMemberAssignmentRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectMemberRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectReleaseRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectStatusUpdateRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectStructureHistoryRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectTechStackRepository;
use App\Infrastructure\Persistence\MySQL\MySqlRepositoryRegistryRepository;
use App\Infrastructure\Persistence\MySQL\MySqlRfcCommentRepository;
use App\Infrastructure\Persistence\MySQL\MySqlRfcRepository;
use App\Infrastructure\Persistence\MySQL\MySqlRolePermissionRepository;
use App\Infrastructure\Persistence\MySQL\MySqlRoleRepository;
use App\Infrastructure\Persistence\MySQL\MySqlUserRepository;
use App\Infrastructure\Persistence\MySQL\MySqlWorkspaceDefaultSettingsRepository;
use App\Infrastructure\Persistence\MySQL\MySqlWorkspaceMemberChecker;
use App\Infrastructure\Persistence\MySQL\MySqlWorkspaceMemberRepository;
use App\Infrastructure\Persistence\MySQL\MySqlWorkspaceModuleSettingRepository;
use App\Infrastructure\Persistence\MySQL\MySqlWorkspaceRepository;
use App\Infrastructure\Persistence\MySQL\MySqlAiProviderRepository;
use App\Infrastructure\Persistence\MySQL\MySqlGitProviderRepository;
use App\Infrastructure\Persistence\MySQL\MySqlProjectTemplateRepository;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Slim\Psr7\Factory\RequestFactory;

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

    // ===== HTTP client (PSR-18) — สำหรับ LINE Login OIDC เท่านั้น =====
    \Psr\Http\Message\ResponseFactoryInterface::class => fn (ContainerInterface $c) => new \Slim\Psr7\Factory\ResponseFactory(),
    ClientInterface::class => fn (ContainerInterface $c) => new CurlHttpClient($c->get(\Psr\Http\Message\ResponseFactoryInterface::class)),
    RequestFactoryInterface::class => fn (ContainerInterface $c) => new RequestFactory(),
    \Psr\Http\Message\StreamFactoryInterface::class => fn (ContainerInterface $c) => new \Slim\Psr7\Factory\StreamFactory(),

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

    // ===== Governance =====
    GovernanceRecordRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlGovernanceRecordRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    GovernanceVersionRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlGovernanceVersionRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    GovernanceVersionItemRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlGovernanceVersionItemRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    GovernanceAdoptionRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlGovernanceAdoptionRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    GovernanceAdoptionItemRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlGovernanceAdoptionItemRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    GovernanceVersionService::class => fn (ContainerInterface $c) => new GovernanceVersionService($c->get(PDO::class), $c->get(GovernanceVersionRepositoryInterface::class)),
    \App\Domain\Governance\GovernancePolicyService::class => fn (ContainerInterface $c) => new \App\Domain\Governance\GovernancePolicyService(
        $c->get(GovernanceRecordRepositoryInterface::class)
    ),
    GovernanceRecordController::class => fn (ContainerInterface $c) => new GovernanceRecordController(
        $c->get(GovernanceRecordRepositoryInterface::class),
        $c->get(\App\Domain\Governance\GovernancePolicyService::class)
    ),
    GovernanceAdoptionService::class => fn (ContainerInterface $c) => new GovernanceAdoptionService(
        $c->get(GovernanceAdoptionRepositoryInterface::class), $c->get(GovernanceAdoptionItemRepositoryInterface::class), $c->get(GovernanceVersionItemRepositoryInterface::class)
    ),
    GovernanceAutoBindService::class => fn (ContainerInterface $c) => new GovernanceAutoBindService(
        $c->get(GovernanceAdoptionRepositoryInterface::class),
        $c->get(PDO::class),
        $c->get('current_workspace_id')
    ),

    // ===== RFC + Decision =====
    RfcRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlRfcRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    RfcCommentRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlRfcCommentRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    RfcService::class => fn (ContainerInterface $c) => new RfcService($c->get(RfcRepositoryInterface::class), $c->get(DecisionRegisterRepositoryInterface::class)),
    DecisionRegisterRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlDecisionRegisterRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    DecisionRegisterService::class => fn (ContainerInterface $c) => new DecisionRegisterService($c->get(DecisionRegisterRepositoryInterface::class)),

    // ===== Knowledge Base =====
    KnowledgeArticleRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlKnowledgeArticleRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    AttachmentRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlAttachmentRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    KnowledgeLinkRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlKnowledgeLinkRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    AttachmentService::class => fn (ContainerInterface $c) => new AttachmentService(
        $c->get(AttachmentRepositoryInterface::class),
        ($GLOBALS['app_env']['STORAGE_BASE_PATH'] ?? (dirname(__DIR__, 2) . '/storage/uploads'))
    ),
    KnowledgeLinkService::class => fn (ContainerInterface $c) => new KnowledgeLinkService(
        $c->get(KnowledgeLinkRepositoryInterface::class), $c->get(PermissionResolver::class)
    ),

    // ===== AI Context Platform =====
    AiConsumerRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlAiConsumerRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    ProjectStatusUpdateRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlProjectStatusUpdateRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    ProjectStatusUpdateController::class => fn (ContainerInterface $c) => new ProjectStatusUpdateController(
        $c->get(ProjectRepositoryInterface::class),
        $c->get(ProjectStatusUpdateRepositoryInterface::class),
    ),
    AiContextExportRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlAiContextExportRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    AiContextAggregationService::class => fn (ContainerInterface $c) => new AiContextAggregationService(
        $c->get(GovernanceAdoptionRepositoryInterface::class),
        $c->get(ProjectRepositoryInterface::class),
        $c->get(ProjectStatusUpdateRepositoryInterface::class),
        $c->get(DecisionRegisterRepositoryInterface::class),
        $c->get(AiContextExportRepositoryInterface::class)
    ),
    MarkdownFormatterService::class => fn (ContainerInterface $c) => new MarkdownFormatterService(),

    // ===== M1 Phase 1: Foundation Services =====
    MilestoneRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlMilestoneRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    ProjectStructureHistoryRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlProjectStructureHistoryRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    MilestoneService::class => fn (ContainerInterface $c) => new MilestoneService(
        $c->get(MilestoneRepositoryInterface::class),
        $c->get('current_workspace_id')
    ),
    ProjectStructureService::class => fn (ContainerInterface $c) => new ProjectStructureService(
        $c->get(ProjectRepositoryInterface::class),
        $c->get(ProjectStructureHistoryRepositoryInterface::class)
    ),
    ProjectStructureController::class => fn (ContainerInterface $c) => new ProjectStructureController(
        $c->get(ProjectStructureService::class),
        $c->get(PermissionResolver::class)
    ),
    ProjectStructureHistoryController::class => fn (ContainerInterface $c) => new ProjectStructureHistoryController(
        $c->get(ProjectStructureService::class)
    ),
    MilestoneController::class => fn (ContainerInterface $c) => new MilestoneController(
        $c->get(MilestoneService::class),
        $c->get(\App\Domain\Automation\AutomationWorkflowService::class)
    ),

    // ===== M1 Phase 1 / R6 Phase 1.5: Registries (R6-01, R6-02) =====
    AiProviderRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlAiProviderRepository($c->get(PDO::class)),
    GitProviderRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlGitProviderRepository($c->get(PDO::class)),
    ProjectTemplateRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlProjectTemplateRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    WorkspaceDefaultSettingsRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlWorkspaceDefaultSettingsRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    ProjectMemberAssignmentRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlProjectMemberAssignmentRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    ProjectAiAssignmentRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlProjectAiAssignmentRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    RepositoryRegistryRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlRepositoryRegistryRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    ProjectTechStackRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlProjectTechStackRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    ProjectEnvironmentRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlProjectEnvironmentRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    ProjectDependencyRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlProjectDependencyRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    ProjectReleaseRepositoryInterface::class => fn (ContainerInterface $c) => new MySqlProjectReleaseRepository($c->get(PDO::class), $c->get('current_workspace_id')),

    AiConsumerCodeResolverInterface::class => fn (ContainerInterface $c) => new MySqlAiConsumerCodeResolver($c->get(PDO::class), $c->get('current_workspace_id')),
    WorkspaceMemberCheckerInterface::class => fn (ContainerInterface $c) => new MySqlWorkspaceMemberChecker($c->get(PDO::class)),
    GovernanceVersionCheckerInterface::class => fn (ContainerInterface $c) => new MySqlGovernanceVersionChecker($c->get(PDO::class)),

    // ===== M1 Phase 1 / R6 Phase 1.5: Services =====
    AiAssignmentService::class => fn (ContainerInterface $c) => new AiAssignmentService(
        $c->get(ProjectAiAssignmentRepositoryInterface::class),
        $c->get('current_workspace_id')
    ),
    ProjectTeamAssignmentService::class => fn (ContainerInterface $c) => new ProjectTeamAssignmentService(
        $c->get(ProjectMemberAssignmentRepositoryInterface::class),
        $c->get(ProjectMemberRepositoryInterface::class),
        $c->get('current_workspace_id')
    ),
    RepositoryRegistryService::class => fn (ContainerInterface $c) => new RepositoryRegistryService(
        $c->get(RepositoryRegistryRepositoryInterface::class),
        $c->get(GitProviderRepositoryInterface::class),
        $c->get('current_workspace_id')
    ),
    TechStackService::class => fn (ContainerInterface $c) => new TechStackService(
        $c->get(ProjectTechStackRepositoryInterface::class),
        $c->get('current_workspace_id')
    ),
    EnvironmentService::class => fn (ContainerInterface $c) => new EnvironmentService(
        $c->get(ProjectEnvironmentRepositoryInterface::class),
        $c->get('current_workspace_id')
    ),
    ProjectDependencyService::class => fn (ContainerInterface $c) => new ProjectDependencyService(
        $c->get(ProjectDependencyRepositoryInterface::class),
        $c->get(ProjectRepositoryInterface::class),
        $c->get('current_workspace_id')
    ),
    ProjectReleaseService::class => fn (ContainerInterface $c) => new ProjectReleaseService(
        $c->get(ProjectReleaseRepositoryInterface::class),
        $c->get('current_workspace_id')
    ),
    ProjectTemplateService::class => fn (ContainerInterface $c) => new ProjectTemplateService(
        $c->get(ProjectTemplateRepositoryInterface::class),
        $c->get(AiConsumerCodeResolverInterface::class)
    ),
    WorkspaceDefaultSettingsService::class => fn (ContainerInterface $c) => new WorkspaceDefaultSettingsService(
        $c->get(WorkspaceDefaultSettingsRepositoryInterface::class),
        $c->get(WorkspaceMemberCheckerInterface::class),
        $c->get(GovernanceVersionCheckerInterface::class),
        $c->get(GitProviderRepositoryInterface::class),
        $c->get(ProjectTemplateRepositoryInterface::class)
    ),
    ProfileCompletenessCalculator::class => fn (ContainerInterface $c) => new ProfileCompletenessCalculator(
        $c->get(GovernanceAdoptionRepositoryInterface::class),
        $c->get(ProjectMemberAssignmentRepositoryInterface::class),
        $c->get(ProjectAiAssignmentRepositoryInterface::class),
        $c->get(RepositoryRegistryRepositoryInterface::class),
        $c->get(MilestoneRepositoryInterface::class),
        $c->get(ProjectEnvironmentRepositoryInterface::class),
        $c->get(ProjectTechStackRepositoryInterface::class),
        $c->get(ProjectReleaseRepositoryInterface::class)
    ),
    ProjectProfileCompletenessInterface::class => fn (ContainerInterface $c) => new MySqlProfileCompletenessProvider(
        $c->get(ProfileCompletenessCalculator::class),
        $c->get(ProjectRepositoryInterface::class),
        $c->get(PDO::class)
    ),

    // ===== M1 Phase 1 / R6: Project Creation Pipeline =====
    ProjectCreationPipeline::class => fn (ContainerInterface $c) => new ProjectCreationPipeline(
        $c->get(PDO::class),
        $c->get(ProjectRepositoryInterface::class),
        $c->get(ProjectMemberRepositoryInterface::class),
        $c->get(ProjectMemberAssignmentRepositoryInterface::class),
        $c->get(ProjectAiAssignmentRepositoryInterface::class),
        $c->get(MilestoneRepositoryInterface::class),
        $c->get(ProjectTechStackRepositoryInterface::class),
        $c->get(WorkspaceModuleSettingRepositoryInterface::class),
        $c->get(ApiTokenRepositoryInterface::class),
        $c->get(GovernanceAutoBindService::class),
        $c->get(WorkspaceDefaultSettingsService::class),
        $c->get(ProjectTemplateRepositoryInterface::class),
        $c->get(AiConsumerCodeResolverInterface::class),
        $c->get(ProfileCompletenessCalculator::class)
    ),

    // ===== M3: Revision / Review / Deployment =====
    \App\Domain\Project\RevisionRepositoryInterface::class => fn (ContainerInterface $c) => new \App\Infrastructure\Persistence\MySQL\MySqlRevisionRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    \App\Domain\Project\ProjectDeploymentRepositoryInterface::class => fn (ContainerInterface $c) => new \App\Infrastructure\Persistence\MySQL\MySqlProjectDeploymentRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    \App\Domain\Project\RevisionService::class => fn (ContainerInterface $c) => new \App\Domain\Project\RevisionService(
        $c->get(\App\Domain\Project\RevisionRepositoryInterface::class),
        $c->get(MilestoneRepositoryInterface::class),
        $c->get(\App\Domain\Project\ProjectStatusUpdater::class),
        $c->get('current_workspace_id')
    ),
    \App\Domain\Project\ProjectStatusUpdater::class => fn (ContainerInterface $c) => new \App\Domain\Project\ProjectStatusUpdater(
        $c->get(ProjectStatusUpdateRepositoryInterface::class),
        $c->get(\App\Domain\Project\RevisionRepositoryInterface::class)
    ),
    \App\Domain\Project\DeploymentService::class => fn (ContainerInterface $c) => new \App\Domain\Project\DeploymentService(
        $c->get(\App\Domain\Project\ProjectDeploymentRepositoryInterface::class),
        $c->get('current_workspace_id')
    ),

    // ===== M8: Automation Center =====
    \App\Domain\Automation\AutomationJobRepositoryInterface::class => fn (ContainerInterface $c) => new \App\Infrastructure\Persistence\MySQL\MySqlAutomationJobRepository($c->get(PDO::class)),
    \App\Domain\Automation\AutomationWorkflowService::class => fn (ContainerInterface $c) => new \App\Domain\Automation\AutomationWorkflowService(
        $c->get(\App\Domain\Automation\AutomationJobRepositoryInterface::class)
    ),
    \App\Domain\Automation\AutomationJobRunner::class => fn (ContainerInterface $c) => new \App\Domain\Automation\AutomationJobRunner(
        $c->get(PDO::class),
        $c->get(\App\Domain\Automation\AutomationJobRepositoryInterface::class),
        $c->get(\App\Domain\Notification\NotificationService::class),
        $c->get(ClientInterface::class)
    ),
    AutomationController::class => fn (ContainerInterface $c) => new AutomationController(
        $c->get(\App\Domain\Automation\AutomationWorkflowService::class),
        $c->get(\App\Domain\Automation\AutomationJobRepositoryInterface::class),
        $c->get(\App\Domain\Automation\AutomationJobRunner::class)
    ),

    // ===== M7: Portfolio Analytics =====
    \App\Infrastructure\Persistence\MySQL\MySqlAnalyticsRepository::class => fn (ContainerInterface $c) => new \App\Infrastructure\Persistence\MySQL\MySqlAnalyticsRepository($c->get(PDO::class)),
    \App\Domain\Analytics\AnalyticsService::class => fn (ContainerInterface $c) => new \App\Domain\Analytics\AnalyticsService(
        $c->get(\App\Infrastructure\Persistence\MySQL\MySqlAnalyticsRepository::class),
        new \App\Infrastructure\Persistence\MySQL\MySqlDashboardRepository($c->get(PDO::class)),
        $c->get(\App\Domain\Project\ProjectDependencyService::class),
        $c->get('current_workspace_id')
    ),
    AnalyticsController::class => fn (ContainerInterface $c) => new AnalyticsController(
        $c->get(\App\Domain\Analytics\AnalyticsService::class),
        $c->get(PermissionResolver::class)
    ),

    // ===== M6: Knowledge Center =====
    \App\Domain\Knowledge\KnowledgeEntryRepositoryInterface::class => fn (ContainerInterface $c) => new \App\Infrastructure\Persistence\MySQL\MySqlKnowledgeEntryRepository($c->get(PDO::class), $c->get('current_workspace_id')),
    \App\Domain\Knowledge\KnowledgeService::class => fn (ContainerInterface $c) => new \App\Domain\Knowledge\KnowledgeService(
        $c->get(\App\Domain\Knowledge\KnowledgeEntryRepositoryInterface::class),
        $c->get(\App\Domain\Knowledge\KnowledgeArticleRepositoryInterface::class),
        $c->get(\App\Domain\Decision\DecisionRegisterRepositoryInterface::class),
        $c->get('current_workspace_id')
    ),
    KnowledgeController::class => fn (ContainerInterface $c) => new KnowledgeController(
        $c->get(\App\Domain\Knowledge\KnowledgeService::class)
    ),

    // ===== M5: API Platform =====
    \App\Domain\Notification\NotificationRepositoryInterface::class => fn (ContainerInterface $c) => new \App\Infrastructure\Persistence\MySQL\MySqlNotificationRepository($c->get(PDO::class)),
    \App\Domain\Notification\NotificationService::class => fn (ContainerInterface $c) => new \App\Domain\Notification\NotificationService(
        $c->get(\App\Domain\Notification\NotificationRepositoryInterface::class),
        $c->get(ClientInterface::class),
        $c->get(RequestFactoryInterface::class),
        $c->get(\Psr\Http\Message\StreamFactoryInterface::class)
    ),
    RevisionController::class => fn (ContainerInterface $c) => new RevisionController(
        $c->get(\App\Domain\Project\RevisionService::class),
        $c->get(\App\Domain\Notification\NotificationService::class),
        $c->get(\App\Domain\Automation\AutomationWorkflowService::class)
    ),
    RevisionReviewController::class => fn (ContainerInterface $c) => new RevisionReviewController(
        $c->get(\App\Domain\Project\RevisionService::class),
        $c->get(\App\Domain\Notification\NotificationService::class)
    ),
    DeploymentController::class => fn (ContainerInterface $c) => new DeploymentController(
        $c->get(\App\Domain\Project\DeploymentService::class),
        $c->get(\App\Domain\Notification\NotificationService::class),
        $c->get(\App\Domain\Automation\AutomationWorkflowService::class)
    ),
    AuditController::class => fn (ContainerInterface $c) => new AuditController(
        $c->get(\App\Domain\Dashboard\DashboardService::class)
    ),
    PlatformController::class => fn (ContainerInterface $c) => new PlatformController(
        $c->get(PDO::class),
        $c->get(PermissionResolver::class)
    ),

    // ===== M2: Dashboard (read model) =====
    \App\Domain\Dashboard\DashboardService::class => fn (ContainerInterface $c) => new \App\Domain\Dashboard\DashboardService(
        new \App\Infrastructure\Persistence\MySQL\MySqlDashboardRepository($c->get(PDO::class)),
        $c->get('current_workspace_id')
    ),
    DashboardController::class => fn (ContainerInterface $c) => new DashboardController(
        $c->get(\App\Domain\Dashboard\DashboardService::class),
        $c->get(PermissionResolver::class)
    ),
    ProjectDashboardController::class => fn (ContainerInterface $c) => new ProjectDashboardController(
        $c->get(\App\Domain\Dashboard\DashboardService::class)
    ),

    // ===== Controllers (M1 Phase 1 + R6) =====
    ProjectController::class => fn (ContainerInterface $c) => new ProjectController(
        $c->get(ProjectRepositoryInterface::class),
        $c->get(ProjectCreationPipeline::class),
        $c->get(WorkspaceRepositoryInterface::class),
        $c->get(PermissionResolver::class)
    ),
    AiAssignmentController::class => fn (ContainerInterface $c) => new AiAssignmentController(
        $c->get(AiAssignmentService::class)
    ),
    RepositoryController::class => fn (ContainerInterface $c) => new RepositoryController(
        $c->get(RepositoryRegistryService::class),
        $c->get(ProjectProfileCompletenessInterface::class)
    ),
    TeamAssignmentController::class => fn (ContainerInterface $c) => new TeamAssignmentController(
        $c->get(ProjectTeamAssignmentService::class)
    ),
    TechStackController::class => fn (ContainerInterface $c) => new TechStackController(
        $c->get(TechStackService::class),
        $c->get(ProjectProfileCompletenessInterface::class)
    ),
    EnvironmentController::class => fn (ContainerInterface $c) => new EnvironmentController(
        $c->get(EnvironmentService::class),
        $c->get(ProjectProfileCompletenessInterface::class)
    ),
    DependencyController::class => fn (ContainerInterface $c) => new DependencyController(
        $c->get(ProjectDependencyService::class)
    ),
    ReleaseController::class => fn (ContainerInterface $c) => new ReleaseController(
        $c->get(ProjectReleaseService::class),
        $c->get(ProjectProfileCompletenessInterface::class)
    ),
    ProjectTemplateController::class => fn (ContainerInterface $c) => new ProjectTemplateController(
        $c->get(ProjectTemplateService::class)
    ),
    WorkspaceDefaultSettingsController::class => fn (ContainerInterface $c) => new WorkspaceDefaultSettingsController(
        $c->get(WorkspaceDefaultSettingsService::class)
    ),
    AiProviderController::class => fn (ContainerInterface $c) => new AiProviderController(
        $c->get(AiProviderRepositoryInterface::class),
        $c->get(PermissionResolver::class)
    ),
    GitProviderController::class => fn (ContainerInterface $c) => new GitProviderController(
        $c->get(GitProviderRepositoryInterface::class),
        $c->get(PermissionResolver::class)
    ),
    AiConsumerController::class => fn (ContainerInterface $c) => new AiConsumerController(
        $c->get(AiConsumerRepositoryInterface::class)
    ),

    // ===== Auth (LINE Login only — CTO Constraint #1) =====
    LineLoginService::class => fn (ContainerInterface $c) => new LineLoginService(
        $c->get(ClientInterface::class),
        $c->get(RequestFactoryInterface::class),
        $c->get(\Psr\Http\Message\StreamFactoryInterface::class),
        $GLOBALS['app_env']['LINE_CHANNEL_ID'] ?? '',
        $GLOBALS['app_env']['LINE_CHANNEL_SECRET'] ?? '',
        $GLOBALS['app_env']['LINE_REDIRECT_URI'] ?? ''
    ),
    InvitationService::class => fn (ContainerInterface $c) => new InvitationService(
        $c->get(ProjectRepositoryInterface::class),
        $c->get(WorkspaceMemberRepositoryInterface::class),
        $c->get(UserRepositoryInterface::class),
        $c->get(LineLoginService::class),
        $c->get(RoleRepositoryInterface::class)
    ),
    LineLoginController::class => fn (ContainerInterface $c) => new LineLoginController(
        $c->get(\App\Domain\Auth\PmoisAuthenticationService::class)
    ),
    ClaimController::class => fn (ContainerInterface $c) => new ClaimController(
        $c->get(\App\Domain\Auth\PmoisAuthenticationService::class),
        $c->get(LineLoginController::class)
    ),
    \App\Domain\Auth\PmoisAuthenticationService::class => fn (ContainerInterface $c) => new \App\Domain\Auth\PmoisAuthenticationService(
        $c->get(LineLoginService::class),
        $c->get(\App\Domain\Auth\OAuthStateRepositoryInterface::class),
        $c->get(\App\Domain\Auth\UserSessionRepositoryInterface::class),
        $c->get(UserRepositoryInterface::class),
        $c->get(InvitationService::class),
        $c->get(PDO::class)
    ),
    \App\Domain\Auth\OAuthStateRepositoryInterface::class => fn (ContainerInterface $c) => new \App\Infrastructure\Persistence\MySQL\MySqlOAuthStateRepository($c->get(PDO::class)),
    \App\Domain\Auth\UserSessionRepositoryInterface::class => fn (ContainerInterface $c) => new \App\Infrastructure\Persistence\MySQL\MySqlUserSessionRepository($c->get(PDO::class)),
    InvitationController::class => fn (ContainerInterface $c) => new InvitationController(
        $c->get(InvitationService::class)
    ),

];
