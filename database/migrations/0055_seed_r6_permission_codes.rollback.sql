-- Rollback: remove R6 permission codes
DELETE FROM role_permissions
WHERE permission_code IN (
  'project.team.manage',
  'project.dependency.manage',
  'project.release.manage',
  'project.environment.manage',
  'project.techstack.manage',
  'project.template.manage',
  'workspace.settings.manage'
);
