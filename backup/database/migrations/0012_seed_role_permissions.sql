-- Phase 0 / Foundation — seed role_permissions
-- อ้างอิงตาม Permission Code List v0.1 (CTO Approved)
-- หมายเหตุ: permission_code ของ module ที่ยังไม่ build จริง (governance/rfc/knowledge/ai ฯลฯ)
-- ใส่ไว้ล่วงหน้าได้เพราะเป็น VARCHAR เสรี ไม่มี FK ผูกกับ table ของ module นั้น

-- ===== ADMIN: ทุก permission (ยกเว้น workspace.create ที่ผูกกับ is_platform_admin โดยตรง ไม่ผ่าน role) =====
INSERT INTO role_permissions (role_id, permission_code)
SELECT r.id, p.code FROM roles r
JOIN (
    SELECT 'workspace.view' AS code UNION ALL SELECT 'workspace.update'
    UNION ALL SELECT 'workspace_member.invite' UNION ALL SELECT 'workspace_member.remove'
    UNION ALL SELECT 'workspace_member.update_role' UNION ALL SELECT 'workspace_module_setting.manage'
    UNION ALL SELECT 'role.manage'
    UNION ALL SELECT 'project.view' UNION ALL SELECT 'project.create' UNION ALL SELECT 'project.update'
    UNION ALL SELECT 'project.close' UNION ALL SELECT 'project.delete' UNION ALL SELECT 'project_member.manage'
    UNION ALL SELECT 'project_status_update.view' UNION ALL SELECT 'project_status_update.create'
    UNION ALL SELECT 'project_status_update.update'
    UNION ALL SELECT 'governance_record.view' UNION ALL SELECT 'governance_record.create'
    UNION ALL SELECT 'governance_record.update' UNION ALL SELECT 'governance_record.archive'
    UNION ALL SELECT 'governance_version.view' UNION ALL SELECT 'governance_version.create'
    UNION ALL SELECT 'governance_version.publish' UNION ALL SELECT 'governance_version_item.manage'
    UNION ALL SELECT 'governance_adoption.view' UNION ALL SELECT 'governance_adoption.create'
    UNION ALL SELECT 'governance_adoption_item.update' UNION ALL SELECT 'governance_adoption.retire'
    UNION ALL SELECT 'rfc.view' UNION ALL SELECT 'rfc.create' UNION ALL SELECT 'rfc.update'
    UNION ALL SELECT 'rfc.submit' UNION ALL SELECT 'rfc.review' UNION ALL SELECT 'rfc.convert_to_decision'
    UNION ALL SELECT 'rfc.comment'
    UNION ALL SELECT 'decision_register.view' UNION ALL SELECT 'decision_register.create'
    UNION ALL SELECT 'decision_register.update' UNION ALL SELECT 'decision_register.approve'
    UNION ALL SELECT 'knowledge_article.view' UNION ALL SELECT 'knowledge_article.create'
    UNION ALL SELECT 'knowledge_article.update' UNION ALL SELECT 'knowledge_article.publish'
    UNION ALL SELECT 'knowledge_article.archive' UNION ALL SELECT 'attachment.upload'
    UNION ALL SELECT 'attachment.delete' UNION ALL SELECT 'knowledge_link.manage'
    UNION ALL SELECT 'ai_consumer.view' UNION ALL SELECT 'ai_consumer.manage'
    UNION ALL SELECT 'ai_context.export' UNION ALL SELECT 'ai_context_export_log.view'
    UNION ALL SELECT 'api_token.create' UNION ALL SELECT 'api_token.revoke' UNION ALL SELECT 'api_token.view'
    UNION ALL SELECT 'audit_trail.view' UNION ALL SELECT 'governance_timeline.view' UNION ALL SELECT 'dashboard.view'
    UNION ALL SELECT 'task.view' UNION ALL SELECT 'task.create' UNION ALL SELECT 'task.update' UNION ALL SELECT 'task.delete'
    UNION ALL SELECT 'milestone.view' UNION ALL SELECT 'milestone.create' UNION ALL SELECT 'milestone.update'
    UNION ALL SELECT 'risk_issue.view' UNION ALL SELECT 'risk_issue.create' UNION ALL SELECT 'risk_issue.update'
    UNION ALL SELECT 'risk_issue_update.create'
) p
WHERE r.code = 'ADMIN';

-- ===== VIEWER: เฉพาะ *.view ทั้งหมด =====
INSERT INTO role_permissions (role_id, permission_code)
SELECT r.id, p.code FROM roles r
JOIN (
    SELECT 'workspace.view' AS code UNION ALL SELECT 'project.view'
    UNION ALL SELECT 'project_status_update.view' UNION ALL SELECT 'governance_record.view'
    UNION ALL SELECT 'governance_version.view' UNION ALL SELECT 'governance_adoption.view'
    UNION ALL SELECT 'rfc.view' UNION ALL SELECT 'decision_register.view'
    UNION ALL SELECT 'knowledge_article.view' UNION ALL SELECT 'ai_consumer.view'
    UNION ALL SELECT 'ai_context_export_log.view' UNION ALL SELECT 'api_token.view'
    UNION ALL SELECT 'audit_trail.view' UNION ALL SELECT 'governance_timeline.view' UNION ALL SELECT 'dashboard.view'
    UNION ALL SELECT 'task.view' UNION ALL SELECT 'milestone.view' UNION ALL SELECT 'risk_issue.view'
) p
WHERE r.code = 'VIEWER';

