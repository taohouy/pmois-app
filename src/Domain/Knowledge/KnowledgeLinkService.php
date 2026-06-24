<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

use App\Domain\Identity\PermissionResolver;
use RuntimeException;

/**
 * KnowledgeLinkService
 *
 * คุม Dynamic Permission Resolution ตาม CTO Decision (Phase 2 Specification Package
 * หมวด 2.5) -- ไม่ hardcode permission mapping ที่ Controller เด็ดขาด
 *
 * ENTITY_PERMISSION_MAP เป็นจุดเดียวในระบบที่ต้องอัปเดตทุกครั้งที่มี entity type ใหม่
 * เกิดขึ้นใน Phase ถัดไป (เช่น Phase 3 อาจมี ai_consumer ที่ link ได้)
 *
 * 🟡 attachment ใช้ permission ของ knowledge_article.view ร่วมไปก่อนตามที่ CTO อนุมัติ
 * (ไม่มี attachment.view เป็น permission code แยกใน Phase 2)
 */
final class KnowledgeLinkService
{
    private const ENTITY_PERMISSION_MAP = [
        'governance_record' => 'governance_record.view',
        'governance_version' => 'governance_version.view',
        'decision_register' => 'decision_register.view',
        'rfc' => 'rfc.view',
        'knowledge_article' => 'knowledge_article.view',
        'attachment' => 'knowledge_article.view', // ตาม CTO Decision -- ไม่เพิ่ม permission code ใหม่
        'project_status_update' => 'project_status_update.view',
    ];

    public function __construct(
        private readonly KnowledgeLinkRepositoryInterface $linkRepo,
        private readonly PermissionResolver $permissionResolver
    ) {
    }

    public function resolvePermissionFor(string $entityType): string
    {
        if (!isset(self::ENTITY_PERMISSION_MAP[$entityType])) {
            throw new RuntimeException("ไม่รู้จัก entity_type: '{$entityType}' -- ไม่มีอยู่ใน ENTITY_PERMISSION_MAP");
        }

        return self::ENTITY_PERMISSION_MAP[$entityType];
    }

    /**
     * @return array<int, KnowledgeLink>
     */
    public function listLinksForEntity(string $entityType, int $entityId, int $userId, int $workspaceId): array
    {
        $permissionCode = $this->resolvePermissionFor($entityType);

        if (!$this->permissionResolver->can($userId, $workspaceId, null, $permissionCode)) {
            throw new RuntimeException("FORBIDDEN: ไม่มีสิทธิ์ดูข้อมูลของ entity_type '{$entityType}'");
        }

        return $this->linkRepo->listByEntity($entityType, $entityId);
    }

    public function createLink(
        string $entityType,
        int $entityId,
        string $linkedType,
        ?int $linkedId,
        ?string $externalUrl,
        ?string $linkLabel,
        int $userId,
        int $workspaceId
    ): KnowledgeLink {
        // เช็คสิทธิ์ของ entity ต้นทาง (สิ่งที่ถูก link เข้า) ก่อนอนุญาตให้สร้าง link
        $permissionCode = $this->resolvePermissionFor($entityType);
        if (!$this->permissionResolver->can($userId, $workspaceId, null, $permissionCode)) {
            throw new RuntimeException("FORBIDDEN: ไม่มีสิทธิ์เชื่อมโยงข้อมูลกับ entity_type '{$entityType}'");
        }

        if ($linkedId === null && $externalUrl === null) {
            throw new RuntimeException('VALIDATION_ERROR: ต้องระบุ linked_id หรือ external_url อย่างใดอย่างหนึ่ง');
        }

        return $this->linkRepo->create($entityType, $entityId, $linkedType, $linkedId, $externalUrl, $linkLabel, $userId);
    }
}
