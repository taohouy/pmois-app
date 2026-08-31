-- Phase 1 / M1 Foundation — repositories
-- GitLab repository registry (manual registration, read-only sync)
CREATE TABLE repositories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    repository_type ENUM('main','supporting','docs','test','infra','custom') NOT NULL DEFAULT 'main',
    repository_name VARCHAR(200) NOT NULL,
    gitlab_url VARCHAR(500) NOT NULL,
    default_branch VARCHAR(100) NOT NULL DEFAULT 'main',
    development_branch VARCHAR(100) NULL,
    release_branch VARCHAR(100) NULL,
    production_branch VARCHAR(100) NULL,
    repository_status ENUM('active','archived') NOT NULL DEFAULT 'active',
    credential_reference VARCHAR(200) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_repo_url (gitlab_url),
    KEY idx_repo_project (project_id),
    KEY idx_repo_workspace (workspace_id),
    CONSTRAINT fk_repo_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_repo_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_repo_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;