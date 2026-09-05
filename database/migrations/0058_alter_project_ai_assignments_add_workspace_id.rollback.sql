-- Rollback: project_ai_assignments workspace_id
ALTER TABLE project_ai_assignments DROP KEY idx_paia_workspace;
ALTER TABLE project_ai_assignments DROP COLUMN workspace_id;
