-- Rollback: remove template provenance column
ALTER TABLE projects DROP FOREIGN KEY fk_projects_source_template;
ALTER TABLE projects DROP COLUMN source_template_id;
