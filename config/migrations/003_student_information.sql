-- Migration: Normalize student profile fields into student_information (PostgreSQL/Supabase)
-- This keeps students as the auth/identity table and moves profile fields to a dedicated table.

BEGIN;

CREATE TABLE IF NOT EXISTS public.student_information (
    student_id bigint PRIMARY KEY REFERENCES public.students(student_id) ON DELETE CASCADE,
    last_name text NOT NULL,
    first_name text NOT NULL,
    student_num text NOT NULL UNIQUE CHECK (student_num ~ '^[0-9]{6}$'),
    email text NOT NULL UNIQUE,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NULL
);

-- Backfill normalized data from current students table.
INSERT INTO public.student_information (student_id, last_name, first_name, student_num, email)
SELECT
    s.student_id,
    COALESCE(NULLIF(TRIM(split_part(COALESCE(s.name, ''), ',', 1)), ''), 'Unknown') AS last_name,
    COALESCE(
        NULLIF(
            TRIM(
                regexp_replace(
                    split_part(COALESCE(s.name, ''), ',', 2),
                    '\\s*\\(.*\\)$',
                    ''
                )
            ),
            ''
        ),
        'Student'
    ) AS first_name,
    COALESCE(
        substring(COALESCE(s.email, '') FROM '([0-9]{6})'),
        substring(COALESCE(s.student_number, '') FROM '([0-9]{6})'),
        LPAD((s.student_id % 1000000)::text, 6, '0')
    ) AS student_num,
    LOWER(TRIM(COALESCE(s.email, ''))) AS email
FROM public.students s
WHERE TRIM(COALESCE(s.email, '')) <> ''
ON CONFLICT (student_id) DO UPDATE
SET last_name = EXCLUDED.last_name,
    first_name = EXCLUDED.first_name,
    student_num = EXCLUDED.student_num,
    email = EXCLUDED.email,
    updated_at = now();

-- Keep legacy students columns synchronized for backward compatibility.
UPDATE public.students s
SET name = CONCAT(si.last_name, ', ', si.first_name, ' (Student)'),
    student_number = si.student_num,
    email = si.email
FROM public.student_information si
WHERE si.student_id = s.student_id;

COMMIT;
