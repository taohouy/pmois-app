-- Phase 1 / M1 Foundation — projects current_milestone_id FK
-- Adds FK constraint for current_milestone_id after milestones table exists
ALTER TABLE projects
    ADD CONSTRAINT fk_projects_current_milestone FOREIGN KEY (current_milestone_id) REFERENCES milestones(id);