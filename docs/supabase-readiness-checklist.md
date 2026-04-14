# Supabase Readiness Checklist (Strict, Command-By-Command)

Use this checklist before creating the Render Web Service.

## 1) Create a clean checkpoint branch

Run:

```powershell
Set-Location c:\xampp\htdocs\student_violations
git fetch origin
git checkout main
git pull --rebase origin main
git checkout -b chore/supabase-readiness
```

Pass criteria:

- `git status --short` is empty before new edits.

## 2) Verify PHP files still parse

Run:

```powershell
php -l config/db_connection.php
php -l config/microsoft365_config.php
php -l model/StudentAuthModel.php
php -l model/AppointmentModel.php
php -l model/ViolationModel.php
php -l controller/StudentLoginController.php
php -l index.php
```

Pass criteria:

- Every command prints `No syntax errors detected`.

## 3) Confirm required env keys exist in config/.env

Run:

```powershell
$required = @(
  'DB_DRIVER','DB_DATABASE','DB_USERNAME','DB_PASSWORD',
  'DB_HOST','DB_PORT','DB_SSLMODE',
  'MICROSOFT_CLIENT_ID','MICROSOFT_CLIENT_SECRET','MICROSOFT_REDIRECT_URI','MICROSOFT_TENANT','MICROSOFT_SCOPES',
  'APP_ENV'
)

$present = Get-Content config/.env |
  Where-Object { $_ -match '^[A-Z0-9_]+=' } |
  ForEach-Object { ($_ -split '=')[0] }

$missing = $required | Where-Object { $_ -notin $present }

if ($missing.Count -gt 0) {
  Write-Host 'Missing keys:' -ForegroundColor Red
  $missing
} else {
  Write-Host 'All required keys are present.' -ForegroundColor Green
}
```

Pass criteria:

- Output says `All required keys are present.`

## 4) Switch local env to Supabase mode

Edit `config/.env` and set:

```env
DB_DRIVER=pgsql
DB_HOST=<your-supabase-host>
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=<your-supabase-password>
DB_SSLMODE=require
APP_ENV=production
```

Keep your Microsoft keys and redirect URI valid for local testing.

Pass criteria:

- File saved with values above (real secrets in place, not placeholders).

## 5) Verify direct DB connectivity with PDO

Run:

```powershell
php -r 'require "config/db_connection.php"; $row=$conn->query("SELECT current_database() AS db, NOW() AS now_ts")->fetch(); var_export($row);'
```

Pass criteria:

- Output includes `db => 'postgres'` (or your configured DB name) and a timestamp.
- No `Database Connection Error` message.

## 6) Apply schema in Supabase SQL Editor

In Supabase Dashboard -> SQL Editor, run:

- `config/migrations/000_supabase_schema.sql`
- `config/migrations/001_appointment_notes.sql` (if not already integrated into schema)
- `config/migrations/002_students_oauth.sql` (apply only statements valid for PostgreSQL)

Pass criteria:

- All migration statements complete without errors.
- Core tables exist (`students`, `officers`, `violations`, `appointments`, `appointment_notes`, `appointment_reasons`).

## 7) Detect remaining SQL Server-only syntax in source

Run:

```powershell
Get-ChildItem -Recurse -File model,controller,api,config |
  Where-Object { $_.Extension -in '.php','.sql' } |
  Where-Object { $_.FullName -notmatch '\\config\\migrations\\' } |
  Select-String -Pattern 'dbo\.|\bGETDATE\s*\(|\bDATEDIFF\s*\(|\bTOP\s*\(|OFFSET\s+\d+\s+ROWS\s+FETCH' -CaseSensitive:$false |
  Select-Object Path, LineNumber, Line
```

Pass criteria:

- No matches in runtime paths used by production flows.
- Matches inside explicit `if ($this->isPgsql()) ... else ...` fallback branches can be treated as intentional SQL Server compatibility.
- If matches remain in debug-only files, mark them as non-blocking or update them.

## 8) Boot local app and run smoke checks

If Apache is already serving `http://localhost/student_violations`, use that. Otherwise:

```powershell
php -S localhost:8080
```

Then in another terminal, run:

```powershell
Invoke-WebRequest "http://localhost/student_violations/index.php?page=login" -UseBasicParsing | Select-Object StatusCode
Invoke-WebRequest "http://localhost/student_violations/index.php?page=student_oauth_callback" -UseBasicParsing -MaximumRedirection 0
```

Pass criteria:

- Login page responds (HTTP 200).
- OAuth callback route does not crash with HTTP 500.

## 9) Manual feature verification (required)

Verify in browser:

1. Student OAuth login works with `@fairview.sti.edu.ph`.
2. First-time student auto-creates a row in `students`.
3. Returning student logs in without duplicate row creation.
4. Student can open appointment page and submit appointment request.
5. Officer can see appointments list and update status.
6. Violations pages load without SQL errors.

Pass criteria:

- No fatal PHP errors.
- No SQL syntax errors in page output and server logs.

## 10) Prepare Render-safe configuration

Before creating Render service, verify these final values are ready to copy:

- `DB_DRIVER=pgsql`
- `DB_HOST`, `DB_PORT=5432`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSLMODE=require`
- Microsoft OAuth keys and production `MICROSOFT_REDIRECT_URI`
- `APP_ENV=production`

Pass criteria:

- You can populate all required Render env vars without guessing.

## 11) Commit this readiness checkpoint

Run:

```powershell
git add .
git commit -m "Add strict Supabase readiness checklist and validate local pgsql switch"
git push -u origin chore/supabase-readiness
```

Pass criteria:

- Branch pushed successfully.
- Team can follow one checklist file end-to-end.
