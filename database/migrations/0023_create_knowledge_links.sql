CREATE TABLE knowledge_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    linked_type VARCHAR(50) NOT NULL,
    linked_id BIGINT UNSIGNED NULL,
    external_url VARCHAR(500) NULL,
    link_label VARCHAR(150) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kl_entity (entity_type, entity_id),
    KEY idx_kl_linked (linked_type, linked_id),
    KEY idx_kl_workspace (workspace_id),
    CONSTRAINT fk_kl_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_kl_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
