-- M1 / R6 Phase 1.5 — project provenance (template applied at creation)
ALTER TABLE projects
    ADD COLUMN source_template_id BIGINT UNSIGNED NULL AFTER parent_project_id,
    ADD CONSTRAINT fk_projects_source_template FOREIGN KEY (source_template_id) REFERENCES project_templates(id);
