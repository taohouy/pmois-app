-- M5 — API Platform: notification log (Telegram เป็น provider หลักตาม CTO Constraint)
-- ทุกความพยายามส่ง (สำเร็จ/ล้มเหลว/ข้าม) ถูก log เพื่อ monitoring + retry ในอนาคต
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NULL,
    project_id BIGINT UNSIGNED NULL,
    channel ENUM('telegram') NOT NULL DEFAULT 'telegram',
    event_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent','failed','skipped') NOT NULL,
    error TEXT NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notif_workspace (workspace_id),
    KEY idx_notif_project (project_id),
    KEY idx_notif_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
