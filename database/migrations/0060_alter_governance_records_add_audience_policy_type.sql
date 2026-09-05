-- M4 — PMO Governance
-- audience: CTO/Dev Working Instructions (all/cto/dev/pmo) — NULL = general template (backward compatible)
-- policy_type: Review/Delivery/Approval Policy (config-driven list ใน service) — เฉพาะ category='policy'
ALTER TABLE governance_records
    ADD COLUMN audience ENUM('all','cto','dev','pmo') NULL DEFAULT NULL AFTER category,
    ADD COLUMN policy_type VARCHAR(50) NULL AFTER audience;
