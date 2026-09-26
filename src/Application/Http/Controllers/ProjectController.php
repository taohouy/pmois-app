<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Identity\PermissionResolver;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Workspace\WorkspaceRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ProjectController
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepo,
        private readonly \App\Domain\Project\ProjectCreationPipeline $creationPipeline,
        private readonly WorkspaceRepositoryInterface $workspaceRepo,
        private readonly PermissionResolver $permissionResolver,
    ) {
    }

    /**
     * GET /api/v1/projects[?workspace_id=]
     * Permission: project.view (เช็คผ่าน RequiresPermissionMiddleware ที่ผูกกับ route นี้ —
     * เช็คกับ workspace ของ session เท่านั้น; ถ้ามี ?workspace_id= ระบุ workspace อื่น ต้อง
     * เช็คซ้ำกับ workspace เป้าหมายจริงตรงนี้เสมอ เหมือน pattern เดียวกับ
     * WorkspaceController::update())
     *
     * รองรับ query param ?workspace_id= เพิ่มเติม (ไม่บังคับ ไม่มีผลย้อนหลังกับ consumer เดิม)
     * เพื่อให้ Workspace Tabs ของหน้า Projects ดูโครงการของ workspace อื่นที่ผู้ใช้เป็นสมาชิก
     * ด้วยได้จริง (ไม่ใช่แค่ workspace เดียวที่ผูกกับ session) — ไม่ระบุ = พฤติกรรมเดิมทุกประการ
     *
     * Projects UI Revision X ต้องแสดง Workspace/Dev Mode/Progress/Health/Current
     * Milestone ในตาราง — ข้อมูลเหล่านี้มีอยู่แล้วใน Project entity แค่ไม่เคยถูก map ออก
     */
    public function index(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $workspaceIdOverride = null;

        if (!empty($queryParams['workspace_id'])) {
            $workspaceIdOverride = (int) $queryParams['workspace_id'];
            $userId = (int) $request->getAttribute('user_id');

            if (!$this->permissionResolver->can($userId, $workspaceIdOverride, null, 'project.view')) {
                return ApiResponse::error($response, 'FORBIDDEN', 'ไม่มีสิทธิ์ดูโครงการในพื้นที่ทำงานนี้', [], 403);
            }
        }

        $projects = $this->projectRepo->listByWorkspace($workspaceIdOverride);

        $workspaceCodeById = [];
        foreach ($this->workspaceRepo->listAll() as $w) {
            $workspaceCodeById[$w->id] = $w->code;
        }

        $data = array_map(static fn ($p) => [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'status' => $p->status,
            'workspaceId' => $p->workspaceId,
            'workspaceCode' => $workspaceCodeById[$p->workspaceId] ?? null,
            'development_mode' => $p->developmentMode,
            'progress' => $p->progressPercent,
            'health' => $p->health,
            'current_milestone' => $p->currentMilestoneId,
        ], $projects);

        return ApiResponse::success($response, $data, [
            'pagination' => ['page' => 1, 'per_page' => count($data), 'total' => count($data)],
        ]);
    }

    /**
     * POST /api/v1/projects
     * Permission: project.create
     *
     * R6: creation ผ่าน ProjectCreationPipeline — template + workspace defaults
     * (CEO กรอกข้อมูลให้น้อยที่สุด: name, code + cto/dev/mode ที่ pre-fill จาก defaults)
     *
     * หมายเหตุ (Consolidated Stabilization — ตัดสินใจไม่ทำ ไม่ใช่ทำไม่สำเร็จ): เคยลองให้
     * สร้างเข้า workspace อื่นที่ไม่ใช่ workspace ของ session ได้ (ตาม body.workspaceId) เพื่อ
     * รองรับ Workspace Tabs แต่พบว่า ProjectCreationPipeline เรียก repository อื่นอีกหลายตัว
     * ต่อ (ProjectMemberRepository, MilestoneRepository, ProjectTechStackRepository,
     * WorkspaceModuleSettingRepository, ApiTokenRepository, Governance auto-bind, AI
     * assignment ฯลฯ) ซึ่งทุกตัวถูก inject 'current_workspace_id' ของ session ตายตัวผ่าน DI
     * container เหมือนกันหมด — การให้สร้างข้าม workspace ได้จริงต้องแก้ scoping ของทุก
     * repository เหล่านี้ ซึ่งเป็นการเปลี่ยน architecture ใหญ่เกินขอบเขตของ stabilization
     * revision นี้ (และเสี่ยงต่อ Workspace Isolation ที่ frozen ไว้) จึงคงพฤติกรรมเดิม: สร้าง
     * เข้า workspace ของ session เท่านั้นเสมอ ไม่ว่า body จะส่ง workspaceId อะไรมาก็ตาม —
     * รายงานเป็น known limitation แทนการฝืนทำ (ดู Handoff Note)
     */
    public function create(Request $request, Response $response): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $workspaceId = (int) $request->getAttribute('workspace_id');
        $body = (array) $request->getParsedBody();

        if (empty($body['code']) || empty($body['name'])) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', 'ต้องระบุ code และ name', [], 422);
        }

        try {
            $result = $this->creationPipeline->create($body, $userId, $workspaceId);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($response, 'VALIDATION_ERROR', $e->getMessage(), [], 422);
        }

        $project = $result['project'];

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'project',
            entityId: $project->id,
            afterValue: ['code' => $project->code, 'name' => $project->name],
            action: 'project_created'
        );

        $data = [
            'id' => $project->id,
            'code' => $project->code,
            'name' => $project->name,
            'status' => $project->status,
            'workspaceId' => $project->workspaceId,
            'development_mode' => $project->developmentMode,
            'source_template_id' => $project->sourceTemplateId,
            'profile_completeness_percent' => $result['profile_completeness_percent'],
            'applied' => $result['applied'],
        ];
        // raw token คืนครั้งเดียวตอนสร้าง (ไม่เก็บ clear text ในระบบ)
        if (!empty($result['project_token'])) {
            $data['project_token'] = $result['project_token'];
        }

        return ApiResponse::success($response, $data, [], 201);
    }

    /**
     * PUT /api/v1/projects/{id}/close
     * Permission: project.close (แยกจาก project.update ตาม Permission Code List v0.1)
     *
     * ตัวอย่างการใช้ audit_action override ตาม CTO Decision:
     * แทนที่จะ log เป็น "update" เฉยๆ ใช้ "close_project" เพื่อให้ audit log อ่านง่ายขึ้น
     */
    public function close(Request $request, Response $response, array $args): Response
    {
        $projectId = (int) $args['id'];
        $project = $this->projectRepo->findById($projectId);

        if ($project === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ project', [], 404);
        }

        $this->projectRepo->updateStatus($projectId, 'closed');

        // ระบุ action override ตรงๆ ผ่าน AuditContext::record() ตาม CTO Decision
        // (Phase 0 Spec หมวด 7, ตัวอย่าง close_project) แทนการพึ่ง default mapping ('update')
        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'project',
            entityId: $projectId,
            afterValue: ['status' => 'closed'],
            beforeValue: ['status' => $project->status],
            action: 'close_project'
        );

        return ApiResponse::success($response, ['id' => $projectId, 'status' => 'closed']);
    }

    /**
     * PUT /api/v1/projects/{id}
     * Permission: project.update
     *
     * Edit modal ของ Projects UI รองรับเฉพาะ field ที่มี Repository Method อยู่แล้ว:
     * progress/health (updateProgress) — name/code/development_mode ไม่มี Repository
     * Method รองรับการแก้ไขใน Design ที่อนุมัติ จึงยังไม่เปิดให้แก้ (ฟอร์มฝั่ง UI ก็ปิด
     * field เหล่านี้ตอน Edit เช่นกัน)
     *
     * หมายเหตุ (M3 Completion Gate): เดิม endpoint นี้เคยรับ workspaceId มาเรียก
     * updateWorkspace() ตรงๆ ด้วย (สำหรับปุ่ม "ย้ายพื้นที่ทำงาน") — เอาออกแล้ว เพราะ
     * "ย้าย workspace" มี canonical flow ที่อนุมัติแล้วอยู่ก่อนแล้วคือ
     * PATCH /api/v1/projects/{id}/structure (action=move_workspace,
     * ProjectStructureService::moveWorkspace(), บันทึกลง project_structure_history
     * โดยเฉพาะ) — การมี 2 endpoint ทำหน้าที่เดียวกันจะทำให้ audit trail กระจัดกระจาย
     * และเสี่ยง permission check ไม่ตรงกัน จึงให้ Edit endpoint นี้ทำหน้าที่แก้ progress/
     * health เท่านั้น ตรงตาม doc-comment เดิม
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $projectId = (int) $args['id'];
        $project = $this->projectRepo->findById($projectId);

        if ($project === null) {
            return ApiResponse::error($response, 'NOT_FOUND', 'ไม่พบ project', [], 404);
        }

        $body = (array) $request->getParsedBody();
        $beforeValue = [
            'progress' => $project->progressPercent,
            'health' => $project->health,
        ];

        $newProgress = $project->progressPercent;
        $newHealth = $project->health;

        if (array_key_exists('progress', $body) || array_key_exists('health', $body)) {
            $newProgress = isset($body['progress']) && $body['progress'] !== ''
                ? (int) $body['progress']
                : $project->progressPercent;
            $newHealth = !empty($body['health']) ? (string) $body['health'] : $project->health;

            if ($newProgress < 0 || $newProgress > 100) {
                return ApiResponse::error($response, 'VALIDATION_ERROR', 'progress ต้องอยู่ระหว่าง 0-100', [], 422);
            }
            if (!in_array($newHealth, ['green', 'yellow', 'red'], true)) {
                return ApiResponse::error($response, 'VALIDATION_ERROR', 'health ไม่ถูกต้อง', [], 422);
            }

            $this->projectRepo->updateProgress($projectId, $newProgress, $newHealth);
        }

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'project',
            entityId: $projectId,
            beforeValue: $beforeValue,
            afterValue: [
                'progress' => $newProgress,
                'health' => $newHealth,
            ]
        );

        return ApiResponse::success($response, [
            'id' => $project->id,
            'code' => $project->code,
            'name' => $project->name,
            'status' => $project->status,
            'workspaceId' => $project->workspaceId,
            'development_mode' => $project->developmentMode,
            'progress' => $newProgress,
            'health' => $newHealth,
            'current_milestone' => $project->currentMilestoneId,
        ]);
    }
}
