-- M6 — Knowledge Center
-- Registry ร่วมสำหรับ Business Rules / Known Issues / Risk Register / Future Enhancements
-- (ADR + Decision Log reuse decision_registers; Project Knowledge Base reuse knowledge_articles)
CREATE TABLE knowledge_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    entry_type ENUM('business_rule','known_issue','risk','future_enhancement') NOT NULL,
    code VARCHAR(50) NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    severity ENUM('low','medium','high','critical') NULL,
    probability ENUM('low','medium','high') NULL,
    impact ENUM('low','medium','high') NULL,
    mitigation TEXT NULL,
    related_revision_id BIGINT UNSIGNED NULL,
    related_url VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ke_workspace (workspace_id),
    KEY idx_ke_project (project_id),
    KEY idx_ke_type (entry_type),
    CONSTRAINT fk_ke_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_ke_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_ke_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
