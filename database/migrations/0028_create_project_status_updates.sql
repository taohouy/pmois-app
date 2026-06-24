-- ⚠️ Gap ที่พบระหว่าง Phase 3 implementation: ตารางนี้ระบุไว้ใน Foundation Design
-- ตั้งแต่ System Design v0.1 (Phase 0 scope) แต่ไม่เคยถูกสร้างจริงตอน migration 0001-0012
-- เพิ่มเข้ามาตอนนี้เพราะ endpoint /projects/status ของ AI Context Platform ต้องใช้ตรงๆ
CREATE TABLE project_status_updates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    report_date DATE NOT NULL,
    overall_status ENUM('on_track','at_risk','off_track') NOT NULL,
    summary TEXT NOT NULL,
    key_achievements TEXT NULL,
    key_issues TEXT NULL,
    next_steps TEXT NULL,
    submitted_by BIGINT UNSIGNED NOT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_psu_project (project_id),
    KEY idx_psu_workspace (workspace_id),
    CONSTRAINT fk_psu_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_psu_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_psu_submitted_by FOREIGN KEY (submitted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
