-- M3 — Deployment Tracking
-- บันทึกการ deploy ของ project ไปยัง environment (เชื่อม release ได้) — ไม่มี secret
CREATE TABLE project_deployments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    release_id BIGINT UNSIGNED NULL,
    environment_id BIGINT UNSIGNED NULL,
    status ENUM('pending','in_progress','deployed','failed','rolled_back') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    deployed_by BIGINT UNSIGNED NULL,
    deployed_at TIMESTAMP NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_pdep_project (project_id),
    KEY idx_pdep_workspace (workspace_id),
    CONSTRAINT fk_pdep_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_pdep_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_pdep_release FOREIGN KEY (release_id) REFERENCES project_releases(id),
    CONSTRAINT fk_pdep_environment FOREIGN KEY (environment_id) REFERENCES project_environments(id),
    CONSTRAINT fk_pdep_deployed_by FOREIGN KEY (deployed_by) REFERENCES users(id),
    CONSTRAINT fk_pdep_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
