-- Rollback: revert column rename and provider link
ALTER TABLE repositories CHANGE COLUMN repository_url gitlab_url VARCHAR(500) NOT NULL;
ALTER TABLE repositories DROP FOREIGN KEY fk_repo_git_provider;
ALTER TABLE repositories DROP COLUMN git_provider_id;
