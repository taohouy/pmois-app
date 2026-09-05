-- M1 / R6 Phase 1.5 — Dependency Registry (CTO Requirement #8)
-- Typed directed edges; depends_on must stay acyclic (enforced in service layer)
CREATE TABLE project_dependencies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    related_project_id BIGINT UNSIGNED NOT NULL,
    dependency_type ENUM('depends_on','blocked_by') NOT NULL,
    note VARCHAR(300) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pd_edge (project_id, related_project_id, dependency_type),
    KEY idx_pd_related (related_project_id),
    CONSTRAINT fk_pd_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_pd_related FOREIGN KEY (related_project_id) REFERENCES projects(id),
    CONSTRAINT fk_pd_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_pd_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
