-- Migration 010: Support student recipients in notifications
-- Adds student_id recipient path while keeping officer notifications intact

ALTER TABLE notifications
    ALTER COLUMN officer_id DROP NOT NULL;

ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS student_id bigint NULL REFERENCES students(student_id) ON DELETE CASCADE;

CREATE INDEX IF NOT EXISTS idx_notifications_student_unread
    ON notifications (student_id, is_read, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_notifications_student_created
    ON notifications (student_id, created_at DESC);

-- Optional integrity guard: at least one recipient must exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'chk_notifications_recipient_present'
    ) THEN
        ALTER TABLE notifications
            ADD CONSTRAINT chk_notifications_recipient_present
            CHECK (officer_id IS NOT NULL OR student_id IS NOT NULL);
    END IF;
END $$;
