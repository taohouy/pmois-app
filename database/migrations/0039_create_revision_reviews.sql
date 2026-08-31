-- Phase 1 / M1 Foundation — revision reviews
-- Tracks CTO review decisions on revisions
CREATE TABLE revision_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revision_id BIGINT UNSIGNED NOT NULL,
    decision ENUM('approved','rejected') NOT NULL,
    review_note TEXT NULL,
    reviewed_by BIGINT UNSIGNED NOT NULL,
    reviewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rr_revision (revision_id),
    CONSTRAINT fk_rr_revision FOREIGN KEY (revision_id) REFERENCES revisions(id),
    CONSTRAINT fk_rr_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;