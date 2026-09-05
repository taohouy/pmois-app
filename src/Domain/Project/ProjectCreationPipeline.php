<?php

declare(strict_types=1);

namespace App\Domain\Project;

use App\Domain\Auth\ApiTokenRepositoryInterface;
use App\Domain\Governance\GovernanceAutoBindService;
use App\Domain\Registry\AiConsumerCodeResolverInterface;
use App\Domain\Registry\ProjectTemplate;
use App\Domain\Registry\ProjectTemplateRepositoryInterface;
use App\Domain\Registry\WorkspaceDefaultSettingsService;
use App\Domain\Workspace\WorkspaceModuleSettingRepositoryInterface;
use PDO;

/**
 * ProjectCreationPipeline (M0-Design/Revision6/R6-05 §1.3)
 *
 * หลักการ: CEO กรอกข้อมูลให้น้อยที่สุด (7 fields) — ที่เหลือ resolve จาก
 * Workspace Defaults + Template แล้ว apply ตามลำดับ pipeline ที่ Design Freeze กำหนด
 *
 * M1 R2 (CTO Review §2): pipeline ทั้งหมดเป็น ATOMIC — ถ้าขั้นตอนใด fail
 * ROLLBACK ทั้ง project creation ห้ามเหลือ partial project / orphan rows
 * (ใช้ transaction; เมื่อ caller มี transaction อยู่แล้วใช้ SAVEPOINT แทน)
 */
final class ProjectCreationPipeline
{
    private const SAVEPOINT = 'pcp_create';

    /** @var array<string, int>|null */
    private ?array $roleCache = null;

    public function __construct(
        private readonly PDO $db,
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly ProjectMemberRepositoryInterface $memberRepository,
        private readonly ProjectMemberAssignmentRepositoryInterface $teamAssignmentRepository,
        private readonly ProjectAiAssignmentRepositoryInterface $aiAssignmentRepository,
        private readonly MilestoneRepositoryInterface $milestoneRepository,
        private readonly ProjectTechStackRepositoryInterface $techStackRepository,
        private readonly WorkspaceModuleSettingRepositoryInterface $moduleSettingRepository,
        private readonly ApiTokenRepositoryInterface $apiTokenRepository,
        private readonly GovernanceAutoBindService $governanceAutoBindService,
        private readonly WorkspaceDefaultSettingsService $workspaceDefaults,
        private readonly ProjectTemplateRepositoryInterface $templateRepository,
        private readonly AiConsumerCodeResolverInterface $aiConsumerResolver,
        private readonly ProfileCompletenessCalculator $completenessCalculator,
    ) {
    }

    /**
     * @param array<string, mixed> $body — request body ของ POST /projects
     * @return array<string, mixed> creation result (project data + applied defaults info)
     * @throws \InvalidArgumentException เมื่อ required fields หาย หรือ template ไม่ valid
     * @throws \Throwable ข้อผิดพลาดใดๆ ระหว่าง pipeline (มี rollback ก่อนส่งต่อเสมอ)
     */
    public function create(array $body, int $actorId, int $workspaceId): array
    {
        $errors = [];
        if (empty($body['name'])) {
            $errors[] = 'name is required';
        }
        if (empty($body['code'])) {
            $errors[] = 'code is required';
        }
        // CTO + Dev ต้อง resolve ได้ (request -> workspace default) — ไม่มีจริงจะสร้างไม่ได้
        $ctoUserId = $this->workspaceDefaults->resolveCtoUserId($workspaceId, isset($body['cto_user_id']) ? (int) $body['cto_user_id'] : null);
        $devUserId = $this->workspaceDefaults->resolveDevUserId($workspaceId, isset($body['dev_user_id']) ? (int) $body['dev_user_id'] : null);
        if ($ctoUserId === null) {
            $errors[] = 'cto_user_id is required (no workspace default configured)';
        }
        if ($devUserId === null) {
            $errors[] = 'dev_user_id is required (no workspace default configured)';
        }
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        $developmentMode = $this->workspaceDefaults->resolveDevelopmentMode($workspaceId, $body['development_mode'] ?? null);
        $template = $this->resolveTemplate($workspaceId, isset($body['template_id']) ? (int) $body['template_id'] : null);
        $payload = $template?->payload ?? [];

        // ===== ATOMIC: ครอบทุก write ของ creation =====
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        } else {
            // caller (เช่น test) เปิด transaction ค้างไว้ — ใช้ savepoint แทน
            $this->db->exec('SAVEPOINT ' . self::SAVEPOINT);
        }

