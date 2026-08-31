-- Phase 1 / M1 Foundation — users LINE Login fields
-- Adds LINE Login fields and makes password_hash nullable for LINE-only accounts
ALTER TABLE users
    ADD COLUMN line_user_id VARCHAR(64) NULL AFTER email,
    ADD COLUMN line_display_name VARCHAR(150) NULL AFTER line_user_id,
    ADD COLUMN avatar_url VARCHAR(500) NULL AFTER line_display_name,
    ADD COLUMN auth_provider ENUM('local','line') NOT NULL DEFAULT 'local' AFTER avatar_url,
    MODIFY COLUMN password_hash VARCHAR(255) NULL,
    ADD UNIQUE KEY uq_users_line_user_id (line_user_id);