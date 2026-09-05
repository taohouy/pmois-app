<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Registry\GitProvider;
use App\Domain\Registry\GitProviderRepositoryInterface;
use PDO;

/**
 * Global registry — ไม่ scope ต่อ workspace จึงไม่ extends BaseRepository
 * CTO Constraint: GitLab เท่านั้น (seed ใน migration 0045)
 */
final class MySqlGitProviderRepository implements GitProviderRepositoryInterface
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function listAll(): array
    {
        $rows = $this->db->query('SELECT * FROM git_providers ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn (array $row): GitProvider => GitProvider::fromRow($row), $rows);
    }

    public function findById(int $id): ?GitProvider
    {
        $stmt = $this->db->prepare('SELECT * FROM git_providers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? GitProvider::fromRow($row) : null;
    }

    public function findByCode(string $code): ?GitProvider
    {
        $stmt = $this->db->prepare('SELECT * FROM git_providers WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? GitProvider::fromRow($row) : null;
    }

    public function create(string $code, string $name, ?string $baseUrl, int $createdBy): GitProvider
    {
        $stmt = $this->db->prepare(
            'INSERT INTO git_providers (code, name, base_url, created_by) VALUES (:code, :name, :base_url, :created_by)'
        );
        $stmt->execute(['code' => $code, 'name' => $name, 'base_url' => $baseUrl, 'created_by' => $createdBy]);

        $id = (int) $this->db->lastInsertId();
        $provider = $this->findById($id);
        if ($provider === null) {
            throw new \RuntimeException('created git_provider but re-read failed');
        }
        return $provider;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE git_providers SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
