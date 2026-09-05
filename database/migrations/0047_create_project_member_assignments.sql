-- M1 / R6 Phase 1.5 — Project Team Registry ledger (CTO Requirement #1)
-- project_members remains the live authorization projection (PermissionResolver unchanged);
-- this table keeps full assign/unassign history, never deleted.
CREATE TABLE project_member_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    assignment_source ENUM('direct','workspace_default','project_template') NOT NULL DEFAULT 'direct',
    note VARCHAR(300) NULL,
    assigned_by BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_by BIGINT UNSIGNED NULL,
    revoked_at TIMESTAMP NULL,
    KEY idx_pma_project (project_id),
    KEY idx_pma_user (user_id),
    KEY idx_pma_workspace (workspace_id),
    CONSTRAINT fk_pma_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_pma_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_pma_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_pma_role FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_pma_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id),
    CONSTRAINT fk_pma_revoked_by FOREIGN KEY (revoked_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bootstrap: one active ledger row per existing project_members row
INSERT INTO project_member_assignments
    (project_id, workspace_id, user_id, role_id, assignment_source, assigned_by, assigned_at)
SELECT pm.project_id, p.workspace_id, pm.user_id, pm.role_id, 'direct', p.owner_user_id, pm.created_at
FROM project_members pm
JOIN projects p ON p.id = pm.project_id;
