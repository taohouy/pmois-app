-- M1 R2 — PMOIS user sessions (CTO Review 1.3: authenticated PMOIS session หลัง LINE Login)
-- session token เก็บเป็น hash เท่านั้น (raw อยู่ใน HttpOnly cookie ของ browser)
CREATE TABLE user_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    session_token_hash CHAR(64) NOT NULL,
    purpose ENUM('login','claim') NOT NULL DEFAULT 'login',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    expires_at TIMESTAMP NOT NULL,
    last_used_at TIMESTAMP NULL,
    revoked_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_sessions_hash (session_token_hash),
    KEY idx_user_sessions_user (user_id),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
