<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MySQL;

use App\Domain\Workspace\WorkspaceModuleSettingRepositoryInterface;

/**
 * Pattern: Scoped ตรง
 * หมายเหตุ: ใน Phase 0 ตารางนี้มีแค่ "สวิตช์" ยังไม่มี module จริง (Task/Milestone/Risk)
 * ให้เปิดใช้งาน -- เตรียมไว้ล่วงหน้าตาม Foundation scope
 */
final class MySqlWorkspaceModuleSettingRepository extends BaseRepository implements WorkspaceModuleSettingRepositoryInterface
{
    public function isEnabled(int $workspaceId, string $moduleCode): bool
    {
        $stmt = $this->db->prepare(
            'SELECT is_enabled FROM workspace_module_settings
             WHERE workspace_id = :workspace_id AND module_code = :module_code
             LIMIT 1'
        );
        $stmt->execute(['workspace_id' => $workspaceId, 'module_code' => $moduleCode]);
        $row = $stmt->fetch();

        // ไม่มี record = ยังไม่เคยตั้งค่า = ถือว่าปิดอยู่ (default false ตาม schema)
        return $row !== false && (bool) $row['is_enabled'];
    }

    public function setEnabled(int $workspaceId, string $moduleCode, bool $isEnabled, int $changedByUserId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO workspace_module_settings (workspace_id, module_code, is_enabled, enabled_by, enabled_at)
             VALUES (:workspace_id, :module_code, :is_enabled, :enabled_by, NOW())
             ON DUPLICATE KEY UPDATE is_enabled = :is_enabled_update, enabled_by = :enabled_by_update, enabled_at = NOW()'
        );
        $stmt->execute([
            'workspace_id' => $workspaceId,
            'module_code' => $moduleCode,
            // ✅ แก้: cast bool -> int ก่อน bind (เหตุผลเดียวกับ MySqlUserRepository)
            'is_enabled' => $isEnabled ? 1 : 0,
            'enabled_by' => $changedByUserId,
            'is_enabled_update' => $isEnabled ? 1 : 0,
            'enabled_by_update' => $changedByUserId,
        ]);
    }

    public function listForWorkspace(int $workspaceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT module_code, is_enabled FROM workspace_module_settings WHERE workspace_id = :workspace_id'
        );
        $stmt->execute(['workspace_id' => $workspaceId]);
        return $stmt->fetchAll();
    }
}
