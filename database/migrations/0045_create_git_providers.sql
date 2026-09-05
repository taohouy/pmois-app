-- M1 / R6 Phase 1.5 — Git Provider Registry
-- CTO Constraint: GitLab ONLY (GitHub / Azure DevOps / Bitbucket are out of scope)
CREATE TABLE git_providers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    base_url VARCHAR(500) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_git_providers_code (code),
    CONSTRAINT fk_gp_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: GitLab only (per CTO Decision — Source Control constraint)
INSERT INTO git_providers (code, name, base_url, created_by)
SELECT 'gitlab', 'GitLab', 'https://gitlab.com', (SELECT MIN(u.id) FROM users u WHERE u.is_platform_admin = 1);
