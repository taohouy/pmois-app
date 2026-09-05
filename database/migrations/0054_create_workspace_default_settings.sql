-- M1 / R6 Phase 1.5 — Workspace Default Settings (CTO Requirement #11)
-- 1:1 with workspace; typed columns for FK integrity
CREATE TABLE workspace_default_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    default_cto_user_id BIGINT UNSIGNED NULL,
    default_dev_user_id BIGINT UNSIGNED NULL,
    default_governance_version_id BIGINT UNSIGNED NULL,
    default_git_provider_id BIGINT UNSIGNED NULL,
    default_project_template_id BIGINT UNSIGNED NULL,
    default_development_mode ENUM('manual','ai_assisted','ai_dev_auto') NOT NULL DEFAULT 'manual',
    default_permission_preset VARCHAR(50) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wds_workspace (workspace_id),
    CONSTRAINT fk_wds_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_wds_cto FOREIGN KEY (default_cto_user_id) REFERENCES users(id),
    CONSTRAINT fk_wds_dev FOREIGN KEY (default_dev_user_id) REFERENCES users(id),
    CONSTRAINT fk_wds_govver FOREIGN KEY (default_governance_version_id) REFERENCES governance_versions(id),
    CONSTRAINT fk_wds_gitprov FOREIGN KEY (default_git_provider_id) REFERENCES git_providers(id),
    CONSTRAINT fk_wds_template FOREIGN KEY (default_project_template_id) REFERENCES project_templates(id),
    CONSTRAINT fk_wds_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
