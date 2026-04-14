# Render Deployment Guide (Docker + Supabase)

This project is ready for Render using Docker and PostgreSQL (Supabase).

## 1) Create Web Service

- In Render: New -> Web Service
- Connect your GitHub repository and branch
- Runtime: `Docker`
- Dockerfile path: `./Dockerfile`

## 2) Set Environment Variables

Configure these in Render Dashboard -> Environment:

- `APP_ENV=production`
- `DB_DRIVER=pgsql`
- `DB_HOST=<your-supabase-host>`
- `DB_PORT=5432`
- `DB_DATABASE=postgres`
- `DB_USERNAME=postgres`
- `DB_PASSWORD=<your-supabase-password>`
- `DB_SSLMODE=require`
- `DEFAULT_OFFICER_ID=1`
- `MICROSOFT_CLIENT_ID=<azure-app-client-id>`
- `MICROSOFT_CLIENT_SECRET=<azure-app-secret>`
- `MICROSOFT_TENANT=<tenant-id-or-common>`
- `MICROSOFT_REDIRECT_URI=https://<your-render-domain>/index.php?page=student_oauth_callback`
- `MICROSOFT_SCOPES=openid profile email User.Read`

## 3) Apply Supabase Schema

In Supabase SQL Editor, run:

- `config/migrations/000_supabase_schema.sql`

## 4) Production Safety Notes

- `api/appointments.php` no longer supports test session bypass.
- `api/debug_appointments.php` is blocked outside development and requires officer authentication.
- Upload folders are ignored by git (`uploads/appointments`, `uploads/evidence`).
- Appointment verbose logging is suppressed in production mode.

## 5) Post-Deploy Checks

1. Officer login works.
2. Student OAuth callback works with Render redirect URI.
3. Student appointment creation writes rows to Supabase.
4. Officer can see and update appointment statuses.