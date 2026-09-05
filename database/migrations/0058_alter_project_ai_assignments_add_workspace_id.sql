-- M1 R2 — workspace scoping สำหรับ project_ai_assignments (ตาม convention: ทุกตาราง
-- workspace-scoped ต้องมี workspace_id — R6 Phase 1.5 repositories อ่าน column นี้)
ALTER TABLE project_ai_assignments
    ADD COLUMN workspace_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER project_id,
    ADD KEY idx_paia_workspace (workspace_id);

-- Backfill จาก project ของแต่ละแถว
UPDATE project_ai_assignments pa
JOIN projects p ON p.id = pa.project_id
SET pa.workspace_id = p.workspace_id
WHERE pa.workspace_id = 0;
