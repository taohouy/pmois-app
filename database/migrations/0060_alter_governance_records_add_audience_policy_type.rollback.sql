-- Rollback: governance_records audience/policy_type
ALTER TABLE governance_records DROP COLUMN policy_type;
ALTER TABLE governance_records DROP COLUMN audience;
