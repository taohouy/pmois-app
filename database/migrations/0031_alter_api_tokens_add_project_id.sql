-- Phase 4 / Project-Scoped Token Enforcement
-- เพิ่ม project_id (nullable) ใน api_tokens
-- NULL  = workspace-level token (ADMIN / พฤติกรรมเดิมที่ยังใช้อยู่)
-- ≠NULL = project-scoped token (เข้าได้เฉพาะ project นั้น)
ALTER TABLE api_tokens
    ADD COLUMN project_id BIGINT UNSIGNED NULL DEFAULT NULL
        AFTER workspace_id,
    ADD CONSTRAINT fk_tokens_project
        FOREIGN KEY (project_id) REFERENCES projects(id);
