<?php

declare(strict_types=1);

use App\Application\Http\Controllers\AiConsumerController;
use App\Application\Http\Controllers\AnalyticsController;
use App\Application\Http\Controllers\AutomationController;
use App\Application\Http\Controllers\AiContextController;
use App\Application\Http\Controllers\AiProviderController;
use App\Application\Http\Controllers\AiAssignmentController;
use App\Application\Http\Controllers\ApiTokenController;
use App\Application\Http\Controllers\AttachmentController;
use App\Application\Http\Controllers\AuditController;
use App\Application\Http\Controllers\ClaimController;
use App\Application\Http\Controllers\DashboardController;
use App\Application\Http\Controllers\DependencyController;
use App\Application\Http\Controllers\DecisionRegisterController;
use App\Application\Http\Controllers\DeploymentController;
use App\Application\Http\Controllers\EnvironmentController;
use App\Application\Http\Controllers\GitProviderController;
use App\Application\Http\Controllers\GovernanceAdoptionController;
use App\Application\Http\Controllers\GovernanceRecordController;
use App\Application\Http\Controllers\GovernanceVersionController;
use App\Application\Http\Controllers\InvitationController;
use App\Application\Http\Controllers\KnowledgeArticleController;
use App\Application\Http\Controllers\KnowledgeController;
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
use App\Application\Http\Controllers\PlatformController;
use App\Application\Http\Controllers\ReleaseController;
use App\Application\Http\Controllers\RepositoryController;
use App\Application\Http\Controllers\RevisionController;
use App\Application\Http\Controllers\RevisionReviewController;
use App\Application\Http\Controllers\RfcController;
use App\Application\Http\Controllers\TeamAssignmentController;
use App\Application\Http\Controllers\TechStackController;
use App\Application\Http\Controllers\WorkspaceController;
use App\Application\Http\Controllers\WorkspaceDefaultSettingsController;
use App\Application\Http\Controllers\WorkspaceMemberController;
use App\Application\Http\Controllers\WorkspaceModuleSettingController;
use App\Application\Middleware\AiAccessControlMiddleware;
use App\Application\Middleware\ApiScopeMiddleware;
use App\Application\Middleware\AuditLoggingMiddleware;
use App\Application\Middleware\AuthTokenMiddleware;
use App\Application\Middleware\ProjectScopeMiddleware;
use App\Application\Middleware\RateLimitMiddleware;
use App\Application\Middleware\RequiresPermissionMiddleware;
use App\Application\Middleware\WorkspaceContextMiddleware;
use App\Domain\Identity\PermissionResolver;
use Psr\Container\ContainerInterface;
use Slim\App;

