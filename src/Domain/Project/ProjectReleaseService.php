<?php

declare(strict_types=1);

namespace App\Domain\Project;

/**
 * ProjectReleaseService — Release Registry (CTO Requirement #9)
 *
 * State machine (M0-Design/Revision6/R6-09 §3):
 *   planned -> in_progress -> released -> (rolled_back | cancelled)
 *              planned/in_progress -> cancelled
 *   released ต้องตั้ง released_by / released_at เท่านั้น
 */
final class ProjectReleaseService
{
    private const TYPES = ['alpha', 'beta', 'rc', 'production', 'hotfix'];

    /** @var array<string, array<int, string>> */
    private const TRANSITIONS = [
        'planned' => ['in_progress', 'cancelled'],
        'in_progress' => ['released', 'cancelled'],
        'released' => ['rolled_back'],
        'rolled_back' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private readonly ProjectReleaseRepositoryInterface $repository,
        private readonly int $workspaceId,
    ) {
    }

    /**
     * @return array<int, ProjectRelease>
     */
    public function listByProject(int $projectId): array
    {
        return $this->repository->findByProjectId($projectId);
    }

    /**
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException VALIDATION_ERROR
     */
    public function create(array $data, int $createdBy): ProjectRelease
    {
        $errors = [];
        if (empty($data['project_id'])) {
            $errors[] = 'project_id is required';
        }
        if (empty($data['version_label'])) {
            $errors[] = 'version_label is required';
        }
        if (isset($data['release_type']) && !in_array($data['release_type'], self::TYPES, true)) {
            $errors[] = 'release_type must be one of: ' . implode(', ', self::TYPES);
        }
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        if ($this->repository->findByProjectAndVersion((int) $data['project_id'], (string) $data['version_label']) !== null) {
            throw new \InvalidArgumentException('version_label already exists for this project');
        }

        return $this->repository->create([
            'project_id' => (int) $data['project_id'],
            'workspace_id' => $this->workspaceId,
            'release_type' => $data['release_type'] ?? 'alpha',
            'version_label' => (string) $data['version_label'],
            'status' => 'planned',
            'repository_id' => $data['repository_id'] ?? null,
            'environment_id' => $data['environment_id'] ?? null,
            'milestone_id' => $data['milestone_id'] ?? null,
            'release_notes' => $data['release_notes'] ?? null,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Transition ตาม state machine — ผิด transition = VALIDATION_ERROR
     *
     * @throws \InvalidArgumentException
     */
    public function transition(int $releaseId, string $toStatus, int $actorId): ProjectRelease
    {
        $release = $this->repository->findById($releaseId);
        if ($release === null) {
            throw new \InvalidArgumentException('release not found');
        }

        $allowed = self::TRANSITIONS[$release->status] ?? [];
        if (!in_array($toStatus, $allowed, true)) {
            throw new \InvalidArgumentException(
                "invalid transition {$release->status} -> {$toStatus} (allowed: " . implode(', ', $allowed) . ')'
            );
        }

        $data = ['status' => $toStatus];
        if ($toStatus === 'released') {
            $data['released_by'] = $actorId;
            $data['released_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        }

        $this->repository->update($releaseId, $data);

        $updated = $this->repository->findById($releaseId);
        if ($updated === null) {
            throw new \RuntimeException('release update succeeded but re-read failed');
        }

        return $updated;
    }
}
