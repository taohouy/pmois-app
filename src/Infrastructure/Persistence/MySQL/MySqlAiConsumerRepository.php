<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Ai\AiConsumer;
use App\Domain\Ai\AiConsumerRepositoryInterface;
use RuntimeException;

final class MySqlAiConsumerRepository extends BaseRepository implements AiConsumerRepositoryInterface
{
    public function findById(int $id): ?AiConsumer
    {
        $sql = $this->applyWorkspaceScope('SELECT * FROM ai_consumers WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1');
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }
        $this->assertWorkspaceMatch($row);

        return AiConsumer::fromRow($row);
    }

    public function listByWorkspace(): array
    {
        $sql = $this->applyWorkspaceScope('SELECT * FROM ai_consumers WHERE {{WORKSPACE_FILTER}} ORDER BY created_at DESC');
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll();
        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $r): AiConsumer => AiConsumer::fromRow($r), $rows);
    }

    public function create(string $code, string $name, ?string $description, int $createdByUserId, ?int $providerId = null): AiConsumer
    {
        if ($this->workspaceId === null) {
            throw new RuntimeException('ต้องมี workspace context ก่อนสร้าง AI consumer');
        }

        $stmt = $this->db->prepare(
            "INSERT INTO ai_consumers (workspace_id, provider_id, code, name, description, status, created_by)
             VALUES (:workspace_id, :provider_id, :code, :name, :description, 'active', :created_by)"
        );
        $stmt->execute([
            'workspace_id' => $this->workspaceId, 'provider_id' => $providerId, 'code' => $code, 'name' => $name,
            'description' => $description, 'created_by' => $createdByUserId,
        ]);

        $consumer = $this->findById((int) $this->db->lastInsertId());
        if ($consumer === null) {
            throw new RuntimeException('สร้าง AI consumer สำเร็จแต่ดึงข้อมูลกลับไม่ได้');
        }

        return $consumer;
    }

    public function updateProvider(int $id, int $providerId): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE ai_consumers SET provider_id = :provider_id WHERE id = :id');

        return $stmt->execute(['provider_id' => $providerId, 'id' => $id]);
    }

    public function updateStatus(int $id, string $status): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE ai_consumers SET status = :status WHERE id = :id');
        return $stmt->execute(['status' => $status, 'id' => $id]);
    }
}
