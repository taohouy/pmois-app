<?php

declare(strict_types=1);

namespace App\Domain\Audit;

interface AuditTrailRepositoryInterface
{
    /**
     * เขียน audit record เดียว — ไม่มี method update/delete โดยตั้งใจ
     * เพราะ audit_trails ต้อง immutable (System Design v0.1 หมวด 2.20)
     */
    public function record(
        ?int $userId,
        string $action,
        ?string $entityType,
        ?int $entityId,
        ?array $beforeValue,
        ?array $afterValue,
        ?string $ipAddress,
        ?string $userAgent
    ): void;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByEntity(string $entityType, int $entityId): array;
}
