<?php

declare(strict_types=1);

namespace App\Domain\Automation;

final class AutomationJob
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $workspaceId,
        public readonly ?int $projectId,
        public readonly string $jobType,
        /** @var array<string, mixed> */
        public readonly array $payload,
        public readonly string $status,
        public readonly int $attempts,
        public readonly int $maxAttempts,
        public readonly string $scheduledAt,
        public readonly ?string $startedAt,
        public readonly ?string $finishedAt,
        public readonly ?string $lastError,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $payload = $row['payload'];
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($payload)) {
            $payload = [];
        }

        return new self(
            id: (int) $row['id'],
            workspaceId: $row['workspace_id'] !== null ? (int) $row['workspace_id'] : null,
            projectId: $row['project_id'] !== null ? (int) $row['project_id'] : null,
            jobType: (string) $row['job_type'],
            payload: $payload,
            status: (string) $row['status'],
            attempts: (int) $row['attempts'],
            maxAttempts: (int) $row['max_attempts'],
            scheduledAt: (string) $row['scheduled_at'],
            startedAt: $row['started_at'] !== null ? (string) $row['started_at'] : null,
            finishedAt: $row['finished_at'] !== null ? (string) $row['finished_at'] : null,
            lastError: $row['last_error'] !== null ? (string) $row['last_error'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspaceId,
            'project_id' => $this->projectId,
            'job_type' => $this->jobType,
            'payload' => $this->payload,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'max_attempts' => $this->maxAttempts,
            'scheduled_at' => $this->scheduledAt,
            'started_at' => $this->startedAt,
            'finished_at' => $this->finishedAt,
            'last_error' => $this->lastError,
        ];
    }
}
