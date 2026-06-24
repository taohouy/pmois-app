<?php

declare(strict_types=1);

namespace App\Domain\Ai;

/** Pattern: Write-mostly / Immutable (เหมือน AuditTrailRepository) */
interface AiContextExportRepositoryInterface
{
    /**
     * @param array<string, mixed> $payloadSnapshot ก้อนข้อมูลจริงที่ส่งออก (payload "data" ทั้งหมด)
     */
    public function record(
        int $apiTokenId,
        ?int $aiConsumerId,
        string $exportType,
        ?string $scopeEntityType,
        ?int $scopeEntityId,
        array $payloadSnapshot
    ): void;
}
