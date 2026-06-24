<?php

declare(strict_types=1);

namespace App\Domain\Project;

interface ProjectRepositoryInterface
{
    public function findById(int $id): ?Project;

    /**
     * @return array<int, Project>
     */
    public function listByWorkspace(): array;

    public function create(
        string $code,
        string $name,
        ?string $description,
        int $ownerUserId
    ): Project;

    public function updateStatus(int $id, string $status): bool;
}
