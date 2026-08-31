-- Phase 1 / M1 Foundation — users LINE Login fields (rollback)
ALTER TABLE users
    DROP COLUMN line_user_id,
    DROP COLUMN line_display_name,
    DROP COLUMN avatar_url,
    DROP COLUMN auth_provider,
    MODIFY COLUMN password_hash VARCHAR(255) NOT NULL;