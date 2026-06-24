CREATE TABLE governance_adoption_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    governance_adoption_id BIGINT UNSIGNED NOT NULL,
    governance_version_item_id BIGINT UNSIGNED NOT NULL,
    compliance_status ENUM('compliant','in_progress','non_compliant','na') NOT NULL DEFAULT 'in_progress',
    evidence_note TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_adoption_item (governance_adoption_id, governance_version_item_id),
    CONSTRAINT fk_adoptionitem_adoption FOREIGN KEY (governance_adoption_id) REFERENCES governance_adoptions(id),
    CONSTRAINT fk_adoptionitem_versionitem FOREIGN KEY (governance_version_item_id) REFERENCES governance_version_items(id),
    CONSTRAINT fk_adoptionitem_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id),
    CONSTRAINT fk_adoptionitem_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
