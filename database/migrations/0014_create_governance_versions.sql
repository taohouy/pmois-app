CREATE TABLE governance_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    governance_record_id BIGINT UNSIGNED NOT NULL,
    version_label VARCHAR(20) NOT NULL,
    content LONGTEXT NOT NULL,
    status ENUM('draft','published','superseded') NOT NULL DEFAULT 'draft',
    effective_date DATE NULL,
    published_by BIGINT UNSIGNED NULL,
    published_at TIMESTAMP NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gov_version_label (governance_record_id, version_label),
    KEY idx_gov_version_record_status (governance_record_id, status),
    CONSTRAINT fk_govver_record FOREIGN KEY (governance_record_id) REFERENCES governance_records(id),
    CONSTRAINT fk_govver_published_by FOREIGN KEY (published_by) REFERENCES users(id),
    CONSTRAINT fk_govver_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
