-- Migration 011: Admin roles, student access list, audit logs, reports, appointment locks, and imports (PostgreSQL/Supabase)

-- 1) Officers: role/permission columns
ALTER TABLE officers
    ADD COLUMN IF NOT EXISTS is_admin boolean NOT NULL DEFAULT false;

ALTER TABLE officers
    ADD COLUMN IF NOT EXISTS is_superadmin boolean NOT NULL DEFAULT false;

ALTER TABLE officers
    ADD COLUMN IF NOT EXISTS is_active boolean NOT NULL DEFAULT true;

ALTER TABLE officers
    ADD COLUMN IF NOT EXISTS can_import_excel boolean NOT NULL DEFAULT false;

ALTER TABLE officers
    ADD COLUMN IF NOT EXISTS password_updated_at timestamptz NULL;

-- Ensure the seeded officer remains usable as an initial admin
UPDATE officers
SET is_admin = true,
    is_superadmin = true,
    is_active = true
WHERE officer_id = 1
  AND (is_admin IS NULL OR is_admin = false OR is_superadmin IS NULL OR is_superadmin = false);

-- 2) Student access whitelist
CREATE TABLE IF NOT EXISTS student_accounts (
    account_id bigserial PRIMARY KEY,
    email text NOT NULL UNIQUE,
    is_enabled boolean NOT NULL DEFAULT true,
    created_by_officer_id bigint NULL REFERENCES officers(officer_id) ON DELETE SET NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NULL
);

CREATE INDEX IF NOT EXISTS idx_student_accounts_enabled
    ON student_accounts (is_enabled, created_at DESC);

-- 3) Admin password reset tokens
CREATE TABLE IF NOT EXISTS admin_password_resets (
    reset_id bigserial PRIMARY KEY,
    officer_id bigint NOT NULL REFERENCES officers(officer_id) ON DELETE CASCADE,
    token_hash text NOT NULL,
    expires_at timestamptz NOT NULL,
    used_at timestamptz NULL,
    created_by_officer_id bigint NULL REFERENCES officers(officer_id) ON DELETE SET NULL,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_admin_password_resets_token
    ON admin_password_resets (token_hash);

CREATE INDEX IF NOT EXISTS idx_admin_password_resets_officer
    ON admin_password_resets (officer_id, created_at DESC);

-- 4) Audit logs
CREATE TABLE IF NOT EXISTS audit_logs (
    audit_id bigserial PRIMARY KEY,
    actor_officer_id bigint NULL,
    actor_role text NOT NULL,
    action_type text NOT NULL,
    target_type text NULL,
    target_id bigint NULL,
    metadata jsonb NULL,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_actor
    ON audit_logs (actor_role, actor_officer_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_audit_logs_target
    ON audit_logs (target_type, target_id, created_at DESC);

-- 5) Student reports
CREATE TABLE IF NOT EXISTS student_reports (
    report_id bigserial PRIMARY KEY,
    student_id bigint NOT NULL REFERENCES students(student_id) ON DELETE CASCADE,
    report_type text NOT NULL CHECK (report_type IN ('bullying', 'discipline', 'mental_health')),
    description text NOT NULL,
    status text NOT NULL DEFAULT 'new' CHECK (status IN ('new', 'in_review', 'resolved', 'escalated')),
    is_self_harm boolean NOT NULL DEFAULT false,
    triaged_by_officer_id bigint NULL REFERENCES officers(officer_id) ON DELETE SET NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_student_reports_status
    ON student_reports (status, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_student_reports_student
    ON student_reports (student_id, created_at DESC);

-- 6) Appointment hardening: appointment_date, locks, self-harm, uniqueness
ALTER TABLE appointments
    ADD COLUMN IF NOT EXISTS appointment_date date NULL;

ALTER TABLE appointments
    ADD COLUMN IF NOT EXISTS is_self_harm boolean NOT NULL DEFAULT false;

ALTER TABLE appointments
    ADD COLUMN IF NOT EXISTS locked_at timestamptz NULL;

ALTER TABLE appointments
    ADD COLUMN IF NOT EXISTS locked_by_role text NULL;

ALTER TABLE appointments
    ADD COLUMN IF NOT EXISTS locked_by_id bigint NULL;

UPDATE appointments
SET appointment_date = CAST(scheduled_date AS date)
WHERE appointment_date IS NULL;

WITH ranked_appointments AS (
    SELECT
        appointment_id,
        ROW_NUMBER() OVER (
            PARTITION BY student_id, appointment_date
            ORDER BY created_at DESC, appointment_id DESC
        ) AS rn
    FROM appointments
    WHERE appointment_date IS NOT NULL
)
UPDATE appointments
SET appointment_date = NULL
FROM ranked_appointments
WHERE appointments.appointment_id = ranked_appointments.appointment_id
  AND ranked_appointments.rn > 1;

UPDATE appointments
SET locked_at = COALESCE(locked_at, now()),
    locked_by_role = COALESCE(locked_by_role, 'system')
WHERE status IN ('cancelled', 'rejected')
  AND locked_at IS NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'uniq_appointments_student_date'
    ) THEN
        ALTER TABLE appointments
            ADD CONSTRAINT uniq_appointments_student_date UNIQUE (student_id, appointment_date);
    END IF;
END $$;

CREATE OR REPLACE FUNCTION prevent_locked_appointment_updates()
RETURNS trigger AS $$
BEGIN
    IF OLD.locked_at IS NOT NULL THEN
        RAISE EXCEPTION 'Appointment is locked and cannot be modified.';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_prevent_locked_appointment_updates ON appointments;
CREATE TRIGGER trg_prevent_locked_appointment_updates
    BEFORE UPDATE ON appointments
    FOR EACH ROW
    WHEN (OLD.locked_at IS NOT NULL)
    EXECUTE FUNCTION prevent_locked_appointment_updates();

-- 7) Self-harm flagging for violations and messages
ALTER TABLE violations
    ADD COLUMN IF NOT EXISTS is_self_harm boolean NOT NULL DEFAULT false;

ALTER TABLE messages
    ADD COLUMN IF NOT EXISTS is_self_harm boolean NOT NULL DEFAULT false;

-- 8) Excel import logs
CREATE TABLE IF NOT EXISTS import_logs (
    import_id bigserial PRIMARY KEY,
    officer_id bigint NULL REFERENCES officers(officer_id) ON DELETE SET NULL,
    file_name text NOT NULL,
    file_type text NOT NULL,
    total_rows integer NOT NULL DEFAULT 0,
    imported_rows integer NOT NULL DEFAULT 0,
    error_rows integer NOT NULL DEFAULT 0,
    status text NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'completed', 'failed')),
    metadata jsonb NULL,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_import_logs_officer
    ON import_logs (officer_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_import_logs_status
    ON import_logs (status, created_at DESC);
