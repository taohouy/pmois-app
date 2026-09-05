-- =====================================================================
-- PMOIS v2 — Database Rollback (M9)
-- ลบทุกตารางของ PMOIS แบบ FK-safe (ใช้เมื่อต้องการถอน installation ทั้งหมด)
-- ⚠️ DESTRUCTIVE — ทำ backup (mysqldump) ก่อนรันเสมอ
-- ตารางเรียง child → parent (generated จาก Consolidated Installer — 45 ตาราง)
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS automation_jobs;
DROP TABLE IF EXISTS knowledge_entries;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS project_deployments;
DROP TABLE IF EXISTS user_sessions;
DROP TABLE IF EXISTS oauth_login_states;
DROP TABLE IF EXISTS workspace_default_settings;
DROP TABLE IF EXISTS project_templates;
DROP TABLE IF EXISTS project_releases;
DROP TABLE IF EXISTS project_dependencies;
DROP TABLE IF EXISTS project_environments;
DROP TABLE IF EXISTS project_technology_stack;
DROP TABLE IF EXISTS project_member_assignments;
DROP TABLE IF EXISTS git_providers;
DROP TABLE IF EXISTS ai_providers;
DROP TABLE IF EXISTS project_ai_assignments;
DROP TABLE IF EXISTS revision_reviews;
DROP TABLE IF EXISTS revisions;
DROP TABLE IF EXISTS repositories;
DROP TABLE IF EXISTS milestones;
DROP TABLE IF EXISTS project_structure_history;
DROP TABLE IF EXISTS project_status_updates;
DROP TABLE IF EXISTS ai_context_exports;
DROP TABLE IF EXISTS ai_consumers;
DROP TABLE IF EXISTS knowledge_links;
DROP TABLE IF EXISTS attachments;
DROP TABLE IF EXISTS knowledge_articles;
DROP TABLE IF EXISTS governance_adoption_items;
DROP TABLE IF EXISTS governance_adoptions;
DROP TABLE IF EXISTS rfc_comments;
DROP TABLE IF EXISTS rfcs;
DROP TABLE IF EXISTS decision_registers;
DROP TABLE IF EXISTS governance_version_items;
DROP TABLE IF EXISTS governance_versions;
DROP TABLE IF EXISTS governance_records;
DROP TABLE IF EXISTS audit_trails;
DROP TABLE IF EXISTS api_tokens;
DROP TABLE IF EXISTS workspace_module_settings;
DROP TABLE IF EXISTS project_members;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS workspace_members;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS workspaces;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- เสร็จสิ้น — ตรวจสอบ: SHOW TABLES; ต้องว่าง
