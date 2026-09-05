<?php

declare(strict_types=1);

namespace App\Domain\Automation;

interface AutomationJobRepositoryInterface
{
    /**
     * @param array<string, mixed> $data — workspace_id?, project_id?, job_type, payload, scheduled_at?, max_attempts?, created_by?
     */
    public function create(array $data): AutomationJob;

    public function findById(int $id): ?AutomationJob;

    /**
     * @param string|null $status
     * @param string|null $jobType
     * @return array<int, AutomationJob>
     */
    public function list(?string $status, ?string $jobType, int $limit): array;

    /**
     * Claim: queued + scheduled_at <= NOW() → status='running'
     *
     * @return array<int, AutomationJob>
     */
    public function claimDue(int $limit): array;

    public function markCompleted(int $id, array $result): void;

    /**
     * บันทึกผล execution ที่ล้มเหลว — attempts ถูกเพิ่มแล้วโดย claim
     * ถ้า attempts < max_attempts → กลับไป queued (scheduled_at = backoff)
     * ถ้าครบ → failed
     */
    public function markFailedWithRetry(int $id, string $error, int $backoffSeconds): void;

    public function requeue(int $id): bool;
}
