-- M1 / R6 Phase 1.5 — provider-agnostic repositories (CTO Requirement #4)
ALTER TABLE repositories
    ADD COLUMN git_provider_id BIGINT UNSIGNED NULL AFTER workspace_id,
    ADD CONSTRAINT fk_repo_git_provider FOREIGN KEY (git_provider_id) REFERENCES git_providers(id);

-- Backfill existing rows to GitLab (only provider seeded)
UPDATE repositories r
JOIN git_providers g ON g.code = 'gitlab'
SET r.git_provider_id = g.id
WHERE r.git_provider_id IS NULL;

-- Rename gitlab_url -> repository_url (index uq_repo_url follows the column automatically)
ALTER TABLE repositories CHANGE COLUMN gitlab_url repository_url VARCHAR(500) NOT NULL;
