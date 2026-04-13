-- Supabase/PostgreSQL schema draft for student_violations
-- Apply this in Supabase SQL Editor after creating the project.

create table if not exists public.students (
    student_id bigserial primary key,
    name text not null,
    student_number text not null unique,
    email text not null unique,
    microsoft_id text null unique,
    oauth_token text null,
    last_login timestamptz null,
    created_at timestamptz not null default now()
);

create table if not exists public.officers (
    officer_id bigserial primary key,
    username text not null unique,
    name text not null,
    password text not null,
    created_at timestamptz not null default now()
);

create table if not exists public.violation_types (
    violation_type_id bigserial primary key,
    type_name text not null unique,
    severity_level text not null check (severity_level in ('minor', 'moderate', 'major')),
    is_active boolean not null default true,
    created_at timestamptz not null default now()
);

create table if not exists public.violations (
    violation_id bigserial primary key,
    student_id bigint not null references public.students(student_id) on delete cascade,
    officer_id bigint not null references public.officers(officer_id) on delete restrict,
    violation_type bigint not null references public.violation_types(violation_type_id) on delete restrict,
    description text not null,
    date_of_violation date not null,
    created_at timestamptz not null default now()
);

create table if not exists public.appointment_categories (
    category_id bigserial primary key,
    category_name text not null unique,
    created_at timestamptz not null default now()
);

create table if not exists public.appointment_subcategories (
    subcategory_id bigserial primary key,
    category_id bigint not null references public.appointment_categories(category_id) on delete cascade,
    subcategory_name text not null,
    created_at timestamptz not null default now(),
    unique (category_id, subcategory_name)
);

create table if not exists public.appointments (
    appointment_id bigserial primary key,
    student_id bigint not null references public.students(student_id) on delete cascade,
    officer_id bigint null references public.officers(officer_id) on delete set null,
    category_id bigint not null references public.appointment_categories(category_id) on delete restrict,
    subcategory_id bigint not null references public.appointment_subcategories(subcategory_id) on delete restrict,
    description text not null,
    scheduled_date timestamptz not null,
    evidence_image text null,
    status text not null default 'pending' check (
        status in ('pending', 'approved', 'in_progress', 'completed', 'rejected', 'cancelled', 'rescheduled')
    ),
    created_at timestamptz not null default now(),
    updated_at timestamptz null
);

create table if not exists public.appointment_notes (
    note_id bigserial primary key,
    appointment_id bigint not null references public.appointments(appointment_id) on delete cascade,
    note_text text not null,
    officer_id bigint null references public.officers(officer_id) on delete set null,
    created_at timestamptz not null default now()
);

create table if not exists public.appointment_reasons (
    reason_id bigserial primary key,
    appointment_id bigint not null references public.appointments(appointment_id) on delete cascade,
    reason_type text not null check (reason_type in ('cancellation', 'rejection', 'reschedule')),
    reason_text text not null,
    created_by bigint not null,
    created_at timestamptz not null default now()
);

create index if not exists idx_violations_student_id on public.violations(student_id);
create index if not exists idx_violations_officer_id on public.violations(officer_id);
create index if not exists idx_appointments_student_id on public.appointments(student_id);
create index if not exists idx_appointments_officer_id on public.appointments(officer_id);
create index if not exists idx_appointments_status on public.appointments(status);
create index if not exists idx_appointments_scheduled_date on public.appointments(scheduled_date);
create index if not exists idx_appointment_notes_appointment_id on public.appointment_notes(appointment_id);
create index if not exists idx_appointment_reasons_appointment_id on public.appointment_reasons(appointment_id);
