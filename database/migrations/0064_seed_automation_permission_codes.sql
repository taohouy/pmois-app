-- M8 — Automation permission codes
-- automation.manage: enqueue / retry / run-due (ADMIN, CTO)
-- automation.view:  queue monitoring (ADMIN, CTO, PMO_REVIEWER)
INSERT INTO role_permissions (role_id, permission_code)
SELECT r.id, pc.perm_code
FROM (
  SELECT 'automation.manage' AS perm_code, 'ADMIN' AS role_code UNION ALL
  SELECT 'automation.manage', 'CTO' UNION ALL
  SELECT 'automation.view', 'ADMIN' UNION ALL
  SELECT 'automation.view', 'CTO' UNION ALL
  SELECT 'automation.view', 'PMO_REVIEWER'
) pc
JOIN roles r ON r.code = pc.role_code
WHERE NOT EXISTS (
  SELECT 1 FROM role_permissions rp
  WHERE rp.role_id = r.id AND rp.permission_code = pc.perm_code
);