return function (App $app, ContainerInterface $container): void {

    $resolver = $container->get(PermissionResolver::class);

    $app->get('/api/v1/health', function ($request, $response) use ($container) {
        // M5: health check ตรวจ DB connectivity ด้วย
        $dbOk = false;
        try {
            $container->get(PDO::class)->query('SELECT 1');
            $dbOk = true;
        } catch (\Throwable) {
            $dbOk = false;
        }

        $response->getBody()->write(json_encode([
            'status' => $dbOk ? 'ok' : 'degraded',
            'checks' => ['api' => true, 'database' => $dbOk],
            'time' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->group('/api/v1', function ($group) use ($resolver) {

        // ===== Foundation (Phase 0 -- ไม่เปลี่ยน) =====
        $group->post('/workspaces', WorkspaceController::class . ':create');
        $group->get('/workspaces/{id}', WorkspaceController::class . ':show')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.view'));

        $group->get('/workspace-members', WorkspaceMemberController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.view'));
        $group->post('/workspace-members', WorkspaceMemberController::class . ':invite')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace_member.invite'));
        $group->put('/workspace-members/{user_id}', WorkspaceMemberController::class . ':updateRole')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace_member.update_role'));
        $group->delete('/workspace-members/{user_id}', WorkspaceMemberController::class . ':remove')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace_member.remove'));

        $group->get('/projects', ProjectController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view'));
        $group->post('/projects', ProjectController::class . ':create')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.create'));
        $group->put('/projects/{id}/close', ProjectController::class . ':close')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.close', 'id'));

        // ===== Inbound Status API =====
        $group->post('/projects/{project_id}/status', ProjectStatusUpdateController::class . ':submit')
            ->add(new RequiresPermissionMiddleware($resolver, 'project_status_update.create', 'project_id'));
        $group->get('/projects/{project_id}/status', ProjectStatusUpdateController::class . ':latest')
            ->add(new RequiresPermissionMiddleware($resolver, 'project_status_update.view', 'project_id'));
        $group->get('/projects/{project_id}/status/history', ProjectStatusUpdateController::class . ':history')
            ->add(new RequiresPermissionMiddleware($resolver, 'project_status_update.view', 'project_id'));

        $group->get('/projects/{project_id}/members', ProjectMemberController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'project_id'));
        $group->post('/projects/{project_id}/members', ProjectMemberController::class . ':add')
            ->add(new RequiresPermissionMiddleware($resolver, 'project_member.manage', 'project_id'));
        $group->delete('/projects/{project_id}/members/{user_id}', ProjectMemberController::class . ':remove')
            ->add(new RequiresPermissionMiddleware($resolver, 'project_member.manage', 'project_id'));

        $group->get('/workspace-module-settings', WorkspaceModuleSettingController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace_module_setting.manage'));
        $group->put('/workspace-module-settings/{module_code}', WorkspaceModuleSettingController::class . ':update')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace_module_setting.manage'));

        $group->post('/auth/tokens', ApiTokenController::class . ':create')
            ->add(new RequiresPermissionMiddleware($resolver, 'api_token.create'));
        $group->get('/auth/tokens', ApiTokenController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'api_token.view'));
        $group->delete('/auth/tokens/{id}', ApiTokenController::class . ':revoke')
            ->add(new RequiresPermissionMiddleware($resolver, 'api_token.revoke'));

        // ===== M4: Governance Policies + Working Instructions =====
        $group->get('/governance-policies', GovernanceRecordController::class . ':policies')
            ->add(new RequiresPermissionMiddleware($resolver, 'governance_record.view'));
        $group->get('/governance/working-instructions', GovernanceRecordController::class . ':workingInstructions')
            ->add(new RequiresPermissionMiddleware($resolver, 'governance_record.view'));

        // ===== M1 Phase 1: Governance Record =====
        $group->get('/governance-records', GovernanceRecordController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'governance_record.view'));
        $group->post('/governance-records', GovernanceRecordController::class . ':create')
            ->add(new RequiresPermissionMiddleware($resolver, 'governance_record.create'));
        $group->get('/governance-records/{id}', GovernanceRecordController::class . ':show')
            ->add(new RequiresPermissionMiddleware($resolver, 'governance_record.view'));
        $group->put('/governance-records/{id}/archive', GovernanceRecordController::class . ':archive')
            ->add(new RequiresPermissionMiddleware($resolver, 'governance_record.archive'));

        // ===== Phase 1: Governance Version + Items =====
        $group->get('/governance-records/{record_id}/versions', GovernanceVersionController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'governance_version.view'));
        $group->post('/governance-records/{record_id}/versions', GovernanceVersionController::class . ':create')
            ->add(new RequiresPermissionMiddleware($resolver, 'governance_version.create'));
        $group->put('/governance-versions/{id}/publish', GovernanceVersionController::class . ':publish')
            ->add(new RequiresPermissionMiddleware($resolver, 'governance_version.publish'));
        $group->get('/governance-versions/{id}/items', GovernanceVersionController::class . ':listItems')
            ->add(new RequiresPermissionMiddleware($resolver, 'governance_version.view'));
        $group->post('/governance-versions/{id}/items', GovernanceVersionController::class . ':createItem')
            ->add(new RequiresPermissionMiddleware($resolver, 'governance_version_item.manage'));

        // ===== Phase 1: Governance Adoption + Items =====
        $group->get('/projects/{project_id}/governance-adoptions', GovernanceAdoptionController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'governance_adoption.view', 'project_id'));
        $group->post('/projects/{project_id}/governance-adoptions', GovernanceAdoptionController::class . ':create')
            ->add(new RequiresPermissionMiddleware($resolver, 'governance_adoption.create', 'project_id'));
        $group->put('/governance-adoptions/{id}/retire', GovernanceAdoptionController::class . ':retire')
            ->add(new RequiresPermissionMiddleware($resolver, 'governance_adoption.retire'));
        $group->get('/governance-adoptions/{id}/items', GovernanceAdoptionController::class . ':listItems')
            ->add(new RequiresPermissionMiddleware($resolver, 'governance_adoption.view'));
        $group->put('/governance-adoption-items/{item_id}', GovernanceAdoptionController::class . ':updateItem')
            ->add(new RequiresPermissionMiddleware($resolver, 'governance_adoption_item.update'));

        // ===== Phase 1: RFC =====
        $group->get('/rfcs', RfcController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'rfc.view'));
        $group->post('/rfcs', RfcController::class . ':create')
            ->add(new RequiresPermissionMiddleware($resolver, 'rfc.create'));
        $group->get('/rfcs/{id}', RfcController::class . ':show')
            ->add(new RequiresPermissionMiddleware($resolver, 'rfc.view'));
        $group->put('/rfcs/{id}/submit', RfcController::class . ':submit')
            ->add(new RequiresPermissionMiddleware($resolver, 'rfc.submit'));
        $group->put('/rfcs/{id}/review', RfcController::class . ':review')
            ->add(new RequiresPermissionMiddleware($resolver, 'rfc.review'));
        $group->post('/rfcs/{id}/convert-to-decision', RfcController::class . ':convertToDecision')
            ->add(new RequiresPermissionMiddleware($resolver, 'rfc.convert_to_decision'));
        $group->get('/rfcs/{id}/comments', RfcController::class . ':listComments')
            ->add(new RequiresPermissionMiddleware($resolver, 'rfc.view'));
        $group->post('/rfcs/{id}/comments', RfcController::class . ':addComment')
            ->add(new RequiresPermissionMiddleware($resolver, 'rfc.comment'));

        // ===== Phase 1: Decision Register =====
        $group->get('/decision-registers', DecisionRegisterController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'decision_register.view'));
        $group->post('/decision-registers', DecisionRegisterController::class . ':create')
            ->add(new RequiresPermissionMiddleware($resolver, 'decision_register.create'));
        $group->get('/decision-registers/{id}', DecisionRegisterController::class . ':show')
            ->add(new RequiresPermissionMiddleware($resolver, 'decision_register.view'));
        $group->put('/decision-registers/{id}/approve', DecisionRegisterController::class . ':approve')
            ->add(new RequiresPermissionMiddleware($resolver, 'decision_register.approve'));

        // ===== Phase 2: Knowledge Article =====
        $group->get('/knowledge-articles', KnowledgeArticleController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'knowledge_article.view'));
        $group->get('/knowledge-articles/search', KnowledgeArticleController::class . ':search')
            ->add(new RequiresPermissionMiddleware($resolver, 'knowledge_article.view'));
        $group->get('/knowledge-categories/suggestions', KnowledgeArticleController::class . ':categorySuggestions')
            ->add(new RequiresPermissionMiddleware($resolver, 'knowledge_article.view'));
        $group->post('/knowledge-articles', KnowledgeArticleController::class . ':create')
            ->add(new RequiresPermissionMiddleware($resolver, 'knowledge_article.create'));
        $group->get('/knowledge-articles/{id}', KnowledgeArticleController::class . ':show')
            ->add(new RequiresPermissionMiddleware($resolver, 'knowledge_article.view'));
        $group->put('/knowledge-articles/{id}', KnowledgeArticleController::class . ':update')
            ->add(new RequiresPermissionMiddleware($resolver, 'knowledge_article.update'));
        $group->put('/knowledge-articles/{id}/publish', KnowledgeArticleController::class . ':publish')
            ->add(new RequiresPermissionMiddleware($resolver, 'knowledge_article.publish'));
        $group->put('/knowledge-articles/{id}/archive', KnowledgeArticleController::class . ':archive')
            ->add(new RequiresPermissionMiddleware($resolver, 'knowledge_article.archive'));

        // ===== Phase 2: Attachment =====
        $group->post('/attachments', AttachmentController::class . ':upload')
            ->add(new RequiresPermissionMiddleware($resolver, 'attachment.upload'));
        $group->delete('/attachments/{id}', AttachmentController::class . ':delete')
            ->add(new RequiresPermissionMiddleware($resolver, 'attachment.delete'));

        // ===== Phase 2: Knowledge Link =====
        // ⚠️ ไม่ผูก RequiresPermissionMiddleware แบบ static -- KnowledgeLinkService
        // เช็คสิทธิ์เอง dynamic ตาม entity_type (ตาม CTO Decision หมวด 2.5)
        $group->get('/knowledge-links', KnowledgeLinkController::class . ':index');
        $group->post('/knowledge-links', KnowledgeLinkController::class . ':create');

        // ===== Phase 3: AI Consumer Registry =====
        $group->get('/ai-consumers', AiConsumerController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'ai_consumer.view'));
        $group->post('/ai-consumers', AiConsumerController::class . ':create')
            ->add(new RequiresPermissionMiddleware($resolver, 'ai_consumer.manage'));
        $group->put('/ai-consumers/{id}/status', AiConsumerController::class . ':updateStatus')
            ->add(new RequiresPermissionMiddleware($resolver, 'ai_consumer.manage'));

        // ===== Phase 3: PMO Context API (4 endpoint ที่ AI token เข้าได้ตาม allowlist) =====
        $group->get('/pmo-context', AiContextController::class . ':pmoContext')
            ->add(new RequiresPermissionMiddleware($resolver, 'ai_context.export'));
        $group->get('/projects/status', AiContextController::class . ':projectsStatus')
            ->add(new RequiresPermissionMiddleware($resolver, 'ai_context.export'));
        $group->get('/governance/summary', AiContextController::class . ':governanceSummary')
            ->add(new RequiresPermissionMiddleware($resolver, 'ai_context.export'));
        $group->get('/decisions/recent', AiContextController::class . ':decisionsRecent')
            ->add(new RequiresPermissionMiddleware($resolver, 'ai_context.export'));

        // ================================================================
        // ===== M1 Phase 1 (per M0 Design Freeze Revision 6) ============
        // ================================================================

        // ===== Auth: LINE Login only (CTO Constraint #1) — guest, อยู่นอก group เพราะไม่มี token =====
        // (ประกาศหลัง group ด้านล่าง)

        // ===== Project Structure / Hierarchy =====
        $group->patch('/projects/{id}/structure', ProjectStructureController::class . ':update')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.structure.update', 'id'));
        $group->get('/projects/{id}/structure-history', ProjectStructureHistoryController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'id'));

        // ===== Milestone Foundation =====
        $group->get('/projects/{project_id}/milestones', MilestoneController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'project_id'));
        $group->post('/projects/{project_id}/milestones', MilestoneController::class . ':create')
            ->add(new RequiresPermissionMiddleware($resolver, 'milestone.create', 'project_id'));
        $group->patch('/milestones/{id}/title', MilestoneController::class . ':updateTitle')
            ->add(new RequiresPermissionMiddleware($resolver, 'milestone.update', 'id'));
        $group->patch('/milestones/{id}/close', MilestoneController::class . ':close')
            ->add(new RequiresPermissionMiddleware($resolver, 'milestone.close', 'id'));
        $group->patch('/milestones/{id}/reopen', MilestoneController::class . ':reopen')
            ->add(new RequiresPermissionMiddleware($resolver, 'milestone.open', 'id'));

        // ===== Project Team Registry (ledger + history) =====
        $group->get('/projects/{project_id}/team-assignments', TeamAssignmentController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'project_id'));
        $group->post('/projects/{project_id}/team-assignments', TeamAssignmentController::class . ':assign')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.team.manage', 'project_id'));
        $group->patch('/team-assignments/{id}/revoke', TeamAssignmentController::class . ':revoke')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.team.manage', 'id'));

        // ===== AI Assignment =====
        $group->get('/projects/{project_id}/ai-assignments', AiAssignmentController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'project_id'));
        $group->post('/projects/{project_id}/ai-assignments', AiAssignmentController::class . ':assign')
            ->add(new RequiresPermissionMiddleware($resolver, 'ai_assignment.manage', 'project_id'));
        $group->patch('/ai-assignments/{id}/revoke', AiAssignmentController::class . ':revoke')
            ->add(new RequiresPermissionMiddleware($resolver, 'ai_assignment.manage', 'id'));

        // ===== GitLab Repository Registry (GitLab only — CTO Constraint #2) =====
        $group->get('/projects/{project_id}/repositories', RepositoryController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'project_id'));
        $group->post('/projects/{project_id}/repositories', RepositoryController::class . ':register')
            ->add(new RequiresPermissionMiddleware($resolver, 'repository.manage', 'project_id'));
        $group->patch('/repositories/{id}', RepositoryController::class . ':update')
            ->add(new RequiresPermissionMiddleware($resolver, 'repository.manage', 'id'));

        // ===== Technology Stack Registry =====
        $group->get('/projects/{project_id}/tech-stack', TechStackController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'project_id'));
        $group->post('/projects/{project_id}/tech-stack', TechStackController::class . ':add')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.techstack.manage', 'project_id'));
        $group->delete('/projects/{project_id}/tech-stack/{id}', TechStackController::class . ':delete')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.techstack.manage', 'project_id'));

        // ===== Environment Registry =====
        $group->get('/projects/{project_id}/environments', EnvironmentController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'project_id'));
        $group->post('/projects/{project_id}/environments', EnvironmentController::class . ':add')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.environment.manage', 'project_id'));
        $group->delete('/projects/{project_id}/environments/{id}', EnvironmentController::class . ':delete')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.environment.manage', 'project_id'));

        // ===== Dependency Registry =====
        $group->get('/projects/{project_id}/dependencies', DependencyController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'project_id'));
        $group->post('/projects/{project_id}/dependencies', DependencyController::class . ':add')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.dependency.manage', 'project_id'));
        $group->delete('/projects/{project_id}/dependencies/{id}', DependencyController::class . ':delete')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.dependency.manage', 'project_id'));
        $group->get('/dependencies/graph', DependencyController::class . ':graph')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view'));

        // ===== Release Registry =====
        $group->get('/projects/{project_id}/releases', ReleaseController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'project_id'));
        $group->post('/projects/{project_id}/releases', ReleaseController::class . ':create')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.release.manage', 'project_id'));
        $group->patch('/releases/{id}/transition', ReleaseController::class . ':transition')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.release.manage', 'id'));

        // ===== Project Template (project.template.manage — ADMIN) =====
        $group->get('/project-templates', ProjectTemplateController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view'));
        $group->get('/project-templates/{id}', ProjectTemplateController::class . ':show')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'id'));
        $group->post('/project-templates', ProjectTemplateController::class . ':create')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.template.manage'));
        $group->put('/project-templates/{id}', ProjectTemplateController::class . ':update')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.template.manage', 'id'));
        $group->put('/project-templates/{id}/set-default', ProjectTemplateController::class . ':setDefault')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.template.manage', 'id'));

        // ===== Workspace Default Settings =====
        $group->get('/workspaces/{id}/default-settings', WorkspaceDefaultSettingsController::class . ':show')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.view', 'id'));
        $group->put('/workspaces/{id}/default-settings', WorkspaceDefaultSettingsController::class . ':update')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.settings.manage', 'id'));

        // ===== Global Provider Registries (read สำหรับทุกคน / write: platform admin) =====
        $group->get('/ai-providers', AiProviderController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'ai_consumer.view'));
        $group->post('/ai-providers', AiProviderController::class . ':create')
            ->add(new RequiresPermissionMiddleware($resolver, 'ai_consumer.view'));
        $group->put('/ai-providers/{id}/status', AiProviderController::class . ':updateStatus')
            ->add(new RequiresPermissionMiddleware($resolver, 'ai_consumer.view'));
        $group->get('/git-providers', GitProviderController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view'));
        $group->post('/git-providers', GitProviderController::class . ':create')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view'));
        $group->put('/git-providers/{id}/status', GitProviderController::class . ':updateStatus')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view'));

        // ===== AI Consumer: ตอนนี้บังคับ provider_id ตอน create (R6 — PROVIDER_REQUIRED) =====

        // ===== Invitation (admin — is_platform_admin เท่านั้น) =====
        $group->post('/invitations', InvitationController::class . ':create')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.create'));

        // ===== M2: Dashboard API (read-only — API First, payload เดียวกับ Web UI ในอนาคต) =====
        $group->get('/dashboards/workspace', DashboardController::class . ':workspace')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.view'));
        $group->get('/dashboards/portfolio', DashboardController::class . ':portfolio')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.view'));
        $group->get('/dashboards/progress-summary', DashboardController::class . ':progressSummary')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.view'));
        $group->get('/dashboards/health-summary', DashboardController::class . ':healthSummary')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.view'));
        $group->get('/dashboards/statistics', DashboardController::class . ':statistics')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.view'));
        $group->get('/dashboards/recent-activities', DashboardController::class . ':recentActivities')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.view'));
        $group->get('/projects/{project_id}/dashboard', ProjectDashboardController::class . ':show')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'project_id'));
        $group->get('/projects/{project_id}/timeline', ProjectDashboardController::class . ':timeline')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'project_id'));
        $group->get('/projects/{project_id}/activities', ProjectDashboardController::class . ':activities')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'project_id'));

        // ===== M3: Revision Management / CTO Review Workflow / Commit Tracking =====
        $group->post('/revisions', RevisionController::class . ':submit')
            ->add(new RequiresPermissionMiddleware($resolver, 'revision.create'));
        $group->get('/projects/{project_id}/revisions', RevisionController::class . ':listByProject')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'project_id'));
        $group->get('/revisions/{id}', RevisionController::class . ':show')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'id'));
        $group->patch('/revisions/{id}/commit', RevisionController::class . ':commit')
            ->add(new RequiresPermissionMiddleware($resolver, 'revision.create', 'id'));
        $group->post('/revisions/{id}/review', RevisionReviewController::class . ':review')
            ->add(new RequiresPermissionMiddleware($resolver, 'revision.review', 'id'));

        // ===== M3: Deployment Tracking =====
        $group->get('/projects/{project_id}/deployments', DeploymentController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'project_id'));
        $group->post('/projects/{project_id}/deployments', DeploymentController::class . ':create')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.release.manage', 'project_id'));
        $group->patch('/deployments/{id}/transition', DeploymentController::class . ':transition')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.release.manage', 'id'));

        // ===== M5: API Platform =====
        $group->get('/audit-logs', AuditController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'audit_trail.view'));
        $group->get('/projects/{project_id}/api-tokens', ApiTokenController::class . ':listForProject')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'project_id'));
        $group->get('/platform/metrics', PlatformController::class . ':metrics')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.view'));

        // ===== M6: Knowledge Center =====
        $group->get('/knowledge-entries', KnowledgeController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'knowledge_article.view'));
        $group->get('/knowledge-entries/{id}', KnowledgeController::class . ':show')
            ->add(new RequiresPermissionMiddleware($resolver, 'knowledge_article.view', 'id'));
        $group->post('/knowledge-entries', KnowledgeController::class . ':create')
            ->add(new RequiresPermissionMiddleware($resolver, 'knowledge_article.create'));
        $group->patch('/knowledge-entries/{id}', KnowledgeController::class . ':update')
            ->add(new RequiresPermissionMiddleware($resolver, 'knowledge_article.update', 'id'));
        $group->delete('/knowledge-entries/{id}', KnowledgeController::class . ':delete')
            ->add(new RequiresPermissionMiddleware($resolver, 'knowledge_article.update', 'id'));
        $group->get('/knowledge/search', KnowledgeController::class . ':search')
            ->add(new RequiresPermissionMiddleware($resolver, 'knowledge_article.view'));
        $group->get('/knowledge-timeline', KnowledgeController::class . ':timeline')
            ->add(new RequiresPermissionMiddleware($resolver, 'knowledge_article.view'));
        $group->get('/architecture-decisions', KnowledgeController::class . ':architectureDecisions')
            ->add(new RequiresPermissionMiddleware($resolver, 'decision_register.view'));
        $group->get('/projects/{project_id}/knowledge', KnowledgeController::class . ':projectKnowledge')
            ->add(new RequiresPermissionMiddleware($resolver, 'project.view', 'project_id'));

        // ===== M7: Portfolio Analytics =====
        $group->get('/analytics/kpis', AnalyticsController::class . ':kpis')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.view'));
        $group->get('/analytics/milestones', AnalyticsController::class . ':milestones')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.view'));
        $group->get('/analytics/productivity', AnalyticsController::class . ':productivity')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.view'));
        $group->get('/analytics/health', AnalyticsController::class . ':health')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.view'));
        $group->get('/analytics/dependencies', AnalyticsController::class . ':dependencies')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.view'));
        $group->get('/analytics/workspace', AnalyticsController::class . ':workspace')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.view'));
        $group->get('/analytics/report', AnalyticsController::class . ':report')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.view'));
        $group->get('/analytics/portfolio', AnalyticsController::class . ':portfolio')
            ->add(new RequiresPermissionMiddleware($resolver, 'workspace.view'));

        // ===== M8: Automation Center =====
        $group->get('/automation/jobs', AutomationController::class . ':index')
            ->add(new RequiresPermissionMiddleware($resolver, 'automation.view'));
        $group->post('/automation/jobs', AutomationController::class . ':enqueue')
            ->add(new RequiresPermissionMiddleware($resolver, 'automation.manage'));
        $group->post('/automation/jobs/{id}/retry', AutomationController::class . ':retry')
            ->add(new RequiresPermissionMiddleware($resolver, 'automation.manage', 'id'));
        $group->post('/automation/run', AutomationController::class . ':run')
            ->add(new RequiresPermissionMiddleware($resolver, 'automation.manage'));
        $group->post('/automation/ai-dev-auto', AutomationController::class . ':aiDevAuto')
            ->add(new RequiresPermissionMiddleware($resolver, 'automation.manage'));

    })        ->add(AuditLoggingMiddleware::class)
        ->add(new WorkspaceContextMiddleware($container))
        // 🆕 M5: Rate Limiting (รันหลัง AuthToken — รู้จัก identity แล้ว; ใช้ env RATE_LIMIT_PER_MINUTE)
        ->add(RateLimitMiddleware::fromEnv())
        // 🆕 M5: API Scope Management — legacy token (ไม่มี scopes) ผ่านเหมือนเดิม (backward compatible)
        ->add(ApiScopeMiddleware::class)
        // 🆕 Phase 3: AiAccessControlMiddleware ต้องอยู่ "ระหว่าง" AuthToken กับ WorkspaceContext
        // (รันที่ 2 ในลำดับ execution) เพื่อบล็อก AI token ที่ผิด method/path ให้เร็วที่สุด
        // ก่อนถึง WorkspaceContext/Controller เลย -- ใช้ lazy resolve ผ่านชื่อคลาส (เรียนรู้
        // จาก bug เดิมเรื่อง eager resolution ของ AuditLoggingMiddleware ตอน Phase 0)
        ->add(AiAccessControlMiddleware::class)
        // Phase 4: enforce project isolation for project-scoped tokens (runs 2nd, after AuthToken)
        ->add(ProjectScopeMiddleware::class)
        ->add(new AuthTokenMiddleware($container->get(PDO::class), $container));

    // ===== M9 UAT Runtime Fix: Web routes (browser — 302) แยกจาก API routes (JSON) =====
    $app->get('/', LineLoginController::class . ':root');
    $app->get('/auth/line', LineLoginController::class . ':redirect');
    $app->get('/auth/line/callback', LineLoginController::class . ':callback');
    $app->get('/auth/error', LineLoginController::class . ':error');
    $app->post('/auth/logout', LineLoginController::class . ':logout');
    $app->get('/claim/{token}', ClaimController::class . ':start');

    // ===== API auth (JSON — สำหรับ API clients) =====
    $app->get('/api/v1/auth/line', LineLoginController::class . ':apiRedirect');
    $app->get('/api/v1/auth/line/callback', LineLoginController::class . ':apiCallback');
};
