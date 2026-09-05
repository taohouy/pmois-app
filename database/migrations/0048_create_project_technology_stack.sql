-- M1 / R6 Phase 1.5 — Technology Stack Registry (CTO Requirement #6)
-- Optional at creation; CTO/Dev fill progressively (project.techstack.manage)
CREATE TABLE project_technology_stack (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    layer ENUM('language','framework','database','runtime','frontend','infrastructure','tooling','other') NOT NULL,
    name VARCHAR(150) NOT NULL,
    version VARCHAR(50) NULL,
    notes TEXT NULL,
    status ENUM('active','deprecated','planned') NOT NULL DEFAULT 'active',
    added_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pts_project_layer_name (project_id, layer, name),
    KEY idx_pts_workspace (workspace_id),
    CONSTRAINT fk_pts_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_pts_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_pts_added_by FOREIGN KEY (added_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
