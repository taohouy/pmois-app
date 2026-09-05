<?php

declare(strict_types=1);

namespace App\Domain\Ai;

interface AiConsumerRepositoryInterface
{
    public function findById(int $id): ?AiConsumer;

    /**
     * @return array<int, AiConsumer>
     */
    public function listByWorkspace(): array;

    /**
     * @param array<string, mixed>|null $description
     */
    public function create(string $code, string $name, ?string $description, int $createdByUserId, ?int $providerId = null): AiConsumer;

    public function updateStatus(int $id, string $status): bool;

    public function updateProvider(int $id, int $providerId): bool;
}
