<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
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
    ) {
    }

    /**
     * GET /api/v1/projects
     * Permission: project.view (เช็คผ่าน RequiresPermissionMiddleware ที่ผูกกับ route นี้)
     *
     * Projects UI Revision X ต้องแสดง Workspace/Dev Mode/Progress/Health/Current
     * Milestone ในตาราง — ข้อมูลเหล่านี้มีอยู่แล้วใน Project entity แค่ไม่เคยถูก map ออก
     */
    public function index(Request $request, Response $response): Response
    {
        $projects = $this->projectRepo->listByWorkspace();

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
     * Edit modal ของ Projects UI Revision X รองรับเฉพาะ field ที่มี Repository Method
     * อยู่แล้ว: progress/health (updateProgress) และ workspace (updateWorkspace) —
     * name/code/development_mode ไม่มี Repository Method รองรับการแก้ไขใน Design
     * ที่อนุมัติ จึงยังไม่เปิดให้แก้ (ฟอร์มฝั่ง UI ก็ปิด field เหล่านี้ตอน Edit เช่นกัน)
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
            'workspaceId' => $project->workspaceId,
        ];

        if (array_key_exists('progress', $body) || array_key_exists('health', $body)) {
            $progress = isset($body['progress']) && $body['progress'] !== ''
                ? (int) $body['progress']
                : $project->progressPercent;
            $health = !empty($body['health']) ? (string) $body['health'] : $project->health;

            if ($progress < 0 || $progress > 100) {
                return ApiResponse::error($response, 'VALIDATION_ERROR', 'progress ต้องอยู่ระหว่าง 0-100', [], 422);
            }
            if (!in_array($health, ['green', 'yellow', 'red'], true)) {
                return ApiResponse::error($response, 'VALIDATION_ERROR', 'health ไม่ถูกต้อง', [], 422);
            }

            $this->projectRepo->updateProgress($projectId, $progress, $health);
        }

        if (!empty($body['workspaceId'])) {
            $this->projectRepo->updateWorkspace($projectId, (int) $body['workspaceId']);
        }

        $updated = $this->projectRepo->findById($projectId);

        $auditContext = $request->getAttribute('audit_context');
        $auditContext?->record(
            entityType: 'project',
            entityId: $projectId,
            beforeValue: $beforeValue,
            afterValue: [
                'progress' => $updated->progressPercent,
                'health' => $updated->health,
                'workspaceId' => $updated->workspaceId,
            ]
        );

        return ApiResponse::success($response, [
            'id' => $updated->id,
            'code' => $updated->code,
            'name' => $updated->name,
            'status' => $updated->status,
            'workspaceId' => $updated->workspaceId,
            'development_mode' => $updated->developmentMode,
            'progress' => $updated->progressPercent,
            'health' => $updated->health,
            'current_milestone' => $updated->currentMilestoneId,
        ]);
    }
}
