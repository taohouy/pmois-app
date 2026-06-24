<?php

declare(strict_types=1);

use App\Application\Http\Controllers\AiConsumerController;
use App\Application\Http\Controllers\AiContextController;
use App\Application\Http\Controllers\ApiTokenController;
use App\Application\Http\Controllers\AttachmentController;
use App\Application\Http\Controllers\DecisionRegisterController;
use App\Application\Http\Controllers\GovernanceAdoptionController;
use App\Application\Http\Controllers\GovernanceRecordController;
use App\Application\Http\Controllers\GovernanceVersionController;
use App\Application\Http\Controllers\KnowledgeArticleController;
use App\Application\Http\Controllers\KnowledgeLinkController;
use App\Application\Http\Controllers\ProjectController;
use App\Application\Http\Controllers\ProjectMemberController;
use App\Application\Http\Controllers\RfcController;
use App\Application\Http\Controllers\WorkspaceController;
use App\Application\Http\Controllers\WorkspaceMemberController;
use App\Application\Http\Controllers\WorkspaceModuleSettingController;
use App\Application\Middleware\AiAccessControlMiddleware;
use App\Application\Middleware\AuditLoggingMiddleware;
use App\Application\Middleware\AuthTokenMiddleware;
use App\Application\Middleware\RequiresPermissionMiddleware;
use App\Application\Middleware\WorkspaceContextMiddleware;
use App\Domain\Identity\PermissionResolver;
use Psr\Container\ContainerInterface;
use Slim\App;

return function (App $app, ContainerInterface $container): void {

    $resolver = $container->get(PermissionResolver::class);

    $app->get('/api/v1/health', function ($request, $response) {
        $response->getBody()->write(json_encode(['status' => 'ok']));
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

        // ===== Phase 1: Governance Record =====
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

    })
        ->add(AuditLoggingMiddleware::class)
        ->add(WorkspaceContextMiddleware::class)
        // 🆕 Phase 3: AiAccessControlMiddleware ต้องอยู่ "ระหว่าง" AuthToken กับ WorkspaceContext
        // (รันที่ 2 ในลำดับ execution) เพื่อบล็อก AI token ที่ผิด method/path ให้เร็วที่สุด
        // ก่อนถึง WorkspaceContext/Controller เลย -- ใช้ lazy resolve ผ่านชื่อคลาส (เรียนรู้
        // จาก bug เดิมเรื่อง eager resolution ของ AuditLoggingMiddleware ตอน Phase 0)
        ->add(AiAccessControlMiddleware::class)
        ->add(new AuthTokenMiddleware($container->get(PDO::class), $container));
};