-- ===== MEMBER: view ทั้งหมด + create/update เนื้อหาของตัวเอง — ไม่มี review/approve/publish =====
INSERT INTO role_permissions (role_id, permission_code)
SELECT r.id, p.code FROM roles r
JOIN (
    SELECT 'workspace.view' AS code
    UNION ALL SELECT 'project.view' UNION ALL SELECT 'project.create' UNION ALL SELECT 'project.update'
    UNION ALL SELECT 'project_status_update.view' UNION ALL SELECT 'project_status_update.create'
    UNION ALL SELECT 'project_status_update.update'
    UNION ALL SELECT 'governance_record.view' UNION ALL SELECT 'governance_version.view'
    UNION ALL SELECT 'governance_adoption.view'
    UNION ALL SELECT 'rfc.view' UNION ALL SELECT 'rfc.create' UNION ALL SELECT 'rfc.update' UNION ALL SELECT 'rfc.comment'
    UNION ALL SELECT 'decision_register.view'
    UNION ALL SELECT 'knowledge_article.view' UNION ALL SELECT 'knowledge_article.create'
    UNION ALL SELECT 'knowledge_article.update' UNION ALL SELECT 'attachment.upload'
    UNION ALL SELECT 'task.view' UNION ALL SELECT 'task.create' UNION ALL SELECT 'task.update'
    UNION ALL SELECT 'milestone.view' UNION ALL SELECT 'milestone.create' UNION ALL SELECT 'milestone.update'
    UNION ALL SELECT 'risk_issue.view' UNION ALL SELECT 'risk_issue.create' UNION ALL SELECT 'risk_issue.update'
    UNION ALL SELECT 'risk_issue_update.create' UNION ALL SELECT 'dashboard.view'
) p
WHERE r.code = 'MEMBER';

-- ===== PMO_REVIEWER: view ทั้งหมด + สิทธิ์ตรวจ/อนุมัติ =====
INSERT INTO role_permissions (role_id, permission_code)
SELECT r.id, p.code FROM roles r
JOIN (
    SELECT 'workspace.view' AS code UNION ALL SELECT 'project.view'
    UNION ALL SELECT 'project_status_update.view' UNION ALL SELECT 'governance_record.view'
    UNION ALL SELECT 'governance_version.view' UNION ALL SELECT 'governance_adoption.view'
    UNION ALL SELECT 'governance_adoption_item.update'
    UNION ALL SELECT 'rfc.view' UNION ALL SELECT 'rfc.review' UNION ALL SELECT 'rfc.comment'
    UNION ALL SELECT 'decision_register.view' UNION ALL SELECT 'decision_register.approve'
    UNION ALL SELECT 'knowledge_article.view' UNION ALL SELECT 'audit_trail.view'
    UNION ALL SELECT 'governance_timeline.view' UNION ALL SELECT 'dashboard.view'
) p
WHERE r.code = 'PMO_REVIEWER';

-- ===== CTO: เน้น Governance/RFC/Decision เต็มสิทธิ์ + view ทั่วไป =====
INSERT INTO role_permissions (role_id, permission_code)
SELECT r.id, p.code FROM roles r
JOIN (
    SELECT 'workspace.view' AS code UNION ALL SELECT 'project.view'
    UNION ALL SELECT 'project_status_update.view'
    UNION ALL SELECT 'governance_record.view' UNION ALL SELECT 'governance_record.create'
    UNION ALL SELECT 'governance_record.update' UNION ALL SELECT 'governance_record.archive'
    UNION ALL SELECT 'governance_version.view' UNION ALL SELECT 'governance_version.create'
    UNION ALL SELECT 'governance_version.publish' UNION ALL SELECT 'governance_version_item.manage'
    UNION ALL SELECT 'governance_adoption.view' UNION ALL SELECT 'governance_adoption.create'
    UNION ALL SELECT 'governance_adoption_item.update' UNION ALL SELECT 'governance_adoption.retire'
    UNION ALL SELECT 'rfc.view' UNION ALL SELECT 'rfc.review' UNION ALL SELECT 'rfc.convert_to_decision'
    UNION ALL SELECT 'rfc.comment'
    UNION ALL SELECT 'decision_register.view' UNION ALL SELECT 'decision_register.create'
    UNION ALL SELECT 'decision_register.update' UNION ALL SELECT 'decision_register.approve'
    UNION ALL SELECT 'ai_consumer.view' UNION ALL SELECT 'ai_consumer.manage'
    UNION ALL SELECT 'ai_context.export' UNION ALL SELECT 'ai_context_export_log.view'
    UNION ALL SELECT 'api_token.create' UNION ALL SELECT 'api_token.revoke' UNION ALL SELECT 'api_token.view'
    UNION ALL SELECT 'audit_trail.view' UNION ALL SELECT 'governance_timeline.view' UNION ALL SELECT 'dashboard.view'
) p
WHERE r.code = 'CTO';

-- ===== SENIOR_DEV: view ทั่วไป + สร้าง/แก้ไขงานเทคนิค — ไม่มี review/approve =====
INSERT INTO role_permissions (role_id, permission_code)
SELECT r.id, p.code FROM roles r
JOIN (
    SELECT 'workspace.view' AS code UNION ALL SELECT 'project.view'
    UNION ALL SELECT 'project_status_update.view' UNION ALL SELECT 'project_status_update.create'
    UNION ALL SELECT 'governance_record.view' UNION ALL SELECT 'governance_version.view'
    UNION ALL SELECT 'governance_adoption.view'
    UNION ALL SELECT 'rfc.view' UNION ALL SELECT 'rfc.create' UNION ALL SELECT 'rfc.update' UNION ALL SELECT 'rfc.comment'
    UNION ALL SELECT 'decision_register.view'
    UNION ALL SELECT 'knowledge_article.view' UNION ALL SELECT 'knowledge_article.create'
    UNION ALL SELECT 'knowledge_article.update' UNION ALL SELECT 'attachment.upload'
    UNION ALL SELECT 'dashboard.view'
) p
WHERE r.code = 'SENIOR_DEV';
