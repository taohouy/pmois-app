-- Phase 4 / Project-Scoped Token Enforcement
-- Seed: MJU Asset project ใน JaideeDigital workspace
-- ใช้ INSERT IGNORE เพื่อให้รันซ้ำได้โดยไม่ error (idempotent)

INSERT IGNORE INTO projects (workspace_id, code, name, description, status, owner_user_id)
SELECT
    w.id,
    'MJU-ASSET',
    'MJU Asset',
    'Internal asset management project — first real integration with PMOIS',
    'active',
    u.id
FROM workspaces w
JOIN users u ON u.email = 'admin@jaidee.digital'
WHERE w.code = 'JAIDEEDIGITAL'
LIMIT 1;
