-- Inbound Status API — add idempotency_key to project_status_updates
ALTER TABLE project_status_updates
    ADD COLUMN idempotency_key VARCHAR(100) NULL AFTER next_steps,
    ADD UNIQUE KEY uq_psu_idempotency_key (idempotency_key);
