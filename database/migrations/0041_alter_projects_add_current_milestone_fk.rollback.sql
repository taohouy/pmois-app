-- Phase 1 / M1 Foundation — projects current_milestone_id FK (rollback)
ALTER TABLE projects
    DROP FOREIGN KEY fk_projects_current_milestone;