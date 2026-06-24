-- Phase 3.5 / Pilot Preparation — JaideeDigital Workspace + PMOIS Project + Pilot Admin
-- รวม: user, workspace, workspace_member (ADMIN), project
-- ใช้ INSERT IGNORE เพื่อให้รันซ้ำได้โดยไม่ error (idempotent)

-- ── 1. Seed pilot admin user ─────────────────────────────────────────────────
-- password_hash เป็น placeholder — pilot ใช้ API Token แทน password-based login
INSERT IGNORE INTO users (name, email, password_hash, status, is_platform_admin)
VALUES (
    'JaideeDigital Admin',
    'admin@jaidee.digital',
    '$2y$12$placeholder.not.a.real.hash.pilot.uses.api.token.only',
    'active',
    TRUE
);

-- ── 2. Seed workspace JaideeDigital ─────────────────────────────────────────
INSERT IGNORE INTO workspaces (code, name, description, status, created_by)
SELECT
    'JAIDEEDIGITAL',
    'JaideeDigital',
    'Workspace หลักของ JaideeDigital — pilot โครงการ PMOIS (Topic 5)',
    'active',
    u.id
FROM users u WHERE u.email = 'admin@jaidee.digital' LIMIT 1;

-- ── 3. Seed workspace_member: admin@jaidee.digital → JaideeDigital → ADMIN ──
INSERT IGNORE INTO workspace_members (workspace_id, user_id, role_id, status)
SELECT
    w.id,
    u.id,
    r.id,
    'active'
FROM workspaces w
JOIN users u ON u.email = 'admin@jaidee.digital'
JOIN roles r ON r.code = 'ADMIN'
WHERE w.code = 'JAIDEEDIGITAL'
LIMIT 1;

-- ── 4. Seed PMOIS project ────────────────────────────────────────────────────
INSERT IGNORE INTO projects (workspace_id, code, name, description, status, owner_user_id)
SELECT
    w.id,
    'PMOIS',
    'PMOIS',
    'Project Management & Operational Intelligence System — pilot project ตาม Topic 5',
    'active',
    u.id
FROM workspaces w
JOIN users u ON u.email = 'admin@jaidee.digital'
WHERE w.code = 'JAIDEEDIGITAL'
LIMIT 1;
