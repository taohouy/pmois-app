<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Notification\NotificationRepositoryInterface;
use PDO;

/**
 * Global log table — ไม่ scope ต่อ workspace จึงไม่ extends BaseRepository
 */
final class MySqlNotificationRepository implements NotificationRepositoryInterface
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function log(
        ?int $workspaceId,
        ?int $projectId,
        string $channel,
        string $eventType,
        string $message,
        string $status,
        ?string $error = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO notifications (workspace_id, project_id, channel, event_type, message, status, error, sent_at)
             VALUES (:workspace_id, :project_id, :channel, :event_type, :message, :status, :error, :sent_at)'
        );
        $stmt->execute([
            'workspace_id' => $workspaceId,
            'project_id' => $projectId,
            'channel' => $channel,
            'event_type' => $eventType,
            'message' => $message,
            'status' => $status,
            'error' => $error,
            'sent_at' => $status === 'sent' ? date('Y-m-d H:i:s') : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function statusCounts(): array
    {
        $rows = $this->db->query('SELECT status, COUNT(*) AS c FROM notifications GROUP BY status')->fetchAll(PDO::FETCH_ASSOC);
        $counts = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['c'];
        }

        return $counts;
    }
}
