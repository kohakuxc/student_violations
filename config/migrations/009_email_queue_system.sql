-- Migration 009: Email queue system for reliable delivery with retry logic
-- Supports SMTP provider integration and delivery tracking

-- 1) Email queue table
CREATE TABLE IF NOT EXISTS email_queue (
    email_id bigserial PRIMARY KEY,
    to_address text NOT NULL,
    to_name text,
    subject text NOT NULL,
    body_html text NOT NULL,
    body_text text,
    email_type text NOT NULL, -- 'appointment_created', 'appointment_approved', 'appointment_rejected', 'appointment_rescheduled', 'appointment_completed', 'appointment_cancelled', 'message_received'
    related_appointment_id bigint REFERENCES appointments(appointment_id) ON DELETE SET NULL,
    related_message_id bigint REFERENCES messages(message_id) ON DELETE SET NULL,
    attempt_count integer NOT NULL DEFAULT 0,
    max_attempts integer NOT NULL DEFAULT 3,
    status text NOT NULL DEFAULT 'pending', -- 'pending', 'sent', 'failed', 'bounce'
    last_error text,
    sent_at timestamptz,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

-- 2) Email delivery log (audit trail)
CREATE TABLE IF NOT EXISTS email_delivery_log (
    log_id bigserial PRIMARY KEY,
    email_id bigint NOT NULL REFERENCES email_queue(email_id) ON DELETE CASCADE,
    attempt_number integer NOT NULL,
    status text NOT NULL, -- 'success', 'temporary_failure', 'permanent_failure', 'retry'
    smtp_response text,
    error_message text,
    attempt_at timestamptz NOT NULL DEFAULT now()
);

-- 3) Indexes
CREATE INDEX IF NOT EXISTS idx_email_queue_status
    ON email_queue (status, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_email_queue_pending
    ON email_queue (status, attempt_count, created_at ASC)
    WHERE status = 'pending' AND attempt_count < max_attempts;

CREATE INDEX IF NOT EXISTS idx_email_delivery_log_email
    ON email_delivery_log (email_id, attempt_number DESC);

CREATE INDEX IF NOT EXISTS idx_email_queue_related_appointment
    ON email_queue (related_appointment_id);

CREATE INDEX IF NOT EXISTS idx_email_queue_related_message
    ON email_queue (related_message_id);
