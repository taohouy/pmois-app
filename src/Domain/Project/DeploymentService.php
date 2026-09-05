<?php

declare(strict_types=1);

namespace App\Domain\Project;

/**
 * DeploymentService — Deployment Tracking (M3)
 *
 * State machine: pending → in_progress → deployed → (rolled_back) / failed
 *                pending/in_progress → failed / cancelled? (cancelled ไม่มีใน enum — ใช้ failed)
 * deployed เท่านั้นที่ตั้ง deployed_by / deployed_at
 */
final class DeploymentService
{
    /** @var array<string, array<int, string>> */
    private const TRANSITIONS = [
        'pending' => ['in_progress', 'failed'],
        'in_progress' => ['deployed', 'failed'],
        'deployed' => ['rolled_back'],
        'failed' => [],
        'rolled_back' => [],
    ];

    public function __construct(
        private readonly ProjectDeploymentRepositoryInterface $repository,
        private readonly int $workspaceId,
    ) {
    }

    /**
     * @return array<int, ProjectDeployment>
     */
    public function listByProject(int $projectId): array
    {
        return $this->repository->findByProjectId($projectId);
    }

    /**
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException VALIDATION_ERROR
     */
    public function create(array $data, int $createdBy): ProjectDeployment
    {
        $errors = [];
        if (empty($data['project_id'])) {
            $errors[] = 'project_id is required';
        }
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        return $this->repository->create([
            'project_id' => (int) $data['project_id'],
            'workspace_id' => $this->workspaceId,
            'release_id' => $data['release_id'] ?? null,
            'environment_id' => $data['environment_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function transition(int $deploymentId, string $toStatus, int $actorId): ProjectDeployment
    {
        $deployment = $this->repository->findById($deploymentId);
        if ($deployment === null) {
            throw new \InvalidArgumentException('deployment not found');
        }

        $allowed = self::TRANSITIONS[$deployment->status] ?? [];
        if (!in_array($toStatus, $allowed, true)) {
            throw new \InvalidArgumentException(
                "invalid transition {$deployment->status} -> {$toStatus} (allowed: " . implode(', ', $allowed) . ')'
            );
        }

        $data = ['status' => $toStatus];
        if ($toStatus === 'deployed') {
            $data['deployed_by'] = $actorId;
            $data['deployed_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        }

        $this->repository->update($deploymentId, $data);

        $updated = $this->repository->findById($deploymentId);
        if ($updated === null) {
            throw new \RuntimeException('deployment update succeeded but re-read failed');
        }

        return $updated;
    }
}
