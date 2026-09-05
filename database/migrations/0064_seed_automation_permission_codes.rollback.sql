-- Rollback: automation permission codes
DELETE FROM role_permissions
WHERE permission_code IN ('automation.manage', 'automation.view');
