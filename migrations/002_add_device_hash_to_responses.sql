ALTER TABLE responses ADD COLUMN device_hash TEXT NULL;

CREATE INDEX IF NOT EXISTS idx_responses_feedback_device_hash ON responses (feedback_id, device_hash);