        try {
            $result = $this->runPipeline($body, $actorId, $workspaceId, $developmentMode, $template, $payload, $ctoUserId, $devUserId);

            if ($ownsTransaction) {
                $this->db->commit();
            } else {
                $this->db->exec('RELEASE SAVEPOINT ' . self::SAVEPOINT);
            }
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                if ($ownsTransaction) {
                    $this->db->rollBack();
                } else {
                    $this->db->exec('ROLLBACK TO SAVEPOINT ' . self::SAVEPOINT);
                    $this->db->exec('RELEASE SAVEPOINT ' . self::SAVEPOINT);
                }
            }
            throw $e;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function runPipeline(
        array $body,
        int $actorId,
        int $workspaceId,
        string $developmentMode,
        ?ProjectTemplate $template,
        array $payload,
        int $ctoUserId,
        int $devUserId
    ): array {
        // Step 3: INSERT projects (projects.id = permanent Internal ID)
        $project = $this->projectRepository->create(
            code: (string) $body['code'],
            name: (string) $body['name'],
            description: $body['description'] ?? null,
            ownerUserId: $actorId,
            workspaceId: $workspaceId,
            parentProjectId: isset($body['parent_project_id']) && $body['parent_project_id'] !== null ? (int) $body['parent_project_id'] : null,
            developmentMode: $developmentMode,
            abbreviation: $body['abbreviation'] ?? null,
            startDate: $body['start_date'] ?? null,
            sourceTemplateId: $template?->id,
        );

        $applied = ['template' => $template?->code, 'workspace_defaults_used' => $this->usedWorkspaceDefaults($body)];

        // Step 4-5: team (CTO + Dev) — ledger + authorization projection
        $source = $template !== null ? 'project_template' : ($applied['workspace_defaults_used'] ? 'workspace_default' : 'direct');
        $ctoRoleId = $this->resolveRoleId('CTO');
        $devRoleId = $this->resolveRoleId('MEMBER');
        $this->teamAssignmentRepository->create($project->id, $ctoUserId, $ctoRoleId, $source, null, $actorId);
        $this->memberRepository->addMember($project->id, $ctoUserId, $ctoRoleId);
        $this->teamAssignmentRepository->create($project->id, $devUserId, $devRoleId, $source, null, $actorId);
        $this->memberRepository->addMember($project->id, $devUserId, $devRoleId);

        // Step 6: AI agents จาก template (ai_consumer_code resolve ผ่าน Registry เท่านั้น)
        foreach (($payload['ai_agents'] ?? []) as $agent) {
            $consumer = $this->aiConsumerResolver->resolveByCode((string) ($agent['ai_consumer_code'] ?? ''));
            if ($consumer === null) {
                continue; // validatePayload กันไว้แล้ว — defensive
            }
            $this->aiAssignmentRepository->create(
                $project->id,
                (int) $consumer['id'],
                $this->resolveRoleId((string) ($agent['role'] ?? 'MEMBER')),
                $agent['purpose'] ?? null,
                $actorId
            );
        }

        // Step 7: Governance binding
        $governanceVersionId = $payload['governance']['governance_version_id'] ?? null;
        $wds = $this->workspaceDefaults->get($workspaceId);
        $applied['governance_adoption_id'] = $this->governanceAutoBindService->bindDefaultGovernance(
            $project->id,
            $governanceVersionId !== null ? (int) $governanceVersionId : null,
            $wds?->defaultGovernanceVersionId
        );

        // Step 8: template milestones
        $today = new \DateTimeImmutable();
        foreach (($payload['milestones'] ?? []) as $m) {
            $plannedDate = null;
            if (isset($m['planned_offset_days']) && is_numeric($m['planned_offset_days'])) {
                $plannedDate = $today->modify('+' . (int) $m['planned_offset_days'] . ' days')->format('Y-m-d');
            }
            $this->milestoneRepository->create(new Milestone(
                id: 0,
                projectId: $project->id,
                workspaceId: $workspaceId,
                code: (string) $m['code'],
                title: (string) $m['title'],
                status: 'open',
                plannedDate: $plannedDate,
                closedBy: null,
                closedAt: null,
                createdBy: $actorId,
                createdAt: $today->format('Y-m-d H:i:s'),
                updatedAt: $today->format('Y-m-d H:i:s'),
            ));
        }

        // Step 9: tech stack เฉพาะเมื่อ template ระบุ (ปกติ CTO/Dev เติมภายหลัง)
        foreach (($payload['tech_stack'] ?? []) as $t) {
            $this->techStackRepository->create([
                'project_id' => $project->id,
                'workspace_id' => $workspaceId,
                'layer' => $t['layer'] ?? 'other',
                'name' => (string) $t['name'],
                'version' => $t['version'] ?? null,
                'notes' => $t['notes'] ?? null,
                'status' => 'active',
                'added_by' => $actorId,
            ]);
        }

        // Step 10: project API token (คืน raw token ครั้งเดียว — ไม่เก็บ clear text)
        $projectToken = null;
        if (($payload['auto_create_project_token'] ?? false) === true) {
            $token = $this->apiTokenRepository->create(
                $workspaceId,
                $actorId,
                sprintf('project-%s-bootstrap', $project->code),
                null,
                null,
                $project->id
            );
            $projectToken = $token['raw_token'] ?? null;
            $applied['api_token_id'] = $token['id'] ?? null;
        }

        // Step 11: default module configuration
        foreach (($payload['module_settings'] ?? []) as $moduleCode => $enabled) {
            $this->moduleSettingRepository->setEnabled($workspaceId, (string) $moduleCode, (bool) $enabled, $actorId);
        }

        // Step 12: completeness recompute + persist (ภายใน transaction เดียวกัน)
        $fresh = $this->projectRepository->findById($project->id);
        $completeness = 0;
        if ($fresh !== null) {
            $completeness = $this->completenessCalculator->calculate($fresh)['percent'];
            $stmt = $this->db->prepare(
                'UPDATE projects SET profile_completeness_percent = :percent WHERE id = :id'
            );
            $stmt->execute(['percent' => $completeness, 'id' => $project->id]);
        }

        return [
            'project' => $fresh ?? $project,
            'applied' => $applied,
            'profile_completeness_percent' => $completeness,
            'project_token' => $projectToken, // raw token — ส่งกลับครั้งเดียวเท่านั้น
        ];
    }

