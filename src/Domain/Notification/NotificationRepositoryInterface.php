<?php

declare(strict_types=1);

namespace App\Domain\Notification;

interface NotificationRepositoryInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function log(
        ?int $workspaceId,
        ?int $projectId,
        string $channel,
        string $eventType,
        string $message,
        string $status,
        ?string $error = null
    ): int;

    /**
     * @return array<string, int> status => count
     */
    public function statusCounts(): array;
}
