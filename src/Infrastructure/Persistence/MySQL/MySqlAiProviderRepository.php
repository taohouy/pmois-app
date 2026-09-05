<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Registry\AiProvider;
use App\Domain\Registry\AiProviderRepositoryInterface;
use PDO;

/**
 * Global registry — ไม่ scope ต่อ workspace จึงไม่ extends BaseRepository
 */
final class MySqlAiProviderRepository implements AiProviderRepositoryInterface
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function listAll(): array
    {
        $rows = $this->db->query('SELECT * FROM ai_providers ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn (array $row): AiProvider => AiProvider::fromRow($row), $rows);
    }

    public function findById(int $id): ?AiProvider
    {
        $stmt = $this->db->prepare('SELECT * FROM ai_providers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? AiProvider::fromRow($row) : null;
    }

    public function findByCode(string $code): ?AiProvider
    {
        $stmt = $this->db->prepare('SELECT * FROM ai_providers WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? AiProvider::fromRow($row) : null;
    }

    public function create(string $code, string $name, int $createdBy): AiProvider
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ai_providers (code, name, created_by) VALUES (:code, :name, :created_by)'
        );
        $stmt->execute(['code' => $code, 'name' => $name, 'created_by' => $createdBy]);

        $id = (int) $this->db->lastInsertId();
        $provider = $this->findById($id);
        if ($provider === null) {
            throw new \RuntimeException('created ai_provider but re-read failed');
        }
        return $provider;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE ai_providers SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
