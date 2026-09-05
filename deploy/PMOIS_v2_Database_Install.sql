-- =====================================================================
-- PMOIS v2 — Consolidated Database Installer (M9)
-- Generated: 2026-09-06  |  Baseline: M0 R6 Design Freeze + M1-M8
-- Target: MySQL 5.7+ / 8.0 (utf8mb4)
--
-- วิธีใช้ (ห้ามรัน migration ทีละไฟล์ — ไฟล์นี้คือตัวเดียวที่ต้องรัน):
--   1. CREATE DATABASE pmois CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   2. USE pmois;
--   3. source PMOIS_v2_Database_Install.sql
--   4. php bin/create-admin.php <email> [name]     (ถ้าต้องการ admin เพิ่ม / คนแรกถูกสร้างแล้ว)
--   5. php bin/admin-claim-url.php admin@pmois.local --base-url=https://...  (ผูก LINE)
--
-- รวม migrations 0001-0064 + seed data (roles, permissions, AI/Git providers)
-- ยกเว้น workspace-specific seeds (0029, 0032) — ข้อมูลเฉพาะ pilot เดิม
-- Rollback: PMOIS_v2_Database_Rollback.sql
-- =====================================================================


-- ==================== 0001_create_users.sql ====================
-- Phase 0 / Foundation — users
-- รวม is_platform_admin ตาม CTO Decision (Phase 0 Planning v0.1, หมวด 2.2)
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    is_platform_admin BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0002_create_workspaces.sql ====================
-- Phase 0 / Foundation — workspaces (ขอบเขตองค์กรสูงสุด)
CREATE TABLE workspaces (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_workspaces_code (code),
    CONSTRAINT fk_workspaces_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0003_create_roles.sql ====================
-- Phase 0 / Foundation — roles (global, ไม่ผูก workspace)
CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0004_create_role_permissions.sql ====================
-- Phase 0 / Foundation — role_permissions
-- permission_code เป็น VARCHAR เสรี ไม่มี FK ไปยัง permission master table
-- (อ้างอิงตาม Permission Code List v0.1 ที่เก็บไว้เป็นเอกสาร ไม่ใช่ schema)
CREATE TABLE role_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    permission_code VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_role_permission (role_id, permission_code),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0005_create_workspace_members.sql ====================
-- Phase 0 / Foundation — workspace_members (User <-> Workspace + default role)
CREATE TABLE workspace_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active','removed') NOT NULL DEFAULT 'active',
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_workspace_user (workspace_id, user_id),
    CONSTRAINT fk_wm_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_wm_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_wm_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0006_create_projects.sql ====================
-- Phase 0 / Foundation — projects
CREATE TABLE projects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    status ENUM('planning','active','on_hold','closed') NOT NULL DEFAULT 'planning',
    start_date DATE NULL,
    end_date DATE NULL,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_workspace_project_code (workspace_id, code),
    CONSTRAINT fk_projects_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_projects_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0007_create_project_members.sql ====================
-- Phase 0 / Foundation — project_members (role override เฉพาะ project)
CREATE TABLE project_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_user (project_id, user_id),
    CONSTRAINT fk_pm_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_pm_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_pm_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0008_create_workspace_module_settings.sql ====================
-- Phase 0 / Foundation — workspace_module_settings (สวิตช์เปิด/ปิด optional module)
CREATE TABLE workspace_module_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    module_code ENUM('task_management','milestone_tracking','risk_issue_tracking') NOT NULL,
    is_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    enabled_by BIGINT UNSIGNED NULL,
    enabled_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_workspace_module (workspace_id, module_code),
    CONSTRAINT fk_wms_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_wms_enabled_by FOREIGN KEY (enabled_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0009_create_api_tokens.sql ====================
-- Phase 0 / Foundation — api_tokens
-- หมายเหตุ: ai_consumer_id ยังไม่เพิ่มในรอบนี้ (เพิ่มทีหลังด้วย ALTER TABLE ใน Phase 4)
CREATE TABLE api_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    token_name VARCHAR(100) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    scopes VARCHAR(500) NULL,
    status ENUM('active','revoked') NOT NULL DEFAULT 'active',
    expires_at TIMESTAMP NULL,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_api_tokens_hash (token_hash),
    CONSTRAINT fk_tokens_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_tokens_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0010_create_audit_trails.sql ====================
-- Phase 0 / Foundation — audit_trails (immutable, ไม่มี updated_at โดยตั้งใจ)
CREATE TABLE audit_trails (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id BIGINT UNSIGNED NULL,
    before_value JSON NULL,
    after_value JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_workspace (workspace_id),
    KEY idx_audit_entity (entity_type, entity_id),
    CONSTRAINT fk_audit_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0011_seed_default_roles.sql ====================
-- Phase 0 / Foundation — seed default roles
-- ตัวอย่าง role เริ่มต้น (เป็น data ปรับแก้ได้ทีหลังโดยไม่กระทบ schema)
INSERT INTO roles (code, name, description) VALUES
('ADMIN',        'Admin',        'สิทธิ์เต็มในระดับ workspace/project'),
('MEMBER',       'Member',       'สิทธิ์ทำงานพื้นฐาน สร้าง/แก้ไขเนื้อหาของตัวเอง'),
('VIEWER',       'Viewer',       'สิทธิ์อ่านอย่างเดียว ไม่มี create/update/approve'),
('CTO',          'CTO',          'สิทธิ์ระดับสูง เน้น Governance/RFC/Decision'),
('SENIOR_DEV',   'Senior Dev',   'สิทธิ์งานเทคนิค ไม่มีสิทธิ์ review/approve'),
('PMO_REVIEWER', 'PMO Reviewer', 'สิทธิ์ตรวจ/อนุมัติ ไม่ใช่ผู้สร้างเนื้อหา');


-- ==================== 0012_seed_role_permissions.sql ====================
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


-- ==================== 0013_create_governance_records.sql ====================
CREATE TABLE governance_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    category ENUM('policy','standard','framework','guideline') NOT NULL,
    description TEXT NULL,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active','deprecated','draft') NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gov_record_code (workspace_id, code),
    CONSTRAINT fk_govrec_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_govrec_owner FOREIGN KEY (owner_user_id) REFERENCES users(id),
    CONSTRAINT fk_govrec_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0014_create_governance_versions.sql ====================
CREATE TABLE governance_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    governance_record_id BIGINT UNSIGNED NOT NULL,
    version_label VARCHAR(20) NOT NULL,
    content LONGTEXT NOT NULL,
    status ENUM('draft','published','superseded') NOT NULL DEFAULT 'draft',
    effective_date DATE NULL,
    published_by BIGINT UNSIGNED NULL,
    published_at TIMESTAMP NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gov_version_label (governance_record_id, version_label),
    KEY idx_gov_version_record_status (governance_record_id, status),
    CONSTRAINT fk_govver_record FOREIGN KEY (governance_record_id) REFERENCES governance_records(id),
    CONSTRAINT fk_govver_published_by FOREIGN KEY (published_by) REFERENCES users(id),
    CONSTRAINT fk_govver_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0015_create_governance_version_items.sql ====================
CREATE TABLE governance_version_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    governance_version_id BIGINT UNSIGNED NOT NULL,
    item_code VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    sequence_order INT NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gov_version_item_code (governance_version_id, item_code),
    CONSTRAINT fk_govveritem_version FOREIGN KEY (governance_version_id) REFERENCES governance_versions(id),
    CONSTRAINT fk_govveritem_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0016_create_decision_registers.sql ====================
-- ต้องสร้างก่อน rfcs เพราะ rfcs.resulting_decision_id ชี้มาที่นี่
CREATE TABLE decision_registers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    related_governance_record_id BIGINT UNSIGNED NULL,
    category ENUM('technical','architecture','process','governance','vendor','budget','scope','organizational','other') NOT NULL,
    title VARCHAR(200) NOT NULL,
    context TEXT NULL,
    decision_description TEXT NOT NULL,
    decision_date DATE NOT NULL,
    decided_by BIGINT UNSIGNED NOT NULL,
    status ENUM('proposed','approved','rejected','superseded') NOT NULL DEFAULT 'proposed',
    impact_level ENUM('low','medium','high') NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_decision_workspace (workspace_id),
    KEY idx_decision_project (project_id),
    CONSTRAINT fk_decision_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_decision_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_decision_govrecord FOREIGN KEY (related_governance_record_id) REFERENCES governance_records(id),
    CONSTRAINT fk_decision_decided_by FOREIGN KEY (decided_by) REFERENCES users(id),
    CONSTRAINT fk_decision_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0017_create_rfcs.sql ====================
CREATE TABLE rfcs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    related_governance_record_id BIGINT UNSIGNED NULL,
    code VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('draft','under_review','approved','rejected','converted_to_decision') NOT NULL DEFAULT 'draft',
    submitted_at TIMESTAMP NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL,
    review_note TEXT NULL,
    resulting_decision_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rfc_code (workspace_id, code),
    KEY idx_rfc_status (workspace_id, status),
    CONSTRAINT fk_rfc_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_rfc_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_rfc_govrecord FOREIGN KEY (related_governance_record_id) REFERENCES governance_records(id),
    CONSTRAINT fk_rfc_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id),
    CONSTRAINT fk_rfc_decision FOREIGN KEY (resulting_decision_id) REFERENCES decision_registers(id),
    CONSTRAINT fk_rfc_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0018_create_rfc_comments.sql ====================
CREATE TABLE rfc_comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rfc_id BIGINT UNSIGNED NOT NULL,
    comment_text TEXT NOT NULL,
    commented_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_rfc_comments_rfc (rfc_id),
    CONSTRAINT fk_rfccomment_rfc FOREIGN KEY (rfc_id) REFERENCES rfcs(id),
    CONSTRAINT fk_rfccomment_user FOREIGN KEY (commented_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0019_create_governance_adoptions.sql ====================
CREATE TABLE governance_adoptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    governance_version_id BIGINT UNSIGNED NOT NULL,
    adoption_status ENUM('in_progress','compliant','non_compliant','retired') NOT NULL DEFAULT 'in_progress',
    status ENUM('active','superseded') NOT NULL DEFAULT 'active',
    adopted_date DATE NOT NULL,
    compliance_note TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_adoption_project (project_id),
    KEY idx_adoption_version (governance_version_id),
    CONSTRAINT fk_adoption_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_adoption_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_adoption_version FOREIGN KEY (governance_version_id) REFERENCES governance_versions(id),
    CONSTRAINT fk_adoption_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id),
    CONSTRAINT fk_adoption_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0020_create_governance_adoption_items.sql ====================
CREATE TABLE governance_adoption_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    governance_adoption_id BIGINT UNSIGNED NOT NULL,
    governance_version_item_id BIGINT UNSIGNED NOT NULL,
    compliance_status ENUM('compliant','in_progress','non_compliant','na') NOT NULL DEFAULT 'in_progress',
    evidence_note TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_adoption_item (governance_adoption_id, governance_version_item_id),
    CONSTRAINT fk_adoptionitem_adoption FOREIGN KEY (governance_adoption_id) REFERENCES governance_adoptions(id),
    CONSTRAINT fk_adoptionitem_versionitem FOREIGN KEY (governance_version_item_id) REFERENCES governance_version_items(id),
    CONSTRAINT fk_adoptionitem_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id),
    CONSTRAINT fk_adoptionitem_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0021_create_knowledge_articles.sql ====================
CREATE TABLE knowledge_articles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(100) NULL,
    content LONGTEXT NOT NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    authored_by BIGINT UNSIGNED NOT NULL,
    published_at TIMESTAMP NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ka_workspace (workspace_id),
    KEY idx_ka_project (project_id),
    CONSTRAINT fk_ka_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_ka_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_ka_authored_by FOREIGN KEY (authored_by) REFERENCES users(id),
    CONSTRAINT fk_ka_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0022_create_attachments.sql ====================
CREATE TABLE attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    storage_type ENUM('local','s3') NOT NULL DEFAULT 'local',
    checksum VARCHAR(64) NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_attachments_workspace (workspace_id),
    CONSTRAINT fk_attach_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_attach_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0023_create_knowledge_links.sql ====================
CREATE TABLE knowledge_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    linked_type VARCHAR(50) NOT NULL,
    linked_id BIGINT UNSIGNED NULL,
    external_url VARCHAR(500) NULL,
    link_label VARCHAR(150) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kl_entity (entity_type, entity_id),
    KEY idx_kl_linked (linked_type, linked_id),
    KEY idx_kl_workspace (workspace_id),
    CONSTRAINT fk_kl_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_kl_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0024_add_fulltext_index_knowledge_articles.sql ====================
-- แก้ไข (พบจากการทดสอบจริงบน server): เดิมไฟล์นี้ตั้งใจสร้าง FULLTEXT INDEX WITH PARSER ngram
-- แต่ server จริงรัน MariaDB ซึ่งไม่มี ngram parser plugin เลย (error #1128 Function
-- 'ngram' is not defined) -- เปลี่ยนมาใช้ SQL LIKE-based search แทนที่ระดับ Repository
-- (ดู MySqlKnowledgeArticleRepository::search()) จึงไม่จำเป็นต้องมี FULLTEXT INDEX ใดๆ
--
-- ไฟล์นี้เปลี่ยนเป็น no-op เพื่อให้ลำดับเลข migration ต่อเนื่องเหมือนเดิม
--
-- ⚠️ แก้รอบที่ 2: เดิมใช้ "SELECT 1;" เป็น no-op แต่ผิด -- SELECT คืน result set
-- กลับมา ทำให้ cursor ค้างเปิดบน connection พอ migrate.php รัน query ถัดไป (INSERT
-- ลง schema_migrations) บน connection เดียวกัน MySQL/MariaDB จึง error
-- "2014 Cannot execute queries while other unbuffered queries are active"
-- เปลี่ยนมาใช้ "DO 0;" แทน เพราะ DO statement ออกแบบมาสำหรับ no-op โดยเฉพาะ
-- (ประเมินค่า expression แต่ไม่คืน result set กลับมาเลย)
DO 0;


-- ==================== 0025_create_ai_consumers.sql ====================
CREATE TABLE ai_consumers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ai_consumer_code (workspace_id, code),
    CONSTRAINT fk_aic_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_aic_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0026_alter_api_tokens_add_ai_consumer_id.sql ====================
ALTER TABLE api_tokens
    ADD COLUMN ai_consumer_id BIGINT UNSIGNED NULL AFTER created_by_user_id;

ALTER TABLE api_tokens
    ADD CONSTRAINT fk_tokens_ai_consumer FOREIGN KEY (ai_consumer_id) REFERENCES ai_consumers(id);


-- ==================== 0027_create_ai_context_exports.sql ====================
-- รวม ai_consumer_id denormalized ตั้งแต่ migration แรก (CTO Decision)
CREATE TABLE ai_context_exports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    api_token_id BIGINT UNSIGNED NOT NULL,
    ai_consumer_id BIGINT UNSIGNED NULL, -- NULL = Human Export, NOT NULL = AI Export (CTO Decision Option B)
    export_type ENUM('project_status','governance_summary','decision_snapshot','full_workspace_context') NOT NULL,
    scope_entity_type VARCHAR(50) NULL,
    scope_entity_id BIGINT UNSIGNED NULL,
    payload_snapshot JSON NOT NULL,
    exported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ace_workspace (workspace_id),
    KEY idx_ace_consumer (ai_consumer_id),
    CONSTRAINT fk_ace_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_ace_token FOREIGN KEY (api_token_id) REFERENCES api_tokens(id),
    CONSTRAINT fk_ace_consumer FOREIGN KEY (ai_consumer_id) REFERENCES ai_consumers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0028_create_project_status_updates.sql ====================
-- ⚠️ Gap ที่พบระหว่าง Phase 3 implementation: ตารางนี้ระบุไว้ใน Foundation Design
-- ตั้งแต่ System Design v0.1 (Phase 0 scope) แต่ไม่เคยถูกสร้างจริงตอน migration 0001-0012
-- เพิ่มเข้ามาตอนนี้เพราะ endpoint /projects/status ของ AI Context Platform ต้องใช้ตรงๆ
CREATE TABLE project_status_updates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    report_date DATE NOT NULL,
    overall_status ENUM('on_track','at_risk','off_track') NOT NULL,
    summary TEXT NOT NULL,
    key_achievements TEXT NULL,
    key_issues TEXT NULL,
    next_steps TEXT NULL,
    submitted_by BIGINT UNSIGNED NOT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_psu_project (project_id),
    KEY idx_psu_workspace (workspace_id),
    CONSTRAINT fk_psu_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_psu_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_psu_submitted_by FOREIGN KEY (submitted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0030_alter_project_status_updates_add_idempotency_key.sql ====================
-- Inbound Status API — add idempotency_key to project_status_updates
ALTER TABLE project_status_updates
    ADD COLUMN idempotency_key VARCHAR(100) NULL AFTER next_steps,
    ADD UNIQUE KEY uq_psu_idempotency_key (idempotency_key);


-- ==================== 0031_alter_api_tokens_add_project_id.sql ====================
-- Phase 4 / Project-Scoped Token Enforcement
-- เพิ่ม project_id (nullable) ใน api_tokens
-- NULL  = workspace-level token (ADMIN / พฤติกรรมเดิมที่ยังใช้อยู่)
-- ≠NULL = project-scoped token (เข้าได้เฉพาะ project นั้น)
ALTER TABLE api_tokens
    ADD COLUMN project_id BIGINT UNSIGNED NULL DEFAULT NULL
        AFTER workspace_id,
    ADD CONSTRAINT fk_tokens_project
        FOREIGN KEY (project_id) REFERENCES projects(id);


-- ==================== 0033_alter_users_add_line_fields.sql ====================
-- Phase 1 / M1 Foundation — users LINE Login fields
-- Adds LINE Login fields and makes password_hash nullable for LINE-only accounts
ALTER TABLE users
    ADD COLUMN line_user_id VARCHAR(64) NULL AFTER email,
    ADD COLUMN line_display_name VARCHAR(150) NULL AFTER line_user_id,
    ADD COLUMN avatar_url VARCHAR(500) NULL AFTER line_display_name,
    ADD COLUMN auth_provider ENUM('local','line') NOT NULL DEFAULT 'local' AFTER avatar_url,
    MODIFY COLUMN password_hash VARCHAR(255) NULL,
    ADD UNIQUE KEY uq_users_line_user_id (line_user_id);

-- ==================== 0034_alter_projects_add_hierarchy_profile.sql ====================
-- Phase 1 / M1 Foundation — projects hierarchy and profile fields
-- Adds hierarchy (parent_project_id), profile fields, and M1 profile fields
ALTER TABLE projects
    ADD COLUMN parent_project_id BIGINT UNSIGNED NULL AFTER workspace_id,
    ADD COLUMN abbreviation VARCHAR(20) NULL AFTER code,
    ADD COLUMN development_mode ENUM('manual','ai_assisted','ai_dev_auto') NOT NULL DEFAULT 'manual' AFTER status,
    ADD COLUMN current_milestone_id BIGINT UNSIGNED NULL AFTER development_mode,
    ADD COLUMN progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER current_milestone_id,
    ADD COLUMN health ENUM('green','yellow','red') NOT NULL DEFAULT 'green' AFTER progress_percent,
    ADD COLUMN profile_completeness_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER health,
    ADD COLUMN archived_at TIMESTAMP NULL AFTER end_date,
    ADD CONSTRAINT fk_projects_parent FOREIGN KEY (parent_project_id) REFERENCES projects(id);

-- ==================== 0035_create_project_structure_history.sql ====================
-- Phase 1 / M1 Foundation — project structure history
-- Tracks all hierarchy changes: move workspace, change parent, promote, demote
CREATE TABLE project_structure_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    change_type ENUM('move_workspace','change_parent','promote_to_workspace','demote_to_child') NOT NULL,
    from_workspace_id BIGINT UNSIGNED NULL,
    to_workspace_id BIGINT UNSIGNED NULL,
    from_parent_project_id BIGINT UNSIGNED NULL,
    to_parent_project_id BIGINT UNSIGNED NULL,
    reason TEXT NULL,
    changed_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_psh_project (project_id),
    KEY idx_psh_created (created_at),
    CONSTRAINT fk_psh_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_psh_from_ws FOREIGN KEY (from_workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_psh_to_ws FOREIGN KEY (to_workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_psh_from_parent FOREIGN KEY (from_parent_project_id) REFERENCES projects(id),
    CONSTRAINT fk_psh_to_parent FOREIGN KEY (to_parent_project_id) REFERENCES projects(id),
    CONSTRAINT fk_psh_changed_by FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== 0036_create_milestones.sql ====================
-- Phase 1 / M1 Foundation — milestones
-- Milestones for tracking project progress and CTO review gates
CREATE TABLE milestones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    planned_date DATE NULL,
    closed_by BIGINT UNSIGNED NULL,
    closed_at TIMESTAMP NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_milestone_code (project_id, code),
    KEY idx_milestone_project (project_id),
    KEY idx_milestone_workspace (workspace_id),
    KEY idx_milestone_status (status),
    CONSTRAINT fk_milestone_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_milestone_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_milestone_closed_by FOREIGN KEY (closed_by) REFERENCES users(id),
    CONSTRAINT fk_milestone_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== 0037_create_repositories.sql ====================
-- Phase 1 / M1 Foundation — repositories
-- GitLab repository registry (manual registration, read-only sync)
CREATE TABLE repositories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    repository_type ENUM('main','supporting','docs','test','infra','custom') NOT NULL DEFAULT 'main',
    repository_name VARCHAR(200) NOT NULL,
    gitlab_url VARCHAR(500) NOT NULL,
    default_branch VARCHAR(100) NOT NULL DEFAULT 'main',
    development_branch VARCHAR(100) NULL,
    release_branch VARCHAR(100) NULL,
    production_branch VARCHAR(100) NULL,
    repository_status ENUM('active','archived') NOT NULL DEFAULT 'active',
    credential_reference VARCHAR(200) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_repo_url (gitlab_url),
    KEY idx_repo_project (project_id),
    KEY idx_repo_workspace (workspace_id),
    CONSTRAINT fk_repo_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_repo_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_repo_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== 0038_create_revisions.sql ====================
-- Phase 1 / M1 Foundation — revisions
-- Tracks Dev revisions submitted for CTO review
CREATE TABLE revisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    milestone_id BIGINT UNSIGNED NULL,
    repository_id BIGINT UNSIGNED NULL,
    status ENUM('submitted','cto_approved','cto_rejected','committed') NOT NULL DEFAULT 'submitted',
    summary TEXT NOT NULL,
    test_result ENUM('passed','failed','skipped','pending') NOT NULL DEFAULT 'pending',
    branch VARCHAR(150) NULL,
    commit_hash VARCHAR(64) NULL,
    push_status ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
    known_issue TEXT NULL,
    next_action TEXT NULL,
    dev_user_id BIGINT UNSIGNED NULL,
    dev_ai_consumer_id BIGINT UNSIGNED NULL,
    submitted_by BIGINT UNSIGNED NOT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    committed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_rev_project (project_id),
    KEY idx_rev_workspace (workspace_id),
    KEY idx_rev_milestone (milestone_id),
    KEY idx_rev_status (status),
    CONSTRAINT fk_rev_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_rev_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_rev_milestone FOREIGN KEY (milestone_id) REFERENCES milestones(id),
    CONSTRAINT fk_rev_repository FOREIGN KEY (repository_id) REFERENCES repositories(id),
    CONSTRAINT fk_rev_dev_user FOREIGN KEY (dev_user_id) REFERENCES users(id),
    CONSTRAINT fk_rev_dev_ai FOREIGN KEY (dev_ai_consumer_id) REFERENCES ai_consumers(id),
    CONSTRAINT fk_rev_submitted_by FOREIGN KEY (submitted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== 0039_create_revision_reviews.sql ====================
-- Phase 1 / M1 Foundation — revision reviews
-- Tracks CTO review decisions on revisions
CREATE TABLE revision_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revision_id BIGINT UNSIGNED NOT NULL,
    decision ENUM('approved','rejected') NOT NULL,
    review_note TEXT NULL,
    reviewed_by BIGINT UNSIGNED NOT NULL,
    reviewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rr_revision (revision_id),
    CONSTRAINT fk_rr_revision FOREIGN KEY (revision_id) REFERENCES revisions(id),
    CONSTRAINT fk_rr_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== 0040_create_project_ai_assignments.sql ====================
-- Phase 1 / M1 Foundation — project AI assignments
-- Tracks AI agent assignments to projects with role and purpose
CREATE TABLE project_ai_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    ai_consumer_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    purpose VARCHAR(200) NULL,
    assigned_by BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_by BIGINT UNSIGNED NULL,
    revoked_at TIMESTAMP NULL,
    KEY idx_paia_project (project_id),
    KEY idx_paia_consumer (ai_consumer_id),
    CONSTRAINT fk_paia_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_paia_consumer FOREIGN KEY (ai_consumer_id) REFERENCES ai_consumers(id),
    CONSTRAINT fk_paia_role FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_paia_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id),
    CONSTRAINT fk_paia_revoked_by FOREIGN KEY (revoked_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== 0041_alter_projects_add_current_milestone_fk.sql ====================
-- Phase 1 / M1 Foundation — projects current_milestone_id FK
-- Adds FK constraint for current_milestone_id after milestones table exists
ALTER TABLE projects
    ADD CONSTRAINT fk_projects_current_milestone FOREIGN KEY (current_milestone_id) REFERENCES milestones(id);

-- ==================== 0042_seed_m0_permission_codes.sql ====================
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

-- ==================== 0043_create_ai_providers.sql ====================

-- ==================== Initial Platform Admin ====================
-- บัญชี admin แรกของระบบ (is_platform_admin = 1) — จำเป็นก่อน seed providers (0043+)
-- ใช้สร้าง invitation และผูก LINE account ผ่าน claim flow
INSERT INTO users (name, email, password_hash, status, is_platform_admin)
SELECT 'PMOIS Admin', 'admin@pmois.local', NULL, 'active', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE is_platform_admin = 1);

-- M1 / R6 Phase 1.5 — AI Provider Registry (global, not workspace-scoped)
-- Providers are platform-level facts (CTO Requirement #2: Provider separated from Agent)
CREATE TABLE ai_providers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ai_providers_code (code),
    CONSTRAINT fk_aip_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed canonical providers (Human included — used as default provider for backfill)
-- created_by = platform admin ตัวแรก (ถ้าไม่มี admin ในระบบ ให้ seed ทีหลังจากสร้าง admin)
INSERT INTO ai_providers (code, name, created_by)
SELECT x.code, x.name, (SELECT MIN(u.id) FROM users u WHERE u.is_platform_admin = 1) FROM (
    SELECT 'openai' AS code, 'OpenAI' AS name
    UNION ALL SELECT 'anthropic', 'Anthropic'
    UNION ALL SELECT 'google', 'Google'
    UNION ALL SELECT 'microsoft', 'Microsoft'
    UNION ALL SELECT 'human', 'Human'
) x;


-- ==================== 0044_alter_ai_consumers_add_provider_id.sql ====================
-- M1 / R6 Phase 1.5 — Agent references its Provider (CTO Requirement #2)
-- Column stays NULL-able at DB level for migration safety; the service layer
-- requires provider_id on every create/update (PROVIDER_REQUIRED).
ALTER TABLE ai_consumers
    ADD COLUMN provider_id BIGINT UNSIGNED NULL AFTER workspace_id,
    ADD CONSTRAINT fk_aic_provider FOREIGN KEY (provider_id) REFERENCES ai_providers(id);

-- Backfill: point every existing consumer at the Human provider until reclassified
UPDATE ai_consumers ac
JOIN ai_providers p ON p.code = 'human'
SET ac.provider_id = p.id
WHERE ac.provider_id IS NULL;


-- ==================== 0045_create_git_providers.sql ====================
-- M1 / R6 Phase 1.5 — Git Provider Registry
-- CTO Constraint: GitLab ONLY (GitHub / Azure DevOps / Bitbucket are out of scope)
CREATE TABLE git_providers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    base_url VARCHAR(500) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_git_providers_code (code),
    CONSTRAINT fk_gp_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: GitLab only (per CTO Decision — Source Control constraint)
INSERT INTO git_providers (code, name, base_url, created_by)
SELECT 'gitlab', 'GitLab', 'https://gitlab.com', (SELECT MIN(u.id) FROM users u WHERE u.is_platform_admin = 1);


-- ==================== 0046_alter_repositories_add_provider_rename_url.sql ====================
-- M1 / R6 Phase 1.5 — provider-agnostic repositories (CTO Requirement #4)
ALTER TABLE repositories
    ADD COLUMN git_provider_id BIGINT UNSIGNED NULL AFTER workspace_id,
    ADD CONSTRAINT fk_repo_git_provider FOREIGN KEY (git_provider_id) REFERENCES git_providers(id);

-- Backfill existing rows to GitLab (only provider seeded)
UPDATE repositories r
JOIN git_providers g ON g.code = 'gitlab'
SET r.git_provider_id = g.id
WHERE r.git_provider_id IS NULL;

-- Rename gitlab_url -> repository_url (index uq_repo_url follows the column automatically)
ALTER TABLE repositories CHANGE COLUMN gitlab_url repository_url VARCHAR(500) NOT NULL;


-- ==================== 0047_create_project_member_assignments.sql ====================
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


-- ==================== 0048_create_project_technology_stack.sql ====================
-- M1 / R6 Phase 1.5 — Technology Stack Registry (CTO Requirement #6)
-- Optional at creation; CTO/Dev fill progressively (project.techstack.manage)
CREATE TABLE project_technology_stack (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    layer ENUM('language','framework','database','runtime','frontend','infrastructure','tooling','other') NOT NULL,
    name VARCHAR(150) NOT NULL,
    version VARCHAR(50) NULL,
    notes TEXT NULL,
    status ENUM('active','deprecated','planned') NOT NULL DEFAULT 'active',
    added_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pts_project_layer_name (project_id, layer, name),
    KEY idx_pts_workspace (workspace_id),
    CONSTRAINT fk_pts_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_pts_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_pts_added_by FOREIGN KEY (added_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0049_create_project_environments.sql ====================
-- M1 / R6 Phase 1.5 — Environment Registry (CTO Requirement #7)
-- NO secrets/passwords ever — credential_reference is a pointer to a secret store only
CREATE TABLE project_environments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    environment ENUM('development','uat','production') NOT NULL,
    name VARCHAR(150) NOT NULL,
    url VARCHAR(500) NULL,
    runtime VARCHAR(150) NULL,
    php_version VARCHAR(20) NULL,
    database_engine VARCHAR(100) NULL,
    deploy_path VARCHAR(500) NULL,
    credential_reference VARCHAR(200) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pe_project_env_name (project_id, environment, name),
    KEY idx_pe_workspace (workspace_id),
    CONSTRAINT fk_pe_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_pe_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_pe_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0050_create_project_dependencies.sql ====================
-- M1 / R6 Phase 1.5 — Dependency Registry (CTO Requirement #8)
-- Typed directed edges; depends_on must stay acyclic (enforced in service layer)
CREATE TABLE project_dependencies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    related_project_id BIGINT UNSIGNED NOT NULL,
    dependency_type ENUM('depends_on','blocked_by') NOT NULL,
    note VARCHAR(300) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pd_edge (project_id, related_project_id, dependency_type),
    KEY idx_pd_related (related_project_id),
    CONSTRAINT fk_pd_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_pd_related FOREIGN KEY (related_project_id) REFERENCES projects(id),
    CONSTRAINT fk_pd_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_pd_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0051_create_project_releases.sql ====================
-- M1 / R6 Phase 1.5 — Release Registry (CTO Requirement #9)
-- Separate from Timeline (project_status_updates) and revision cycles
CREATE TABLE project_releases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    release_type ENUM('alpha','beta','rc','production','hotfix') NOT NULL,
    version_label VARCHAR(50) NOT NULL,
    status ENUM('planned','in_progress','released','rolled_back','cancelled') NOT NULL DEFAULT 'planned',
    repository_id BIGINT UNSIGNED NULL,
    environment_id BIGINT UNSIGNED NULL,
    milestone_id BIGINT UNSIGNED NULL,
    release_notes TEXT NULL,
    released_by BIGINT UNSIGNED NULL,
    released_at TIMESTAMP NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pr_project_version (project_id, version_label),
    KEY idx_pr_project_type (project_id, release_type),
    KEY idx_pr_workspace (workspace_id),
    CONSTRAINT fk_pr_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_pr_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_pr_repository FOREIGN KEY (repository_id) REFERENCES repositories(id),
    CONSTRAINT fk_pr_environment FOREIGN KEY (environment_id) REFERENCES project_environments(id),
    CONSTRAINT fk_pr_milestone FOREIGN KEY (milestone_id) REFERENCES milestones(id),
    CONSTRAINT fk_pr_released_by FOREIGN KEY (released_by) REFERENCES users(id),
    CONSTRAINT fk_pr_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0052_create_project_templates.sql ====================
-- M1 / R6 Phase 1.5 — Project Template (CTO Requirement #10)
-- payload JSON contract: see M0-Design/Revision6/R6-05-Project-Creation-Flow-Revision6.md §3
CREATE TABLE project_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    payload JSON NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pt_workspace_code (workspace_id, code),
    CONSTRAINT fk_pt_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_pt_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0053_alter_projects_add_source_template_id.sql ====================
-- M1 / R6 Phase 1.5 — project provenance (template applied at creation)
ALTER TABLE projects
    ADD COLUMN source_template_id BIGINT UNSIGNED NULL AFTER parent_project_id,
    ADD CONSTRAINT fk_projects_source_template FOREIGN KEY (source_template_id) REFERENCES project_templates(id);


-- ==================== 0054_create_workspace_default_settings.sql ====================
-- M1 / R6 Phase 1.5 — Workspace Default Settings (CTO Requirement #11)
-- 1:1 with workspace; typed columns for FK integrity
CREATE TABLE workspace_default_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    default_cto_user_id BIGINT UNSIGNED NULL,
    default_dev_user_id BIGINT UNSIGNED NULL,
    default_governance_version_id BIGINT UNSIGNED NULL,
    default_git_provider_id BIGINT UNSIGNED NULL,
    default_project_template_id BIGINT UNSIGNED NULL,
    default_development_mode ENUM('manual','ai_assisted','ai_dev_auto') NOT NULL DEFAULT 'manual',
    default_permission_preset VARCHAR(50) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wds_workspace (workspace_id),
    CONSTRAINT fk_wds_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_wds_cto FOREIGN KEY (default_cto_user_id) REFERENCES users(id),
    CONSTRAINT fk_wds_dev FOREIGN KEY (default_dev_user_id) REFERENCES users(id),
    CONSTRAINT fk_wds_govver FOREIGN KEY (default_governance_version_id) REFERENCES governance_versions(id),
    CONSTRAINT fk_wds_gitprov FOREIGN KEY (default_git_provider_id) REFERENCES git_providers(id),
    CONSTRAINT fk_wds_template FOREIGN KEY (default_project_template_id) REFERENCES project_templates(id),
    CONSTRAINT fk_wds_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0055_seed_r6_permission_codes.sql ====================
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


-- ==================== 0056_create_oauth_login_states.sql ====================
-- M1 R2 — OAuth login state store (CTO Review: OAuth State must be persisted, one-time use)
-- state เก็บเป็น hash เท่านั้น (ไม่เก็บ raw) — claim_token เก็บ raw ไว้ที่ server เพื่อ
-- complete claim ตอน callback (client ไม่มีส่วนเกี่ยวกับการ bind line_user_id)
CREATE TABLE oauth_login_states (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    state_hash CHAR(64) NOT NULL,
    purpose ENUM('login','claim') NOT NULL DEFAULT 'login',
    nonce VARCHAR(64) NULL,
    fingerprint_hash CHAR(64) NOT NULL,
    claim_token VARCHAR(2000) NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_oauth_state_hash (state_hash),
    KEY idx_oauth_state_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0057_create_user_sessions.sql ====================
-- M1 R2 — PMOIS user sessions (CTO Review 1.3: authenticated PMOIS session หลัง LINE Login)
-- session token เก็บเป็น hash เท่านั้น (raw อยู่ใน HttpOnly cookie ของ browser)
CREATE TABLE user_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    session_token_hash CHAR(64) NOT NULL,
    purpose ENUM('login','claim') NOT NULL DEFAULT 'login',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    expires_at TIMESTAMP NOT NULL,
    last_used_at TIMESTAMP NULL,
    revoked_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_sessions_hash (session_token_hash),
    KEY idx_user_sessions_user (user_id),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0058_alter_project_ai_assignments_add_workspace_id.sql ====================
-- M1 R2 — workspace scoping สำหรับ project_ai_assignments (ตาม convention: ทุกตาราง
-- workspace-scoped ต้องมี workspace_id — R6 Phase 1.5 repositories อ่าน column นี้)
ALTER TABLE project_ai_assignments
    ADD COLUMN workspace_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER project_id,
    ADD KEY idx_paia_workspace (workspace_id);

-- Backfill จาก project ของแต่ละแถว
UPDATE project_ai_assignments pa
JOIN projects p ON p.id = pa.project_id
SET pa.workspace_id = p.workspace_id
WHERE pa.workspace_id = 0;


-- ==================== 0059_create_project_deployments.sql ====================
-- M3 — Deployment Tracking
-- บันทึกการ deploy ของ project ไปยัง environment (เชื่อม release ได้) — ไม่มี secret
CREATE TABLE project_deployments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    release_id BIGINT UNSIGNED NULL,
    environment_id BIGINT UNSIGNED NULL,
    status ENUM('pending','in_progress','deployed','failed','rolled_back') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    deployed_by BIGINT UNSIGNED NULL,
    deployed_at TIMESTAMP NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_pdep_project (project_id),
    KEY idx_pdep_workspace (workspace_id),
    CONSTRAINT fk_pdep_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_pdep_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_pdep_release FOREIGN KEY (release_id) REFERENCES project_releases(id),
    CONSTRAINT fk_pdep_environment FOREIGN KEY (environment_id) REFERENCES project_environments(id),
    CONSTRAINT fk_pdep_deployed_by FOREIGN KEY (deployed_by) REFERENCES users(id),
    CONSTRAINT fk_pdep_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0060_alter_governance_records_add_audience_policy_type.sql ====================
-- M4 — PMO Governance
-- audience: CTO/Dev Working Instructions (all/cto/dev/pmo) — NULL = general template (backward compatible)
-- policy_type: Review/Delivery/Approval Policy (config-driven list ใน service) — เฉพาะ category='policy'
ALTER TABLE governance_records
    ADD COLUMN audience ENUM('all','cto','dev','pmo') NULL DEFAULT NULL AFTER category,
    ADD COLUMN policy_type VARCHAR(50) NULL AFTER audience;


-- ==================== 0061_create_notifications.sql ====================
-- M5 — API Platform: notification log (Telegram เป็น provider หลักตาม CTO Constraint)
-- ทุกความพยายามส่ง (สำเร็จ/ล้มเหลว/ข้าม) ถูก log เพื่อ monitoring + retry ในอนาคต
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NULL,
    project_id BIGINT UNSIGNED NULL,
    channel ENUM('telegram') NOT NULL DEFAULT 'telegram',
    event_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent','failed','skipped') NOT NULL,
    error TEXT NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notif_workspace (workspace_id),
    KEY idx_notif_project (project_id),
    KEY idx_notif_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0062_create_knowledge_entries.sql ====================
-- M6 — Knowledge Center
-- Registry ร่วมสำหรับ Business Rules / Known Issues / Risk Register / Future Enhancements
-- (ADR + Decision Log reuse decision_registers; Project Knowledge Base reuse knowledge_articles)
CREATE TABLE knowledge_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    entry_type ENUM('business_rule','known_issue','risk','future_enhancement') NOT NULL,
    code VARCHAR(50) NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    severity ENUM('low','medium','high','critical') NULL,
    probability ENUM('low','medium','high') NULL,
    impact ENUM('low','medium','high') NULL,
    mitigation TEXT NULL,
    related_revision_id BIGINT UNSIGNED NULL,
    related_url VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ke_workspace (workspace_id),
    KEY idx_ke_project (project_id),
    KEY idx_ke_type (entry_type),
    CONSTRAINT fk_ke_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_ke_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_ke_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0063_create_automation_jobs.sql ====================
-- M8 — Automation Center: automation queue
-- job_type ครอบ scope: timeline_update / project_update (Automatic Updates),
-- gitlab_sync (GitLab Integration), ai_dev_auto (AI Dev Auto Integration),
-- notification (Telegram Automation)
CREATE TABLE automation_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NULL,
    project_id BIGINT UNSIGNED NULL,
    job_type ENUM('timeline_update','project_update','gitlab_sync','ai_dev_auto','notification') NOT NULL,
    payload JSON NOT NULL,
    status ENUM('queued','running','completed','failed') NOT NULL DEFAULT 'queued',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
    scheduled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    finished_at TIMESTAMP NULL,
    last_error TEXT NULL,
    result JSON NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_aj_status_sched (status, scheduled_at),
    KEY idx_aj_workspace (workspace_id),
    KEY idx_aj_project (project_id),
    CONSTRAINT fk_aj_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
    CONSTRAINT fk_aj_project FOREIGN KEY (project_id) REFERENCES projects(id),
    CONSTRAINT fk_aj_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================== 0064_seed_automation_permission_codes.sql ====================
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

-- ==================== End of Installer ====================
