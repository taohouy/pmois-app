-- M8 — Automation Center: automation queue
-- job_type ครอบ scope: timeline_update / project_update (Automatic Updates),
-- gitlab_sync (GitLab Integration), ai_dev_auto (AI Dev Auto Integration),
-- notification (Telegram Automation)
CREATE TABLE automation_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NULL,
    project_id BIGINT UNSIGNED NULL,
    job_type ENUM('timeline_update','project_update','gitlab_sync','ai_dev_auto','notification') NOT NULL,
    payload JSON NOT NULL,
    status ENUM('queued','running','completed','failed') NOT NULL DEFAULT 'queued',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
    scheduled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    finished_at TIMESTAMP NULL,
    last_error TEXT NULL,
    result JSON NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_aj_status_sched (status, scheduled_at),
    KEY idx_aj_workspace (workspace_id),
    KEY idx_aj_project (project_id),
    CONSTRAINT fk_aj_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_aj_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_aj_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
