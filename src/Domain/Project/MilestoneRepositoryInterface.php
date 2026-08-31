<?php

declare(strict_types=1);

namespace App\Domain\Project;

interface MilestoneRepositoryInterface
{
    public function findById(int $id): ?Milestone;
    public function findByProjectId(int $projectId): array;
    public function findByProjectIdAndCode(int $projectId, string $code): ?Milestone;
    public function create(Milestone $milestone): int;
    public function update(Milestone $milestone): bool;
    public function close(int $id, int $closedBy): bool;
    public function open(int $id): bool;
}