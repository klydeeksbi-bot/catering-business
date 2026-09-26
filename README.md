## 3YOS Catering

Laravel serves the existing Blade interface and business workflows. Supabase provides PostgreSQL, Auth for admin accounts, and Storage for public catering images and contract uploads. Sensitive operations remain in Laravel; no Supabase service-role credential is sent to the browser, and no Edge Function is needed.

## Supabase Setup

1. In the [project SQL editor](https://supabase.com/dashboard/project/vnmzdahsonoshmrwhlym/sql/new), run [`database/supabase/migrations/20260926000000_initial_schema.sql`](database/supabase/migrations/20260926000000_initial_schema.sql). It creates the current schema, RLS policies, the `catering-media` public-read bucket, initial package rows, and Laravel's migration ledger.
2. In Supabase Project Settings, copy the project URL, anon key, service-role key, and PostgreSQL connection string into `.env`. Use the database connection string from Supabase's Connect panel with SSL required. Keep the service-role key and database password in server-side environment configuration only; never use a `VITE_` prefix for them.
3. Configure `.env` with `SUPABASE_URL`, `SUPABASE_ANON_KEY`, `SUPABASE_SERVICE_ROLE_KEY`, `SUPABASE_STORAGE_BUCKET=catering-media`, `DB_CONNECTION=pgsql`, and `SUPABASE_DB_URL`. Set `APP_URL`, generate `APP_KEY`, and keep `SESSION_ENCRYPT=true` so the Supabase session token is encrypted at rest. Configure `MAIL_*` for application email and `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET_KEY` for public reservations.
4. Disable public Auth sign-ups. Configure Supabase Auth email/SMTP settings and allow the password-recovery redirect `APP_URL/admin/reset-password/supabase`. Create the first admin identity in Supabase Auth, then link it to an app profile. For a new, empty app database, run this in the SQL editor after replacing the email and display name:

```sql
insert into public.users (name, email, password, role, supabase_user_id, created_at, updated_at)
select 'Site Administrator', email, 'supabase-auth-managed', 'full', id, now(), now()
from auth.users
where email = 'admin@example.com'
on conflict (email) do update
set supabase_user_id = excluded.supabase_user_id, role = 'full', updated_at = now();
```

The `password` profile column is retained for compatibility with the existing Laravel schema; Supabase Auth is the only production login/password authority. Team admin accounts created in the existing admin UI are provisioned in Supabase Auth automatically.

## Existing Data

Before changing the old app's database connection, create a JSON backup from its `/admin/backups` page. Copy the backup JSON to `storage/app/backups/` and copy its referenced files into `storage/app/public/` on the new deployment. Create Supabase Auth accounts with the same email addresses as the old admin profiles; existing Laravel password hashes cannot be used as Supabase passwords, so set new passwords or send Supabase recovery emails. After configuring the new database and keys, import once:

```powershell
php artisan supabase:import-backup backup-YYYYMMDDHHMMSS.json
```

The importer preserves entity IDs and relationships, maps matching Auth email addresses, uploads local images/contracts, and repairs PostgreSQL identity sequences. It refuses to overwrite populated application tables. Unmatched legacy admin profiles remain unlinked and cannot sign in until linked to a Supabase Auth identity. Create the primary admin profile after import using the SQL above, or promote a linked imported profile by updating its `role` to `full`.

## Run Locally

After applying the SQL migration and configuring `.env`:

```powershell
composer install
npm.cmd ci
php artisan key:generate
php artisan migrate --force
php artisan serve
```

In another terminal, run `npm.cmd run dev`; for a production frontend bundle run `npm.cmd run build`. `php artisan migrate --force` sees the schema migration ledger created by the SQL file and does not recreate the tables. Automated tests use an in-memory SQLite database and fake Supabase HTTP requests: run `php artisan test`.

## Data Access

The server-rendered app continues to use its existing Laravel routes, validation, authorization middleware, mail, and Eloquent models. Laravel connects to Supabase PostgreSQL using the trusted server-side `SUPABASE_DB_URL`; Supabase Auth validates admin passwords and provisions team users; Supabase Storage serves the existing image workflows. RLS is enabled on app and Laravel support tables. Policies allow public reads only for the public catalog/gallery, role-scoped reads and writes for authenticated admins, and no direct anonymous writes to reservations or inquiries (those forms remain validated and rate-limited through Laravel). Storage is public-read as before and full-admin-write through RLS; Laravel uploads use the private service-role key.