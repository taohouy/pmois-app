<?php

declare(strict_types=1);

namespace App\Domain\Ai;

/** Pattern: Scoped ตรง */
interface AiConsumerRepositoryInterface
{
    public function findById(int $id): ?AiConsumer;

    /** @return array<int, AiConsumer> */
    public function listByWorkspace(): array;

    public function create(string $code, string $name, ?string $description, int $createdByUserId): AiConsumer;

    public function updateStatus(int $id, string $status): bool;
}
