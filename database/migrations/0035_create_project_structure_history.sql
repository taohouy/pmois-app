-- Phase 1 / M1 Foundation — project structure history
-- Tracks all hierarchy changes: move workspace, change parent, promote, demote
CREATE TABLE project_structure_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    change_type ENUM('move_workspace','change_parent','promote_to_workspace','demote_to_child') NOT NULL,
    from_workspace_id BIGINT UNSIGNED NULL,
    to_workspace_id BIGINT UNSIGNED NULL,
    from_parent_project_id BIGINT UNSIGNED NULL,
    to_parent_project_id BIGINT UNSIGNED NULL,
    reason TEXT NULL,
    changed_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_psh_project (project_id),
    KEY idx_psh_created (created_at),
    CONSTRAINT fk_psh_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_psh_from_ws FOREIGN KEY (from_workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_psh_to_ws FOREIGN KEY (to_workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_psh_from_parent FOREIGN KEY (from_parent_project_id) REFERENCES projects(id),
    CONSTRAINT fk_psh_to_parent FOREIGN KEY (to_parent_project_id) REFERENCES projects(id),
    CONSTRAINT fk_psh_changed_by FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;