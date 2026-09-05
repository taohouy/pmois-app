-- M1 / R6 Phase 1.5 — Release Registry (CTO Requirement #9)
-- Separate from Timeline (project_status_updates) and revision cycles
CREATE TABLE project_releases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    release_type ENUM('alpha','beta','rc','production','hotfix') NOT NULL,
    version_label VARCHAR(50) NOT NULL,
    status ENUM('planned','in_progress','released','rolled_back','cancelled') NOT NULL DEFAULT 'planned',
    repository_id BIGINT UNSIGNED NULL,
    environment_id BIGINT UNSIGNED NULL,
    milestone_id BIGINT UNSIGNED NULL,
    release_notes TEXT NULL,
    released_by BIGINT UNSIGNED NULL,
    released_at TIMESTAMP NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pr_project_version (project_id, version_label),
    KEY idx_pr_project_type (project_id, release_type),
    KEY idx_pr_workspace (workspace_id),
    CONSTRAINT fk_pr_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_pr_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_pr_repository FOREIGN KEY (repository_id) REFERENCES repositories(id),
    CONSTRAINT fk_pr_environment FOREIGN KEY (environment_id) REFERENCES project_environments(id),
    CONSTRAINT fk_pr_milestone FOREIGN KEY (milestone_id) REFERENCES milestones(id),
    CONSTRAINT fk_pr_released_by FOREIGN KEY (released_by) REFERENCES users(id),
    CONSTRAINT fk_pr_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
