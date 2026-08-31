-- Phase 1 / M1 Foundation — seed M0 permission codes (rollback)
-- Remove the newly inserted permission grants
DELETE FROM role_permissions
WHERE permission_code IN (
    'project.structure.update',
    'project.progress.update',
    'milestone.close',
    'milestone.open',
    'revision.create',
    'revision.review',
    'repository.manage',
    'ai_assignment.manage'
);