<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

/** Pattern: Scoped ตรง -- polymorphic, ไม่มี FK ไปยัง entity ปลายทาง */
interface KnowledgeLinkRepositoryInterface
{
    /** @return array<int, KnowledgeLink> */
    public function listByEntity(string $entityType, int $entityId): array;

    public function create(
        string $entityType,
        int $entityId,
        string $linkedType,
        ?int $linkedId,
        ?string $externalUrl,
        ?string $linkLabel,
        int $createdByUserId
    ): KnowledgeLink;

    public function delete(int $id): bool;
}
