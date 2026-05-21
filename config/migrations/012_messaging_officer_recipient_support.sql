-- Migration 012: expand messaging to support officer-to-officer conversations

ALTER TABLE conversations
    ADD COLUMN IF NOT EXISTS other_officer_id bigint NULL REFERENCES officers(officer_id) ON DELETE SET NULL;

ALTER TABLE conversations
    ADD COLUMN IF NOT EXISTS conversation_kind text NOT NULL DEFAULT 'student_officer';

ALTER TABLE conversations
    ALTER COLUMN student_id DROP NOT NULL;

UPDATE conversations
SET conversation_kind = 'student_officer'
WHERE conversation_kind IS NULL;

CREATE INDEX IF NOT EXISTS idx_conversations_other_officer
    ON conversations (other_officer_id, is_archived_by_officer, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_conversations_kind
    ON conversations (conversation_kind, created_at DESC);