    private function resolveTemplate(int $workspaceId, ?int $templateId): ?ProjectTemplate
    {
        if ($templateId !== null) {
            $template = $this->templateRepository->findById($templateId);
            if ($template === null || $template->workspaceId !== $workspaceId || $template->status !== 'active') {
                throw new \InvalidArgumentException('TEMPLATE_NOT_FOUND');
            }
            return $template;
        }
        return $this->templateRepository->findDefaultForWorkspace($workspaceId);
    }

    private function usedWorkspaceDefaults(array $body): bool
    {
        return empty($body['cto_user_id']) || empty($body['dev_user_id']) || empty($body['development_mode']);
    }

    /**
     * Map canonical role code -> roles.id (roles เดิม: ADMIN/MEMBER/VIEWER/CTO/SENIOR_DEV/PMO_REVIEWER)
     * CEO/PMO/Dev/Viewer ใน brief map ตาม 12-Role-Permission-Matrix.md §1 — ไม่สร้าง role ใหม่
     */
    private function resolveRoleId(string $code): int
    {
        if ($this->roleCache === null) {
            $stmt = $this->db->query('SELECT id, code FROM roles');
            $this->roleCache = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $this->roleCache[(string) $row['code']] = (int) $row['id'];
            }
        }

        if (!isset($this->roleCache[$code])) {
            throw new \RuntimeException("role '{$code}' not seeded — check migration 0011");
        }

        return $this->roleCache[$code];
    }
}
