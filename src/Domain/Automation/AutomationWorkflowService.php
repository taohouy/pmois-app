<?php

declare(strict_types=1);

namespace App\Domain\Automation;

/**
 * AutomationWorkflowService — Automatic Workflow triggers (M8)
 *
 * Workflows (config-driven concepts):
 *   onRevisionCommitted      → queue timeline_update (Automatic Timeline Update)
 *   onMilestoneClosed        → queue project_update (Automatic Project Update)
 *   onDeploymentStatusChanged→ queue notification  (Telegram Automation)
 *
 * ทุก trigger เป็น best-effort — การ enqueue ล้มเหลวไม่กระทบธุรกรรมหลัก
 */
final class AutomationWorkflowService
{
    public const JOB_TYPES = ['timeline_update', 'project_update', 'gitlab_sync', 'ai_dev_auto', 'notification'];

    public function __construct(private readonly AutomationJobRepositoryInterface $jobRepository)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @throws \InvalidArgumentException job_type ไม่ถูกต้อง
     */
    public function enqueue(string $jobType, array $payload, ?int $workspaceId, ?int $projectId, ?int $createdBy = null, ?string $scheduledAt = null, int $maxAttempts = 3): AutomationJob
    {
        if (!in_array($jobType, self::JOB_TYPES, true)) {
            throw new \InvalidArgumentException('job_type must be one of: ' . implode(', ', self::JOB_TYPES));
        }

        return $this->jobRepository->create([
            'workspace_id' => $workspaceId,
            'project_id' => $projectId,
            'job_type' => $jobType,
            'payload' => $payload,
            'scheduled_at' => $scheduledAt,
            'max_attempts' => $maxAttempts,
            'created_by' => $createdBy,
        ]);
    }

    public function onRevisionCommitted(int $workspaceId, int $projectId, int $revisionId): void
    {
        try {
            $this->enqueue('timeline_update', ['revision_id' => $revisionId], $workspaceId, $projectId);
        } catch (\Throwable) {
            // best-effort — timeline ถูกเขียนตรงอยู่แล้วใน request path
        }
    }

    public function onMilestoneClosed(int $workspaceId, int $projectId, int $milestoneId): void
    {
        try {
            $this->enqueue('project_update', ['project_id' => $projectId, 'milestone_id' => $milestoneId], $workspaceId, $projectId);
        } catch (\Throwable) {
        }
    }

    public function onDeploymentStatusChanged(int $workspaceId, int $projectId, int $deploymentId, string $status): void
    {
        try {
            $this->enqueue('notification', [
                'message' => sprintf('📦 Deployment #%d (project #%d) → %s', $deploymentId, $projectId, $status),
            ], $workspaceId, $projectId);
        } catch (\Throwable) {
        }
    }

    public function retry(int $jobId): bool
    {
        return $this->jobRepository->requeue($jobId);
    }
}
