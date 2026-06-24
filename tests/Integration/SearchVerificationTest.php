<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Persistence\MySQL\MySqlKnowledgeArticleRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * SearchVerificationTest
 *
 * ครอบคลุมตาม Phase 2 Specification Package -- Search Verification Plan
 *
 * ⚠️ ต้องมี FULLTEXT INDEX WITH PARSER ngram บนตาราง knowledge_articles ก่อนรัน
 * (migration 0024) ไม่งั้น query MATCH...AGAINST จะ error "no fulltext index"
 */
final class SearchVerificationTest extends TestCase
{
    private PDO $db;
    private int $workspaceId;
    private int $userId;
    private MySqlKnowledgeArticleRepository $repo;

    protected function setUp(): void
    {
        $this->db = new PDO(
            getenv('TEST_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=pmois_test;charset=utf8mb4',
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->beginTransaction();

        $this->userId = $this->seedUser();
        $this->workspaceId = $this->seedWorkspace($this->userId);
        $this->repo = new MySqlKnowledgeArticleRepository($this->db, $this->workspaceId);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testSearchFindsThaiSubstringMatch(): void
    {
        $article = $this->repo->create('นโยบายความปลอดภัย', 'policy', 'นโยบายความปลอดภัยข้อมูลส่วนบุคคลขององค์กร', null, $this->userId, $this->userId);
        $this->repo->updateStatus($article->id, 'published');

        $results = $this->repo->search('ปลอดภัย');

        $found = array_filter($results, static fn ($a) => $a->id === $article->id);
        $this->assertNotEmpty($found, 'ngram parser ควรเจอ substring ภาษาไทยที่ match ได้');
    }

    public function testSearchFindsEnglishKeyword(): void
    {
        $article = $this->repo->create('Security Guide', 'technical', 'This document explains the security policy in detail', null, $this->userId, $this->userId);
        $this->repo->updateStatus($article->id, 'published');

        $results = $this->repo->search('security');

        $found = array_filter($results, static fn ($a) => $a->id === $article->id);
        $this->assertNotEmpty($found);
    }

    public function testSearchDoesNotReturnDraftArticles(): void
    {
        $article = $this->repo->create('Draft Document', null, 'เนื้อหาทดสอบที่ยังไม่เผยแพร่ unique keyword zzqqxx', null, $this->userId, $this->userId);
        // ไม่เรียก updateStatus -- ปล่อยเป็น draft

        $results = $this->repo->search('zzqqxx');

        $found = array_filter($results, static fn ($a) => $a->id === $article->id);
        $this->assertEmpty($found, 'Draft article ต้องไม่ถูกค้นเจอในการค้นหาทั่วไป');
    }

    public function testSearchWithCategoryFilter(): void
    {
        $a1 = $this->repo->create('Doc A', 'technical', 'unique keyword abcxyz123', null, $this->userId, $this->userId);
        $this->repo->updateStatus($a1->id, 'published');

        $a2 = $this->repo->create('Doc B', 'faq', 'unique keyword abcxyz123', null, $this->userId, $this->userId);
        $this->repo->updateStatus($a2->id, 'published');

        $results = $this->repo->search('abcxyz123', 'technical');

        $ids = array_map(static fn ($a) => $a->id, $results);
        $this->assertContains($a1->id, $ids);
        $this->assertNotContains($a2->id, $ids, 'Filter category ต้องไม่คืนผลของ category อื่น');
    }

    public function testSearchIsolatedByWorkspace(): void
    {
        $otherUserId = $this->seedUser();
        $otherWorkspaceId = $this->seedWorkspace($otherUserId);
        $otherRepo = new MySqlKnowledgeArticleRepository($this->db, $otherWorkspaceId);

        $articleOther = $otherRepo->create('Other WS Doc', null, 'unique keyword uniquewordzz999', null, $otherUserId, $otherUserId);
        $otherRepo->updateStatus($articleOther->id, 'published');

        $resultsFromA = $this->repo->search('uniquewordzz999');

        $this->assertEmpty($resultsFromA, 'ผลค้นหาต้อง isolate ตาม workspace ไม่เห็นข้าม workspace');
    }

    private function seedUser(): int
    {
        $stmt = $this->db->prepare("INSERT INTO users (name, email, password_hash, status, is_platform_admin) VALUES ('Test', :email, 'hash', 'active', 0)");
        $stmt->execute(['email' => 'search-' . uniqid() . '@example.com']);
        return (int) $this->db->lastInsertId();
    }

    private function seedWorkspace(int $createdBy): int
    {
        $stmt = $this->db->prepare("INSERT INTO workspaces (code, name, status, created_by) VALUES (:code, :code, 'active', :created_by)");
        $stmt->execute(['code' => 'WS-SEARCH-' . uniqid(), 'created_by' => $createdBy]);
        return (int) $this->db->lastInsertId();
    }
}
