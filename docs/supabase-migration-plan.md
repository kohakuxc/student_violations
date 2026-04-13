# Supabase + Render Migration Plan

This project can move to Supabase (PostgreSQL) and Render, but it must be done in steps.

## Order Of Work

1. Freeze the current working branch and keep a backup.
2. Create the Supabase project and database.
3. Apply the PostgreSQL schema migration.
4. Move data from SQL Server into Supabase.
5. Refactor PHP connection code from `sqlsrv` to `pgsql`.
6. Convert SQL Server queries to PostgreSQL syntax.
7. Deploy the PHP app to Render.
8. Update Microsoft OAuth redirect URIs to the Render URL.
9. Test all major flows: student login, auto-create, appointments, violations, officer login.

## Environment Switch

- Use `DB_DRIVER=sqlsrv` while developing locally against XAMPP.
- Use `DB_DRIVER=pgsql` with Supabase on Render.
- For Supabase, set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_SSLMODE=require`.

## Current SQL Server Features That Need Conversion

- `sqlsrv:` DSN in [config/db_connection.php](../config/db_connection.php)
- `dbo.` schema prefixes in model queries
- `GETDATE()` -> `NOW()`
- `TOP n` -> `LIMIT n`
- `CAST(... AS DATE)` and `DATEDIFF()` queries that may need PostgreSQL rewrites

## Security Rules For The New Deployment

- Keep all secrets in Render environment variables.
- Do not commit `.env`.
- Do not expose Supabase service-role keys in client-side code.
- Use HTTPS only in production.
- Update Microsoft OAuth redirect URI to the Render domain exactly.

## Recommended Database Tables

- `students`
- `officers`
- `violations`
- `violation_types`
- `appointment_categories`
- `appointment_subcategories`
- `appointments`
- `appointment_notes`
- `appointment_reasons`

## Notes

- Microsoft does not provide `age`, so that field should be collected later if you decide you need it.
- Auto-create first-time student accounts can continue working after the migration.
- The app currently assumes a student record can be matched by email or Microsoft ID.