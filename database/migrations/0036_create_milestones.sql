-- Phase 1 / M1 Foundation — milestones
-- Milestones for tracking project progress and CTO review gates
CREATE TABLE milestones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    planned_date DATE NULL,
    closed_by BIGINT UNSIGNED NULL,
    closed_at TIMESTAMP NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_milestone_code (project_id, code),
    KEY idx_milestone_project (project_id),
    KEY idx_milestone_workspace (workspace_id),
    KEY idx_milestone_status (status),
    CONSTRAINT fk_milestone_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_milestone_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_milestone_closed_by FOREIGN KEY (closed_by) REFERENCES users(id),
    CONSTRAINT fk_milestone_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;