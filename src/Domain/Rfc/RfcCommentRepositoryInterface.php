<?php

declare(strict_types=1);

namespace App\Domain\Rfc;

/** Pattern: Scoped ทางอ้อม 1 ชั้น (comment -> rfc -> workspace) */
interface RfcCommentRepositoryInterface
{
    /** @return array<int, RfcComment> */
    public function listByRfc(int $rfcId): array;

    public function create(int $rfcId, string $commentText, int $commentedByUserId): RfcComment;
}
