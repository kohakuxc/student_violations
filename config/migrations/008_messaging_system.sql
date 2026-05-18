-- Migration 008: Messaging system with role-based conversations
-- Supports student-officer messaging with thread tracking, read status, and attachment support

-- 1) Conversations table (thread header)
CREATE TABLE IF NOT EXISTS conversations (
    conversation_id bigserial PRIMARY KEY,
    student_id bigint NOT NULL REFERENCES students(student_id) ON DELETE CASCADE,
    officer_id bigint NOT NULL REFERENCES officers(officer_id) ON DELETE CASCADE,
    subject text,
    initiated_by_role text NOT NULL, -- 'student' or 'officer'
    last_message_at timestamptz,
    is_archived_by_student boolean NOT NULL DEFAULT false,
    is_archived_by_officer boolean NOT NULL DEFAULT false,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

-- 2) Messages within conversations
CREATE TABLE IF NOT EXISTS messages (
    message_id bigserial PRIMARY KEY,
    conversation_id bigint NOT NULL REFERENCES conversations(conversation_id) ON DELETE CASCADE,
    sender_id bigint, -- officer_id or student_id (referenced by sender_role)
    sender_role text NOT NULL, -- 'student' or 'officer'
    message_body text NOT NULL,
    attachment_path text, -- file upload path
    attachment_filename text,
    is_read boolean NOT NULL DEFAULT false,
    read_at timestamptz NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

-- 3) Conversation participants (tracks read status per participant)
CREATE TABLE IF NOT EXISTS conversation_participants (
    participant_id bigserial PRIMARY KEY,
    conversation_id bigint NOT NULL REFERENCES conversations(conversation_id) ON DELETE CASCADE,
    user_id bigint NOT NULL,
    user_role text NOT NULL, -- 'student' or 'officer'
    last_read_message_id bigint REFERENCES messages(message_id) ON DELETE SET NULL,
    unread_count bigint NOT NULL DEFAULT 0,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE(conversation_id, user_id, user_role)
);

-- 4) Indexes for efficient queries
CREATE INDEX IF NOT EXISTS idx_conversations_student
    ON conversations (student_id, is_archived_by_student, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_conversations_officer
    ON conversations (officer_id, is_archived_by_officer, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_conversations_pair
    ON conversations (student_id, officer_id);

CREATE INDEX IF NOT EXISTS idx_messages_conversation
    ON messages (conversation_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_messages_unread
    ON messages (conversation_id, is_read, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_participants_user
    ON conversation_participants (user_id, user_role, conversation_id);

CREATE INDEX IF NOT EXISTS idx_participants_unread
    ON conversation_participants (user_id, user_role, unread_count DESC);
