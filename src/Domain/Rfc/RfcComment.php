<?php

declare(strict_types=1);

namespace App\Domain\Rfc;

final class RfcComment
{
    public function __construct(
        public readonly int $id,
        public readonly int $rfcId,
        public readonly string $commentText,
        public readonly int $commentedBy,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            rfcId: (int) $row['rfc_id'],
            commentText: (string) $row['comment_text'],
            commentedBy: (int) $row['commented_by'],
        );
    }
}
