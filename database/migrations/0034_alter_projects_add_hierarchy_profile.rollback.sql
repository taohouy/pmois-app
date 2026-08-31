-- Phase 1 / M1 Foundation — projects hierarchy and profile fields (rollback)
ALTER TABLE projects
    DROP FOREIGN KEY fk_projects_parent,
    DROP COLUMN parent_project_id,
    DROP COLUMN abbreviation,
    DROP COLUMN development_mode,
    DROP COLUMN current_milestone_id,
    DROP COLUMN progress_percent,
    DROP COLUMN health,
    DROP COLUMN profile_completeness_percent,
    DROP COLUMN archived_at;