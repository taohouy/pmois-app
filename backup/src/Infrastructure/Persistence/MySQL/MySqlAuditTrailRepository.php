<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Audit\AuditTrailRepositoryInterface;

/**
 * Pattern: Write-mostly / Immutable — audit_trails ห้ามแก้ไขย้อนหลัง
 * จึงไม่มี method update()/delete() ในคลาสนี้เลยโดยตั้งใจ (ไม่ใช่ลืมทำ)
 *
 * หมายเหตุ: workspace_id เป็น nullable ในตารางนี้ (รองรับ system-level action เช่น login)
 * จึงไม่ extends BaseRepository ตรงๆ เพราะ BaseRepository บังคับว่าต้องมี workspace context เสมอ
 * — ใช้ pattern ที่ผ่อนปรนกว่าเล็กน้อย แต่ยังคง filter workspace_id ตอน list
 */
final class MySqlAuditTrailRepository implements AuditTrailRepositoryInterface
{
    public function __construct(
        private readonly \PDO $db,
        private readonly ?int $workspaceId
    ) {
    }

    public function record(
        ?int $userId,
        string $action,
        ?string $entityType,
        ?int $entityId,
        ?array $beforeValue,
        ?array $afterValue,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_trails
                (workspace_id, user_id, action, entity_type, entity_id, before_value, after_value, ip_address, user_agent)
             VALUES
                (:workspace_id, :user_id, :action, :entity_type, :entity_id, :before_value, :after_value, :ip_address, :user_agent)'
        );

        $stmt->execute([
            'workspace_id' => $this->workspaceId,
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_value' => $beforeValue !== null ? json_encode($beforeValue, JSON_UNESCAPED_UNICODE) : null,
            'after_value' => $afterValue !== null ? json_encode($afterValue, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public function listByEntity(string $entityType, int $entityId): array
    {
        // ยังคง filter workspace_id เพื่อไม่ให้เห็น audit ข้าม workspace แม้ entity_id ชนกัน
        $stmt = $this->db->prepare(
            'SELECT * FROM audit_trails
             WHERE entity_type = :entity_type AND entity_id = :entity_id AND workspace_id = :workspace_id
             ORDER BY created_at DESC'
        );
        $stmt->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'workspace_id' => $this->workspaceId,
        ]);

        return $stmt->fetchAll();
    }
}
