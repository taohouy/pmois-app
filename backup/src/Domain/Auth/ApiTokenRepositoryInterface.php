<?php

declare(strict_types=1);

namespace App\Domain\Auth;

interface ApiTokenRepositoryInterface
{
    /**
     * สร้าง token ใหม่ -- คืน "raw token" กลับให้ Controller แสดงผลครั้งเดียว
     * (DB เก็บแค่ hash ไม่เก็บ raw ตามที่ design ไว้)
     *
     * @return array{id: int, raw_token: string}
     */
    public function create(int $workspaceId, int $createdByUserId, string $tokenName, ?array $scopes): array;

    public function findByHash(string $tokenHash): ?array;

    public function revoke(int $id): bool;

    public function touchLastUsed(int $id): void;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByWorkspace(int $workspaceId): array;
}
