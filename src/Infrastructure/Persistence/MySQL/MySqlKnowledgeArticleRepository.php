<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Knowledge\KnowledgeArticle;
use App\Domain\Knowledge\KnowledgeArticleRepositoryInterface;
use RuntimeException;

final class MySqlKnowledgeArticleRepository extends BaseRepository implements KnowledgeArticleRepositoryInterface
{
    public function findById(int $id): ?KnowledgeArticle
    {
        $sql = $this->applyWorkspaceScope('SELECT * FROM knowledge_articles WHERE id = :id AND {{WORKSPACE_FILTER}} LIMIT 1');
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }
        $this->assertWorkspaceMatch($row);

        return KnowledgeArticle::fromRow($row);
    }

    public function listByWorkspace(?string $category = null, ?int $projectId = null): array
    {
        $conditions = ['{{WORKSPACE_FILTER}}'];
        $params = ['workspace_id' => $this->workspaceId];

        if ($category !== null) {
            $conditions[] = 'category = :category';
            $params['category'] = $category;
        }
        if ($projectId !== null) {
            $conditions[] = 'project_id = :project_id';
            $params['project_id'] = $projectId;
        }

        $sql = $this->applyWorkspaceScope(
            'SELECT * FROM knowledge_articles WHERE ' . implode(' AND ', $conditions) . ' ORDER BY created_at DESC'
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $r): KnowledgeArticle => KnowledgeArticle::fromRow($r), $rows);
    }

    public function create(
        string $title,
        ?string $category,
        string $content,
        ?int $projectId,
        int $authoredByUserId,
        int $createdByUserId
    ): KnowledgeArticle {
        if ($this->workspaceId === null) {
            throw new RuntimeException('ต้องมี workspace context ก่อนสร้าง knowledge article');
        }

        $stmt = $this->db->prepare(
            "INSERT INTO knowledge_articles (workspace_id, project_id, title, category, content, status, authored_by, created_by)
             VALUES (:workspace_id, :project_id, :title, :category, :content, 'draft', :authored_by, :created_by)"
        );
        $stmt->execute([
            'workspace_id' => $this->workspaceId,
            'project_id' => $projectId,
            'title' => $title,
            'category' => $category,
            'content' => $content,
            'authored_by' => $authoredByUserId,
            'created_by' => $createdByUserId,
        ]);

        $article = $this->findById((int) $this->db->lastInsertId());
        if ($article === null) {
            throw new RuntimeException('สร้าง knowledge article สำเร็จแต่ดึงข้อมูลกลับไม่ได้');
        }

        return $article;
    }

    public function update(int $id, string $title, ?string $category, string $content): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE knowledge_articles SET title = :title, category = :category, content = :content WHERE id = :id'
        );
        return $stmt->execute(['title' => $title, 'category' => $category, 'content' => $content, 'id' => $id]);
    }

    public function updateStatus(int $id, string $status): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $extra = $status === 'published' ? ', published_at = NOW()' : '';
        $stmt = $this->db->prepare("UPDATE knowledge_articles SET status = :status{$extra} WHERE id = :id");
        return $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /**
     * แก้ไข (พบจากการทดสอบจริงบน server): เดิมออกแบบให้ใช้ MySQL FULLTEXT + ngram parser
     * แต่ server จริงรัน MariaDB ซึ่ง "ไม่มี ngram parser" เลย (เป็นฟีเจอร์เฉพาะของ MySQL
     * จาก Oracle เท่านั้น) -- เปลี่ยนมาใช้ SQL LIKE-based substring search แทน
     *
     * ข้อดีที่ไม่คาดคิด: LIKE '%คำ%' รองรับภาษาไทยได้ดีกว่า ngram ด้วยซ้ำ เพราะไม่สนใจ
     * word boundary เลย (ไม่มีปัญหาเรื่องภาษาไทยไม่มีเว้นวรรคคำที่เคย flag ไว้ตอน design)
     * ข้อเสีย: ไม่มี relevance ranking แบบ FULLTEXT จริง ใช้ weighted heuristic แทน
     * (title match ให้คะแนนสูงกว่า content match) และช้ากว่าตอนข้อมูลมาก (full scan)
     */
    public function search(string $query, ?string $category = null): array
    {
        // escape wildcard character ของ LIKE เอง (% และ _) ที่อาจอยู่ในคำค้นของผู้ใช้
        // เพื่อไม่ให้ตีความเป็น wildcard ของ SQL โดยไม่ตั้งใจ
        $escapedQuery = addcslashes($query, '%_\\');
        $likePattern = '%' . $escapedQuery . '%';

        $conditions = ['{{WORKSPACE_FILTER}}', "status = 'published'", '(title LIKE :like_title OR content LIKE :like_content)'];
        $params = [
            'workspace_id' => $this->workspaceId,
            'like_title' => $likePattern,
            'like_content' => $likePattern,
            'like_title_score' => $likePattern,
        ];

        if ($category !== null) {
            $conditions[] = 'category = :category';
            $params['category'] = $category;
        }

        $sql = $this->applyWorkspaceScope(
            'SELECT *, (CASE WHEN title LIKE :like_title_score THEN 2 ELSE 1 END) AS relevance
             FROM knowledge_articles WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY relevance DESC, created_at DESC'
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $this->assertWorkspaceMatchAll($rows);

        return array_map(static fn (array $r): KnowledgeArticle => KnowledgeArticle::fromRow($r), $rows);
    }
}
