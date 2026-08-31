-- Phase 1 / M1 Foundation — seed M0 permission codes
-- Seeds new M0 permission codes per 12-Role-Permission-Matrix.md §4
-- Enforces Q11: project.create restricted to ADMIN, CTO only

-- Insert new M0 permission grants per approved matrix
INSERT INTO role_permissions (role_id, permission_code)
SELECT r.id, pc.perm_code
FROM (
  SELECT 'project.structure.update' AS perm_code, 'ADMIN' AS role_code UNION ALL
  SELECT 'project.structure.update', 'CTO' UNION ALL
  SELECT 'project.progress.update', 'ADMIN' UNION ALL
  SELECT 'project.progress.update', 'CTO' UNION ALL
  SELECT 'project.progress.update', 'PMO_REVIEWER' UNION ALL
  SELECT 'milestone.close', 'ADMIN' UNION ALL
  SELECT 'milestone.close', 'CTO' UNION ALL
  SELECT 'milestone.open', 'ADMIN' UNION ALL
  SELECT 'milestone.open', 'CTO' UNION ALL
  SELECT 'revision.create', 'ADMIN' UNION ALL
  SELECT 'revision.create', 'CTO' UNION ALL
  SELECT 'revision.create', 'SENIOR_DEV' UNION ALL
  SELECT 'revision.create', 'MEMBER' UNION ALL
  SELECT 'revision.review', 'ADMIN' UNION ALL
  SELECT 'revision.review', 'CTO' UNION ALL
  SELECT 'repository.manage', 'ADMIN' UNION ALL
  SELECT 'repository.manage', 'CTO' UNION ALL
  SELECT 'repository.manage', 'SENIOR_DEV' UNION ALL
  SELECT 'repository.manage', 'MEMBER' UNION ALL
  SELECT 'ai_assignment.manage', 'ADMIN' UNION ALL
  SELECT 'ai_assignment.manage', 'CTO'
) pc
JOIN roles r ON r.code = pc.role_code
WHERE NOT EXISTS (
  SELECT 1 FROM role_permissions rp
  WHERE rp.role_id = r.id AND rp.permission_code = pc.perm_code
);

-- Q11: Ensure project.create is granted to ADMIN and CTO (per Approved Matrix)
-- Note: ADMIN already has it from migration 0012; CTO needs it added
INSERT INTO role_permissions (role_id, permission_code)
SELECT r.id, 'project.create'
FROM roles r
WHERE r.code IN ('ADMIN', 'CTO')
  AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp
    WHERE rp.role_id = r.id AND rp.permission_code = 'project.create'
  );

-- Q11: Restrict project.create to ADMIN, CTO only
-- Remove existing grants for roles that should not have project.create
DELETE FROM role_permissions
WHERE permission_code = 'project.create'
  AND role_id IN (
    SELECT id FROM roles WHERE code IN ('MEMBER', 'SENIOR_DEV', 'PMO_REVIEWER', 'VIEWER')
  );