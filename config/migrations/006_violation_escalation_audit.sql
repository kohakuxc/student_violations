-- Migration 006: Keep converted minor violations for audit and link them to generated major violations
-- Rule: every 3 minor offenses are escalated to 1 Major Offense - Category A
-- This migration adds tracking columns/tables so source minor violations are preserved.

-- 1) Track escalation state on violations
ALTER TABLE violations
    ADD COLUMN IF NOT EXISTS is_escalated boolean NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS escalated_at timestamptz NULL,
    ADD COLUMN IF NOT EXISTS escalated_to_violation_id bigint NULL REFERENCES violations(violation_id) ON DELETE SET NULL;

-- 2) Escalation header table (one row per conversion event)
CREATE TABLE IF NOT EXISTS violation_escalations (
    escalation_id bigserial PRIMARY KEY,
    student_id bigint NOT NULL REFERENCES students(student_id) ON DELETE CASCADE,
    major_violation_id bigint NOT NULL REFERENCES violations(violation_id) ON DELETE CASCADE,
    created_by_officer_id bigint NULL REFERENCES officers(officer_id) ON DELETE SET NULL,
    rule_code text NOT NULL DEFAULT 'minor_3_to_major_a',
    created_at timestamptz NOT NULL DEFAULT now()
);

-- 3) Escalation item table (source minor violations used in the conversion)
CREATE TABLE IF NOT EXISTS violation_escalation_items (
    escalation_item_id bigserial PRIMARY KEY,
    escalation_id bigint NOT NULL REFERENCES violation_escalations(escalation_id) ON DELETE CASCADE,
    source_violation_id bigint NOT NULL REFERENCES violations(violation_id) ON DELETE CASCADE,
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (escalation_id, source_violation_id)
);

-- 4) Helpful indexes
CREATE INDEX IF NOT EXISTS idx_violations_escalation_state
    ON violations (student_id, is_escalated, date_of_violation, created_at);

CREATE INDEX IF NOT EXISTS idx_violations_escalated_to
    ON violations (escalated_to_violation_id);

CREATE INDEX IF NOT EXISTS idx_violation_escalations_student
    ON violation_escalations (student_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_violation_escalation_items_source
    ON violation_escalation_items (source_violation_id);
