<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

final class Attachment
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly string $originalName,
        public readonly string $storedPath,
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        public readonly string $checksum,
        public readonly int $uploadedBy,
        public readonly ?string $deletedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            workspaceId: (int) $row['workspace_id'],
            originalName: (string) $row['original_name'],
            storedPath: (string) $row['stored_path'],
            mimeType: (string) $row['mime_type'],
            sizeBytes: (int) $row['size_bytes'],
            checksum: (string) ($row['checksum'] ?? ''),
            uploadedBy: (int) $row['uploaded_by'],
            deletedAt: $row['deleted_at'] !== null ? (string) $row['deleted_at'] : null,
        );
    }
}
