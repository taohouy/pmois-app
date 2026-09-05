<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Project\Revision;
use App\Domain\Project\RevisionRepositoryInterface;
use PDO;

final class MySqlRevisionRepository extends BaseRepository implements RevisionRepositoryInterface
{
    public function create(array $data): Revision
    {
        // INSERT ระบุ workspace_id ตรงจาก parameter (ตาม pattern ระบบเดิม)
        $sql = 'INSERT INTO revisions (project_id, workspace_id, milestone_id, repository_id, status, summary,
                    test_result, branch, known_issue, next_action, dev_user_id, dev_ai_consumer_id, submitted_by)
                VALUES (:project_id, :workspace_id, :milestone_id, :repository_id, :status, :summary,
                    :test_result, :branch, :known_issue, :next_action, :dev_user_id, :dev_ai_consumer_id, :submitted_by)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'project_id' => (int) $data['project_id'],
            'workspace_id' => (int) $data['workspace_id'],
            'milestone_id' => $data['milestone_id'],
            'repository_id' => $data['repository_id'],
            'status' => 'submitted',
            'summary' => (string) $data['summary'],
            'test_result' => (string) $data['test_result'],
            'branch' => $data['branch'],
            'known_issue' => $data['known_issue'],
            'next_action' => $data['next_action'],
            'dev_user_id' => $data['dev_user_id'],
            'dev_ai_consumer_id' => $data['dev_ai_consumer_id'],
            'submitted_by' => (int) $data['submitted_by'],
        ]);

        $revision = $this->findById((int) $this->db->lastInsertId());
        if ($revision === null) {
            throw new \RuntimeException('created revision but re-read failed');
        }

        return $revision;
    }

    public function findById(int $id): ?Revision
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM revisions WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $this->assertWorkspaceMatch($row);
        return Revision::fromRow($row);
    }

    public function findByProjectId(int $projectId, ?string $status = null): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM revisions WHERE project_id = :project_id
             ' . ($status !== null ? 'AND status = :status' : '') . '
             AND {{WORKSPACE_FILTER}} ORDER BY id DESC'
        );
        $stmt = $this->db->prepare($sql);
        $params = ['project_id' => $projectId, 'workspace_id' => $this->workspaceId];
        if ($status !== null) {
            $params['status'] = $status;
        }
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $row): Revision => Revision::fromRow($row), $rows);
    }

    public function update(int $id, array $data): bool
    {
        $assignments = [];
        $params = ['id' => $id, 'workspace_id' => $this->workspaceId];
        foreach ($data as $column => $value) {
            $assignments[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        $sql = $this->applyWorkspaceScope(
            'UPDATE revisions SET ' . implode(', ', $assignments) . ' WHERE id = :id AND {{WORKSPACE_FILTER}}'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function findReviews(int $revisionId): array
    {
        $sql = $this->applyWorkspaceScope(
            'SELECT rr.id, rr.revision_id, rr.decision, rr.review_note, rr.reviewed_by, u.name AS reviewer_name, rr.reviewed_at, r.workspace_id
             FROM revision_reviews rr
             JOIN revisions r ON r.id = rr.revision_id
             LEFT JOIN users u ON u.id = rr.reviewed_by
             WHERE rr.revision_id = :revision_id AND {{WORKSPACE_FILTER}}
             ORDER BY rr.id ASC'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['revision_id' => $revisionId, 'workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertWorkspaceMatchAll($rows);

        return $rows;
    }

    public function createReview(int $revisionId, string $decision, ?string $note, int $reviewerId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO revision_reviews (revision_id, decision, review_note, reviewed_by)
             VALUES (:revision_id, :decision, :review_note, :reviewed_by)'
        );
        $stmt->execute([
            'revision_id' => $revisionId,
            'decision' => $decision,
            'review_note' => $note,
            'reviewed_by' => $reviewerId,
        ]);

        return (int) $this->db->lastInsertId();
    }
}
