<?php

declare(strict_types=1);

namespace App\Domain\Project;

interface RevisionRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Revision;

    public function findById(int $id): ?Revision;

    /**
     * @param string|null $status filter
     * @return array<int, Revision>
     */
    public function findByProjectId(int $projectId, ?string $status = null): array;

    /**
     * @param array<string, mixed> $data — status, commit_hash, branch, push_status, committed_at
     */
    public function update(int $id, array $data): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findReviews(int $revisionId): array;

    public function createReview(int $revisionId, string $decision, ?string $note, int $reviewerId): int;
}
