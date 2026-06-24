CREATE TABLE governance_version_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    governance_version_id BIGINT UNSIGNED NOT NULL,
    item_code VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    sequence_order INT NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gov_version_item_code (governance_version_id, item_code),
    CONSTRAINT fk_govveritem_version FOREIGN KEY (governance_version_id) REFERENCES governance_versions(id),
    CONSTRAINT fk_govveritem_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
