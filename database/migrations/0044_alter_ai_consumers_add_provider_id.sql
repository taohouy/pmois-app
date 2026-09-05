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
