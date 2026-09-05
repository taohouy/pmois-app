-- M1 / R6 Phase 1.5 — permission codes for R6 registries
-- Read access uses existing project.view / ai_consumer.view patterns — no separate .view codes.
-- ai_provider.manage / git_provider.manage are is_platform_admin-only in the service layer
-- (same rule as workspace.create) — no role_permissions rows for them.
INSERT INTO role_permissions (role_id, permission_code)
SELECT r.id, pc.perm_code
FROM (
  SELECT 'project.team.manage' AS perm_code, 'ADMIN' AS role_code UNION ALL
  SELECT 'project.team.manage', 'CTO' UNION ALL
  SELECT 'project.dependency.manage', 'ADMIN' UNION ALL
  SELECT 'project.dependency.manage', 'CTO' UNION ALL
  SELECT 'project.dependency.manage', 'SENIOR_DEV' UNION ALL
  SELECT 'project.release.manage', 'ADMIN' UNION ALL
  SELECT 'project.release.manage', 'CTO' UNION ALL
  SELECT 'project.release.manage', 'SENIOR_DEV' UNION ALL
  SELECT 'project.environment.manage', 'ADMIN' UNION ALL
  SELECT 'project.environment.manage', 'CTO' UNION ALL
  SELECT 'project.environment.manage', 'SENIOR_DEV' UNION ALL
  SELECT 'project.techstack.manage', 'ADMIN' UNION ALL
  SELECT 'project.techstack.manage', 'CTO' UNION ALL
  SELECT 'project.techstack.manage', 'SENIOR_DEV' UNION ALL
  SELECT 'project.techstack.manage', 'MEMBER' UNION ALL
  SELECT 'project.template.manage', 'ADMIN' UNION ALL
  SELECT 'workspace.settings.manage', 'ADMIN'
) pc
JOIN roles r ON r.code = pc.role_code
WHERE NOT EXISTS (
  SELECT 1 FROM role_permissions rp
  WHERE rp.role_id = r.id AND rp.permission_code = pc.perm_code
);
