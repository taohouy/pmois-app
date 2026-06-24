-- Phase 0 / Foundation — role_permissions
-- permission_code เป็น VARCHAR เสรี ไม่มี FK ไปยัง permission master table
-- (อ้างอิงตาม Permission Code List v0.1 ที่เก็บไว้เป็นเอกสาร ไม่ใช่ schema)
CREATE TABLE role_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    permission_code VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_role_permission (role_id, permission_code),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
