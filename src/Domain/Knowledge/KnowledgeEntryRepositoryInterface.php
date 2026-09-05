<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

interface KnowledgeEntryRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): KnowledgeEntry;

    public function findById(int $id): ?KnowledgeEntry;

    /**
     * @return array<int, KnowledgeEntry>
     */
    public function findByWorkspace(?string $entryType = null, ?int $projectId = null, ?string $status = null): array;

    /**
     * Keyword search บน title/body (workspace-scoped)
     *
     * @return array<int, KnowledgeEntry>
     */
    public function search(string $keyword, ?string $entryType = null): array;

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;

    /**
     * Knowledge Timeline — entries เรียงตามเวลา (ใหม่→เก่า) ต่อ project หรือ workspace
     *
     * @return array<int, array<string, mixed>>
     */
    public function timeline(int $workspaceId, ?int $projectId, int $limit): array;
}
