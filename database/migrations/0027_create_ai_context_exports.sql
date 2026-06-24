-- รวม ai_consumer_id denormalized ตั้งแต่ migration แรก (CTO Decision)
CREATE TABLE ai_context_exports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    api_token_id BIGINT UNSIGNED NOT NULL,
    ai_consumer_id BIGINT UNSIGNED NULL, -- NULL = Human Export, NOT NULL = AI Export (CTO Decision Option B)
    export_type ENUM('project_status','governance_summary','decision_snapshot','full_workspace_context') NOT NULL,
    scope_entity_type VARCHAR(50) NULL,
    scope_entity_id BIGINT UNSIGNED NULL,
    payload_snapshot JSON NOT NULL,
    exported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ace_workspace (workspace_id),
    KEY idx_ace_consumer (ai_consumer_id),
    CONSTRAINT fk_ace_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_ace_token FOREIGN KEY (api_token_id) REFERENCES api_tokens(id),
    CONSTRAINT fk_ace_consumer FOREIGN KEY (ai_consumer_id) REFERENCES ai_consumers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
