-- M1 / R6 Phase 1.5 — Environment Registry (CTO Requirement #7)
-- NO secrets/passwords ever — credential_reference is a pointer to a secret store only
CREATE TABLE project_environments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    environment ENUM('development','uat','production') NOT NULL,
    name VARCHAR(150) NOT NULL,
    url VARCHAR(500) NULL,
    runtime VARCHAR(150) NULL,
    php_version VARCHAR(20) NULL,
    database_engine VARCHAR(100) NULL,
    deploy_path VARCHAR(500) NULL,
    credential_reference VARCHAR(200) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pe_project_env_name (project_id, environment, name),
    KEY idx_pe_workspace (workspace_id),
    CONSTRAINT fk_pe_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_pe_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_pe_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
