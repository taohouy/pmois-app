-- M1 R2 — OAuth login state store (CTO Review: OAuth State must be persisted, one-time use)
-- state เก็บเป็น hash เท่านั้น (ไม่เก็บ raw) — claim_token เก็บ raw ไว้ที่ server เพื่อ
-- complete claim ตอน callback (client ไม่มีส่วนเกี่ยวกับการ bind line_user_id)
CREATE TABLE oauth_login_states (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    state_hash CHAR(64) NOT NULL,
    purpose ENUM('login','claim') NOT NULL DEFAULT 'login',
    nonce VARCHAR(64) NULL,
    fingerprint_hash CHAR(64) NOT NULL,
    claim_token VARCHAR(2000) NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_oauth_state_hash (state_hash),
    KEY idx_oauth_state_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
