<?php

declare(strict_types=1);

namespace App\Domain\Project;

final class ProjectStructureService
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly ProjectStructureHistoryRepositoryInterface $structureHistoryRepository,
    ) {
    }

    /**
     * Move project to a different workspace
     */
    public function moveWorkspace(int $projectId, int $newWorkspaceId, int $actorId, ?string $reason): void
    {
        // แก้ไข (M3 Completion Gate — Round 6): findById()/updateWorkspace() แบบไม่ระบุ
        // override จะ scope กับ workspace ของ session เท่านั้น — ถ้า project ที่จะย้ายอยู่
        // Workspace Tab อื่น (ไม่ใช่ workspace หลักของ session) จะเจอ "Project not found"
        // ทั้งที่ project มีอยู่จริงและผู้ใช้มีสิทธิ์ (RequiresPermissionMiddleware เช็ค
        // project.structure.update ผ่าน project_id ให้แล้วก่อนถึงชั้นนี้เสมอ) ต้องหา
        // workspace จริงของ project ก่อนผ่าน findWorkspaceIdForProject() แล้วส่งเป็น
        // override ให้ findById()/updateWorkspace() ทั้งคู่
        $realWorkspaceId = $this->projectRepository->findWorkspaceIdForProject($projectId);
        if ($realWorkspaceId === null) {
            throw new \InvalidArgumentException("Project not found");
        }

        $project = $this->projectRepository->findById($projectId, $realWorkspaceId);
        if ($project === null) {
            throw new \InvalidArgumentException("Project not found");
        }

        if ($project->workspaceId === $newWorkspaceId) {
            throw new \InvalidArgumentException("Project is already in the target workspace");
        }

        $oldWorkspaceId = $project->workspaceId;

        // Update project workspace
        if (!$this->projectRepository->updateWorkspace($projectId, $newWorkspaceId, $realWorkspaceId)) {
            throw new \RuntimeException("ย้าย workspace ไม่สำเร็จ — กรุณาลองใหม่");
        }

        // Record structure history
        $this->structureHistoryRepository->create(new \App\Domain\Project\ProjectStructureHistory(
            id: 0,
            projectId: $projectId,
            changeType: 'move_workspace',
            fromWorkspaceId: $oldWorkspaceId,
            toWorkspaceId: $newWorkspaceId,
            fromParentProjectId: null,
            toParentProjectId: null,
            reason: $reason,
            changedBy: $actorId,
            createdAt: (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ));
    }

    /**
     * Change parent project
     */
    public function changeParent(int $projectId, ?int $newParentId, int $actorId, ?string $reason): void
    {
        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            throw new \InvalidArgumentException("Project not found");
        }

        if ($newParentId !== null) {
            // Validate no circular reference
            if ($newParentId === $projectId) {
                throw new \InvalidArgumentException("Project cannot be its own parent");
            }
            // Check for circular reference by walking up the parent chain
            $current = $newParentId;
            while ($current !== null) {
                if ($current === $projectId) {
                    throw new \InvalidArgumentException("Circular reference detected");
                }
                $parent = $this->projectRepository->findById($current);
                $current = $parent?->parentProjectId;
            }
        }

        $oldParentId = $project->parentProjectId;

        $this->projectRepository->updateParent($projectId, $newParentId);

        $this->structureHistoryRepository->create(new \App\Domain\Project\ProjectStructureHistory(
            id: 0,
            projectId: $projectId,
            changeType: 'change_parent',
            fromWorkspaceId: null,
            toWorkspaceId: null,
            fromParentProjectId: $oldParentId,
            toParentProjectId: $newParentId,
            reason: $reason,
            changedBy: $actorId,
            createdAt: (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ));
    }

    /**
     * Promote project to workspace root (remove parent)
     */
    public function promoteToRoot(int $projectId, int $actorId, ?string $reason): void
    {
        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            throw new \InvalidArgumentException("Project not found");
        }

        if ($project->parentProjectId === null) {
            throw new \InvalidArgumentException("Project is already a root project");
        }

        $oldParentId = $project->parentProjectId;

        $this->projectRepository->updateParent($projectId, null);

        $this->structureHistoryRepository->create(new \App\Domain\Project\ProjectStructureHistory(
            id: 0,
            projectId: $projectId,
            changeType: 'promote_to_workspace',
            fromWorkspaceId: null,
            toWorkspaceId: null,
            fromParentProjectId: $project->parentProjectId,
            toParentProjectId: null,
            reason: $reason,
            changedBy: $actorId,
            createdAt: (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ));
    }

    /**
     * Get structure history for a project
     */
    public function getStructureHistory(int $projectId): array
    {
        return $this->structureHistoryRepository->findByProjectId($projectId);
    }
}