ALTER TABLE api_tokens
    ADD COLUMN ai_consumer_id BIGINT UNSIGNED NULL AFTER created_by_user_id;

ALTER TABLE api_tokens
    ADD CONSTRAINT fk_tokens_ai_consumer FOREIGN KEY (ai_consumer_id) REFERENCES ai_consumers(id);
