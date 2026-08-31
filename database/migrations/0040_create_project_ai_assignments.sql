-- Phase 1 / M1 Foundation — project AI assignments
-- Tracks AI agent assignments to projects with role and purpose
CREATE TABLE project_ai_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    ai_consumer_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    purpose VARCHAR(200) NULL,
    assigned_by BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_by BIGINT UNSIGNED NULL,
    revoked_at TIMESTAMP NULL,
    KEY idx_paia_project (project_id),
    KEY idx_paia_consumer (ai_consumer_id),
    CONSTRAINT fk_paia_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_paia_consumer FOREIGN KEY (ai_consumer_id) REFERENCES ai_consumers(id),
    CONSTRAINT fk_paia_role FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_paia_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id),
    CONSTRAINT fk_paia_revoked_by FOREIGN KEY (revoked_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;