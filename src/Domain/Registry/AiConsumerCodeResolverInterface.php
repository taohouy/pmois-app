<?php

declare(strict_types=1);

namespace App\Domain\Registry;

/**
 * Port สำหรับ resolve Agent code -> ai_consumers row (workspace-scoped)
 * ใช้โดย ProjectTemplateService เพื่อบังคับว่า template อ้างอิง Agent ผ่าน Registry เสมอ
 * (CTO Requirement #2 — ห้าม hardcode ชื่อ AI นอก Registry)
 */
interface AiConsumerCodeResolverInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function resolveByCode(string $code): ?array;
}
