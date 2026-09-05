<?php

declare(strict_types=1);

namespace App\Domain\Project;

/**
 * TechStackService — Technology Stack Registry (CTO Requirement #6)
 * ไม่บังคับตอนสร้าง project — CTO/Dev เติมภายหลัง (permission: project.techstack.manage)
 */
final class TechStackService
{
    private const LAYERS = ['language', 'framework', 'database', 'runtime', 'frontend', 'infrastructure', 'tooling', 'other'];
    private const STATUSES = ['active', 'deprecated', 'planned'];

    public function __construct(
        private readonly ProjectTechStackRepositoryInterface $repository,
        private readonly int $workspaceId,
    ) {
    }

    /**
     * @return array<int, ProjectTechStack>
     */
    public function listByProject(int $projectId): array
    {
        return $this->repository->findByProjectId($projectId);
    }

    /**
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException VALIDATION_ERROR
     */
    public function add(int $projectId, array $data, int $addedBy): ProjectTechStack
    {
        $errors = [];
        if (empty($data['name'])) {
            $errors[] = 'name is required';
        }
        if (isset($data['layer']) && !in_array($data['layer'], self::LAYERS, true)) {
            $errors[] = 'layer must be one of: ' . implode(', ', self::LAYERS);
        }
        if (isset($data['status']) && !in_array($data['status'], self::STATUSES, true)) {
            $errors[] = 'status must be one of: ' . implode(', ', self::STATUSES);
        }
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        return $this->repository->create([
            'project_id' => $projectId,
            'workspace_id' => $this->workspaceId,
            'layer' => $data['layer'] ?? 'other',
            'name' => (string) $data['name'],
            'version' => $data['version'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'active',
            'added_by' => $addedBy,
        ]);
    }

    public function remove(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
