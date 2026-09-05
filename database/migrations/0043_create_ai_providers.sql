-- M1 / R6 Phase 1.5 — AI Provider Registry (global, not workspace-scoped)
-- Providers are platform-level facts (CTO Requirement #2: Provider separated from Agent)
CREATE TABLE ai_providers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ai_providers_code (code),
    CONSTRAINT fk_aip_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed canonical providers (Human included — used as default provider for backfill)
-- created_by = platform admin ตัวแรก (ถ้าไม่มี admin ในระบบ ให้ seed ทีหลังจากสร้าง admin)
INSERT INTO ai_providers (code, name, created_by)
SELECT x.code, x.name, (SELECT MIN(u.id) FROM users u WHERE u.is_platform_admin = 1) FROM (
    SELECT 'openai' AS code, 'OpenAI' AS name
    UNION ALL SELECT 'anthropic', 'Anthropic'
    UNION ALL SELECT 'google', 'Google'
    UNION ALL SELECT 'microsoft', 'Microsoft'
    UNION ALL SELECT 'human', 'Human'
) x;
