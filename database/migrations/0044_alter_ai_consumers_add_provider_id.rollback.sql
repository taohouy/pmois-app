-- Rollback: drop provider link from ai_consumers
ALTER TABLE ai_consumers DROP FOREIGN KEY fk_aic_provider;
ALTER TABLE ai_consumers DROP COLUMN provider_id;
