-- Migration 007: Notifications system with read/unread tracking
-- Replaces log file approach with database table for better filtering, retention, and per-user tracking

-- 1) Main notifications table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id bigserial PRIMARY KEY,
    officer_id bigint NOT NULL REFERENCES officers(officer_id) ON DELETE CASCADE,
    notification_type text NOT NULL, -- 'appointment_request', 'appointment_approved', 'appointment_rejected', 'appointment_rescheduled', 'appointment_completed', 'settings_changed'
    title text NOT NULL,
    message text,
    target_id bigint, -- appointment_id or null
    target_url text, -- URL to navigate to when clicked
    is_read boolean NOT NULL DEFAULT false,
    read_at timestamptz NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

-- 2) Index for efficient querying
CREATE INDEX IF NOT EXISTS idx_notifications_officer_unread
    ON notifications (officer_id, is_read, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_notifications_officer_created
    ON notifications (officer_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_notifications_target
    ON notifications (target_id, notification_type);

-- 3) Mark all notifications as read
CREATE OR REPLACE FUNCTION mark_all_notifications_read(p_officer_id bigint)
RETURNS TABLE(count_updated bigint) AS $$
BEGIN
    UPDATE notifications 
    SET is_read = true, read_at = now(), updated_at = now()
    WHERE officer_id = p_officer_id AND is_read = false;
    
    RETURN QUERY SELECT COUNT(*)::bigint FROM notifications 
    WHERE officer_id = p_officer_id AND is_read = true AND read_at IS NOT NULL;
END;
$$ LANGUAGE plpgsql;
