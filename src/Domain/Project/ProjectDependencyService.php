<?php

declare(strict_types=1);

namespace App\Domain\Project;

/**
 * ProjectDependencyService — Dependency Registry (CTO Requirement #8)
 *
 * Rules (M0-Design/Revision6/R6-08 §2):
 *  - depends_on ต้อง acyclic (ancestor walk เหมือน CIRCULAR_HIERARCHY)
 *  - blocked_by เป็น informational — cycle ได้ แต่ graph จะรายงาน warning
 *  - ห้าม self-edge, ทั้งสอง project ต้องอยู่ workspace เดียวกัน
 */
final class ProjectDependencyService
{
    private const TYPES = ['depends_on', 'blocked_by'];

    public function __construct(
        private readonly ProjectDependencyRepositoryInterface $repository,
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly int $workspaceId,
    ) {
    }

    /**
     * @return array<int, ProjectDependency>
     */
    public function listByProject(int $projectId): array
    {
        return $this->repository->findByProjectId($projectId);
    }

    /**
     * Graph payload สำหรับ Portfolio Dashboard (read-only, ทุก role ที่มี project.view)
     *
     * @return array<string, mixed>
     */
    public function graph(): array
    {
        $edges = $this->repository->findByWorkspaceId();
        $projects = $this->projectRepository->listByWorkspace();

        $nodes = [];
        $nodeIds = [];
        foreach ($edges as $edge) {
            $nodeIds[$edge->projectId] = true;
            $nodeIds[$edge->relatedProjectId] = true;
        }

        foreach ($projects as $project) {
            if (isset($nodeIds[$project->id])) {
                $nodes[] = [
                    'id' => $project->id,
                    'code' => $project->code,
                    'name' => $project->name,
                    'status' => $project->status,
                    'health' => $project->health,
                    'progress_percent' => $project->progressPercent,
                    'development_mode' => $project->developmentMode,
                ];
            }
        }

        $edgeList = [];
        foreach ($edges as $edge) {
            $edgeList[] = [
                'from' => $edge->projectId,
                'to' => $edge->relatedProjectId,
                'type' => $edge->dependencyType,
            ];
        }

        return [
            'nodes' => $nodes,
            'edges' => $edgeList,
            'warnings' => $this->detectBlockedByCycles($edges),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @throws \DomainException DEPENDENCY_CIRCULAR / DEPENDENCY_SELF / DEPENDENCY_WORKSPACE_MISMATCH
     * @throws \InvalidArgumentException VALIDATION_ERROR
     */
    public function add(array $data, int $createdBy): ProjectDependency
    {
        $projectId = isset($data['project_id']) ? (int) $data['project_id'] : 0;
        $relatedProjectId = isset($data['related_project_id']) ? (int) $data['related_project_id'] : 0;
        $type = $data['dependency_type'] ?? 'depends_on';

        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('dependency_type must be one of: ' . implode(', ', self::TYPES));
        }
        if ($projectId === $relatedProjectId) {
            throw new \DomainException('DEPENDENCY_SELF');
        }

        $project = $this->projectRepository->findById($projectId);
        $related = $this->projectRepository->findById($relatedProjectId);
        if ($project === null || $related === null || $project->workspaceId !== $related->workspaceId) {
            throw new \DomainException('DEPENDENCY_WORKSPACE_MISMATCH');
        }

        if ($type === 'depends_on') {
            $this->assertDependsOnAcyclic($projectId, $relatedProjectId);
        }

        return $this->repository->create([
            'workspace_id' => $this->workspaceId,
            'project_id' => $projectId,
            'related_project_id' => $relatedProjectId,
            'dependency_type' => $type,
            'note' => $data['note'] ?? null,
            'created_by' => $createdBy,
        ]);
    }

    public function remove(int $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * Ancestor walk: A depends_on B ต้องไม่ทำให้เกิดวง — ถ้า B (หรือ chain ที่ B depends_on อยู่)
     * มี A เป็นต้นทางอยู่แล้วจะเกิด cycle
     * ใช้ directed edges (findEdgesFrom) เพราะทิศทางสำคัญกับการหา cycle
     */
    private function assertDependsOnAcyclic(int $projectId, int $newDependencyId): void
    {
        $visited = [];
        $current = $newDependencyId;

        while ($current !== null && !isset($visited[$current])) {
            if ($current === $projectId) {
                throw new \DomainException('DEPENDENCY_CIRCULAR');
            }
            $visited[$current] = true;

            $next = null;
            foreach ($this->repository->findEdgesFrom($current, 'depends_on') as $edge) {
                $next = $edge->relatedProjectId;
                break;
            }
            $current = $next;
        }
    }

    /**
     * @param array<int, ProjectDependency> $edges
     * @return array<int, array{type: string, projects: array<int, int>}>
     */
    private function detectBlockedByCycles(array $edges): array
    {
        // blocked_by edges เป็น informational — ตรวจง่าย ๆ: หาคู่ A blocked_by B และ B blocked_by A
        $warnings = [];
        $seen = [];
        foreach ($edges as $edge) {
            if ($edge->dependencyType !== 'blocked_by') {
                continue;
            }
            $key = $edge->relatedProjectId . ':' . $edge->projectId;
            if (isset($seen[$key]) && !isset($seen[$edge->projectId . ':' . $edge->relatedProjectId . '-reported'])) {
                $warnings[] = ['type' => 'blocked_by_cycle', 'projects' => [$edge->projectId, $edge->relatedProjectId]];
                $seen[$edge->projectId . ':' . $edge->relatedProjectId . '-reported'] = true;
            }
            $seen[$edge->projectId . ':' . $edge->relatedProjectId] = true;
        }
        return $warnings;
    }
}
