<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Knowledge\KnowledgeLink;
use App\Domain\Knowledge\KnowledgeLinkRepositoryInterface;
use RuntimeException;

final class MySqlKnowledgeLinkRepository extends BaseRepository implements KnowledgeLinkRepositoryInterface
{
    public function listByEntity(string $entityType, int $entityId): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM knowledge_links WHERE entity_type = :type AND entity_id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['type' => $entityType, 'id' => $entityId, 'workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll();
        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $r): KnowledgeLink => KnowledgeLink::fromRow($r), $rows);
    }

    public function create(
        string $entityType,
        int $entityId,
        string $linkedType,
        ?int $linkedId,
        ?string $externalUrl,
        ?string $linkLabel,
        int $createdByUserId
    ): KnowledgeLink {
        if ($this->workspaceId === null) {
            throw new RuntimeException('ต้องมี workspace context ก่อนสร้าง knowledge link');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO knowledge_links (workspace_id, entity_type, entity_id, linked_type, linked_id, external_url, link_label, created_by)
             VALUES (:workspace_id, :entity_type, :entity_id, :linked_type, :linked_id, :external_url, :label, :created_by)'
        );
        $stmt->execute([
            'workspace_id' => $this->workspaceId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'linked_type' => $linkedType,
            'linked_id' => $linkedId,
            'external_url' => $externalUrl,
            'label' => $linkLabel,
            'created_by' => $createdByUserId,
        ]);

        $stmt2 = $this->db->prepare('SELECT * FROM knowledge_links WHERE id = :id');
        $stmt2->execute(['id' => $this->db->lastInsertId()]);
        $row = $stmt2->fetch();

        if ($row === false) {
            throw new RuntimeException('สร้าง knowledge link สำเร็จแต่ดึงข้อมูลกลับไม่ได้');
        }

        return KnowledgeLink::fromRow($row);
    }

    public function delete(int $id): bool
    {
        $sql = $this->applyWorkspaceScope('DELETE FROM knowledge_links WHERE id = :id AND {{WORKSPACE_FILTER}}');
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
    }
}
