-- Migration: Drop legacy profile columns from students after normalization
-- Target: PostgreSQL/Supabase
-- Prerequisite: 003_student_information.sql completed successfully

BEGIN;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM public.students s
        LEFT JOIN public.student_information si ON si.student_id = s.student_id
        WHERE si.student_id IS NULL
    ) THEN
        RAISE EXCEPTION 'Cannot drop legacy columns: some students are missing student_information rows';
    END IF;
END
$$ LANGUAGE plpgsql;

ALTER TABLE public.students
    DROP COLUMN IF EXISTS name,
    DROP COLUMN IF EXISTS student_number,
    DROP COLUMN IF EXISTS email;

COMMIT;
