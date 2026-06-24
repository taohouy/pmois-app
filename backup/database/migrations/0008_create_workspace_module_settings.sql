-- Phase 0 / Foundation — workspace_module_settings (สวิตช์เปิด/ปิด optional module)
CREATE TABLE workspace_module_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    module_code ENUM('task_management','milestone_tracking','risk_issue_tracking') NOT NULL,
    is_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    enabled_by BIGINT UNSIGNED NULL,
    enabled_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_workspace_module (workspace_id, module_code),
    CONSTRAINT fk_wms_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_wms_enabled_by FOREIGN KEY (enabled_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
