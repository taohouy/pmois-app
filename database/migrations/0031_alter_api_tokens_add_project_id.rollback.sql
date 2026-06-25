ALTER TABLE api_tokens
    DROP FOREIGN KEY fk_tokens_project,
    DROP COLUMN project_id;
