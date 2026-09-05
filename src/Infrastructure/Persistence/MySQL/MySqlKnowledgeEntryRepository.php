<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Knowledge\KnowledgeEntry;
use App\Domain\Knowledge\KnowledgeEntryRepositoryInterface;
use PDO;

final class MySqlKnowledgeEntryRepository extends BaseRepository implements KnowledgeEntryRepositoryInterface
{
    public function create(array $data): KnowledgeEntry
    {
        // INSERT ระบุ workspace_id ตรงจาก parameter (ตาม pattern ระบบเดิม)
        $sql = 'INSERT INTO knowledge_entries (workspace_id, project_id, entry_type, code, title, body, status,
                    severity, probability, impact, mitigation, related_revision_id, related_url, created_by)
                VALUES (:workspace_id, :project_id, :entry_type, :code, :title, :body, :status,
                    :severity, :probability, :impact, :mitigation, :related_revision_id, :related_url, :created_by)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'workspace_id' => (int) $data['workspace_id'],
            'project_id' => $data['project_id'],
            'entry_type' => (string) $data['entry_type'],
            'code' => $data['code'],
            'title' => (string) $data['title'],
            'body' => (string) $data['body'],
            'status' => (string) $data['status'],
            'severity' => $data['severity'],
            'probability' => $data['probability'],
            'impact' => $data['impact'],
            'mitigation' => $data['mitigation'],
            'related_revision_id' => $data['related_revision_id'],
            'related_url' => $data['related_url'],
            'created_by' => (int) $data['created_by'],
        ]);

        $entry = $this->findById((int) $this->db->lastInsertId());
        if ($entry === null) {
            throw new \RuntimeException('created knowledge entry but re-read failed');
        }

        return $entry;
    }

    public function findById(int $id): ?KnowledgeEntry
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM knowledge_entries WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);
        return KnowledgeEntry::fromRow($row);
    }

    public function findByWorkspace(?string $entryType = null, ?int $projectId = null, ?string $status = null): array
    {
        $conditions = [];
        $params = ['workspace_id' => $this->workspaceId];

        if ($entryType !== null) {
            $conditions[] = 'entry_type = :entry_type';
            $params['entry_type'] = $entryType;
        }
        if ($projectId !== null) {
            $conditions[] = 'project_id = :project_id';
            $params['project_id'] = $projectId;
        }
        if ($status !== null) {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM knowledge_entries WHERE {{WORKSPACE_FILTER}}
             ' . ($conditions !== [] ? 'AND ' . implode(' AND ', $conditions) : '') . '
             ORDER BY created_at DESC, id DESC'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $row): KnowledgeEntry => KnowledgeEntry::fromRow($row), $rows);
    }

    public function search(string $keyword, ?string $entryType = null): array
    {
        $conditions = ['(title LIKE :kw OR body LIKE :kw)'];
        $params = ['workspace_id' => $this->workspaceId, 'kw' => '%' . $keyword . '%'];

        if ($entryType !== null) {
            $conditions[] = 'entry_type = :entry_type';
            $params['entry_type'] = $entryType;
        }

        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM knowledge_entries WHERE {{WORKSPACE_FILTER}}
             AND ' . implode(' AND ', $conditions) . '
             ORDER BY created_at DESC, id DESC LIMIT 50'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $row): KnowledgeEntry => KnowledgeEntry::fromRow($row), $rows);
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['title', 'body', 'status', 'severity', 'probability', 'impact', 'mitigation', 'related_url'];
        $filtered = array_intersect_key($data, array_flip($allowed));
        if ($filtered === []) {
            throw new \InvalidArgumentException('no updatable fields supplied');
        }

        $assignments = [];
        $params = ['id' => $id, 'workspace_id' => $this->workspaceId];
        foreach ($filtered as $column => $value) {
            $assignments[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        $sql = $this->applyWorkspaceScope(
            'UPDATE knowledge_entries SET ' . implode(', ', $assignments) . ' WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $sql = $this->applyWorkspaceScope(
            'DELETE FROM knowledge_entries WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);

        return $stmt->rowCount() > 0;
    }

    public function timeline(int $workspaceId, ?int $projectId, int $limit): array
    {
        $params = ['workspace_id' => $workspaceId];
        $projectFilter = '';

        if ($projectId !== null) {
            $projectFilter = 'AND project_id = :project_id';
            $params['project_id'] = $projectId;
        }

        $stmt = $this->db->prepare(
            "SELECT id, entry_type, code, title, status, created_at
             FROM knowledge_entries
             WHERE workspace_id = :workspace_id {$projectFilter}
             ORDER BY created_at DESC, id DESC
             LIMIT :lim"
        );
        $stmt->bindValue('workspace_id', $workspaceId, PDO::PARAM_INT);
        if ($projectId !== null) {
            $stmt->bindValue('project_id', $projectId, PDO::PARAM_INT);
        }
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
