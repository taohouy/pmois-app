-- Phase 1 / M1 Foundation — projects hierarchy and profile fields
-- Adds hierarchy (parent_project_id), profile fields, and M1 profile fields
ALTER TABLE projects
    ADD COLUMN parent_project_id BIGINT UNSIGNED NULL AFTER workspace_id,
    ADD COLUMN abbreviation VARCHAR(20) NULL AFTER code,
    ADD COLUMN development_mode ENUM('manual','ai_assisted','ai_dev_auto') NOT NULL DEFAULT 'manual' AFTER status,
    ADD COLUMN current_milestone_id BIGINT UNSIGNED NULL AFTER development_mode,
    ADD COLUMN progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER current_milestone_id,
    ADD COLUMN health ENUM('green','yellow','red') NOT NULL DEFAULT 'green' AFTER progress_percent,
    ADD COLUMN profile_completeness_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER health,
    ADD COLUMN archived_at TIMESTAMP NULL AFTER end_date,
    ADD CONSTRAINT fk_projects_parent FOREIGN KEY (parent_project_id) REFERENCES projects(id);