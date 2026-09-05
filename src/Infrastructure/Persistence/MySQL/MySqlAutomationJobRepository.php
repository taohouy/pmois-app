<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Automation\AutomationJob;
use App\Domain\Automation\AutomationJobRepositoryInterface;
use PDO;
use RuntimeException;

/**
 * Global queue table — ไม่ scope ต่อ workspace (worker ทำงานข้าม workspace)
 * workspace isolation บังคับที่ controller (automation.view/manage)
 */
final class MySqlAutomationJobRepository implements AutomationJobRepositoryInterface
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function create(array $data): AutomationJob
    {
        $stmt = $this->db->prepare(
            'INSERT INTO automation_jobs (workspace_id, project_id, job_type, payload, max_attempts, scheduled_at, created_by)
             VALUES (:workspace_id, :project_id, :job_type, :payload, :max_attempts, :scheduled_at, :created_by)'
        );
        $stmt->execute([
            'workspace_id' => $data['workspace_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'job_type' => (string) $data['job_type'],
            'payload' => json_encode($data['payload'] ?? [], JSON_UNESCAPED_UNICODE),
            'max_attempts' => (int) ($data['max_attempts'] ?? 3),
            'scheduled_at' => $data['scheduled_at'] ?? date('Y-m-d H:i:s'),
            'created_by' => $data['created_by'] ?? null,
        ]);

        $job = $this->findById((int) $this->db->lastInsertId());
        if ($job === null) {
            throw new RuntimeException('created automation job but re-read failed');
        }

        return $job;
    }

    public function findById(int $id): ?AutomationJob
    {
        $stmt = $this->db->prepare('SELECT * FROM automation_jobs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? AutomationJob::fromRow($row) : null;
    }

    public function list(?string $status, ?string $jobType, int $limit): array
    {
        $conditions = [];
        $params = [];

        if ($status !== null) {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }
        if ($jobType !== null) {
            $conditions[] = 'job_type = :job_type';
            $params['job_type'] = $jobType;
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM automation_jobs
             ' . ($conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '') . '
             ORDER BY id DESC LIMIT :lim'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): AutomationJob => AutomationJob::fromRow($row), $rows);
    }

    public function claimDue(int $limit): array
    {
        // SELECT ก่อนแล้ว claim ทีละ id — single worker ตาม M8 design (multi-worker คือ future work)
        $stmt = $this->db->prepare(
            "SELECT id FROM automation_jobs
             WHERE status = 'queued' AND scheduled_at <= NOW()
             ORDER BY id ASC LIMIT :lim"
        );
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $ids = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));

        $claimed = [];
        foreach ($ids as $id) {
            $update = $this->db->prepare(
                "UPDATE automation_jobs
                 SET status = 'running', started_at = NOW(), attempts = attempts + 1
                 WHERE id = :id AND status = 'queued'"
            );
            $update->execute(['id' => $id]);
            if ($update->rowCount() > 0) {
                $job = $this->findById($id);
                if ($job !== null) {
                    $claimed[] = $job;
                }
            }
        }

        return $claimed;
    }

    public function markCompleted(int $id, array $result): void
    {
        $stmt = $this->db->prepare(
            "UPDATE automation_jobs SET status = 'completed', finished_at = NOW(), result = :result, last_error = NULL
             WHERE id = :id"
        );
        $stmt->execute(['id' => $id, 'result' => json_encode($result, JSON_UNESCAPED_UNICODE)]);
    }

    public function markFailedWithRetry(int $id, string $error, int $backoffSeconds): void
    {
        $stmt = $this->db->prepare(
            "UPDATE automation_jobs
             SET last_error = :error,
                 status = CASE WHEN attempts >= max_attempts THEN 'failed' ELSE 'queued' END,
                 scheduled_at = CASE WHEN attempts >= max_attempts THEN scheduled_at ELSE DATE_ADD(NOW(), INTERVAL :backoff SECOND) END,
                 finished_at = CASE WHEN attempts >= max_attempts THEN NOW() ELSE NULL END
             WHERE id = :id"
        );
        $stmt->execute(['id' => $id, 'error' => $error, 'backoff' => $backoffSeconds]);
    }

    public function requeue(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE automation_jobs
             SET status = 'queued', scheduled_at = NOW(), attempts = 0, last_error = NULL, finished_at = NULL
             WHERE id = :id AND status = 'failed'"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
