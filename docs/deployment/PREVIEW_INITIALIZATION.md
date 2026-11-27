# PCDR — Preview database initialization and DemoAdminSeeder

This document covers the **temporary Render Free preview** only. It is not a
production provisioning guide. No shell, SSH, one-off jobs, or pre-deploy
commands are required on Render Free.

---

## 1. DemoAdminSeeder

`database/seeders/DemoAdminSeeder.php` creates the initial Platform Owner for
the preview:

| Field | Value |
|---|---|
| Name | `Ali waked` |
| Email | `info@pcdr.org` |
| Password | `mydtringPassword1@` (stored only as a Laravel `Hash::make()` hash) |
| Status | `active`, email verified, locale `ar` |
| Authorization | `is_platform_owner = true` (the project's existing Platform Owner mechanism) |

Implementation details:

- Uses the existing `App\Models\User`, `App\Services\RbacService`, and
  `App\Services\AuditService`; it does **not** create a new role or a second
  authorization system. The `platform_owner` role remains virtual, as in
  `RbacService::rolesFor()`.
- Mirrors `pcdr:platform-owner:bootstrap`: a `DB::transaction` with
  `lockForUpdate()` rechecks, RBAC cache invalidation, and the
  `platform_owner.bootstrapped` audit event. The password is never written to
  audit logs or console output.
- Idempotent: if the demo owner already exists, it returns without changes.
- Conflict-safe: it never overwrites an existing password, never promotes an
  existing ordinary user, and refuses to add a second Platform Owner. Conflicts
  raise a `RuntimeException` with a message that contains no credentials.

> **Security note:** these credentials are intentionally hardcoded for a
> temporary preview, as requested. They must be rotated/removed before any
> real production or Hostinger deployment and must never be reused.

### Manual execution

```bash
php artisan db:seed --class=DemoAdminSeeder --force
```

Running it again is safe. To compensate a conflict, resolve the conflicting
account outside the seeder; never delete or overwrite unrelated users.

### Verification

```bash
php artisan test --compact tests/Feature/DemoAdminSeederTest.php \
    tests/Feature/PreviewInitializationTest.php
```

On the running preview, check the Render log stream for
`Demo Platform Owner is ready (info@pcdr.org).` and sign in at `/login` with
the credentials above. Locally you can also query the SQLite file directly.

---

## 2. Safe preview initialization

`app/Console/Commands/InitializePreviewDatabaseCommand.php` provides the
explicit mechanism (`php artisan pcdr:preview:initialize`):

1. Always runs `php artisan migrate --force` (non-destructive; no
   `migrate:fresh`, `db:wipe`, `migrate:reset`, or seeders run inside migrate).
2. Seeds **only** when `PCDR_PREVIEW_INIT=true`.
3. Uses a marker row in the `settings` table
   (`group=preview`, `key=initialized_at`) so seeding happens once for a given
   database, even across container/service restarts.
4. Calls `DatabaseSeeder`, which already contains idempotent reference/content
   seeders. `DemoAdminSeeder` is called from `DatabaseSeeder` only when
   `PCDR_PREVIEW_INIT=true`, so normal production seeding is unchanged.
5. Wraps seeding plus marker creation in one transaction. Conflicts are logged
   as warnings and do not prevent the web service from starting; unexpected
   failures fail the command so they are visible.
6. Existing seeders (`SiteSettingsSeeder`, `WebsiteSeeder`,
   `HomepageContentSeeder`, `RedesignHomepageContentSeeder`,
   `HomepagePrototypeContentSeeder`, `CmsRolesSeeder`,
   `EmailTemplatesSeeder`) were not removed, renamed, or made destructive.

There is deliberately **no** initialization on HTTP requests and no dependency
on Render Shell or one-off jobs.

---

## 3. Render Free configuration

The current deployment failure (`etc/nginx/conf.d/default.conf: nonexistent
directory`) shows that Render is not running the Docker image: that path is
written only by `docker/entrypoint.sh` and the nginx directory is created by
the `nginx` apk package inside the Docker image. On Render's native PHP runtime
it does not exist. The nginx/Docker files were **not** changed as part of this
work; use the native PHP configuration below for the preview.

| Render setting | Value |
|---|---|
| Runtime | Native PHP (not Docker) |
| Build Command | `composer install --no-dev --prefer-dist --optimize-autoloader && npm ci --ignore-scripts && npm run build` |
| Start Command | See below |
| Health Check Path | `/up` |
| Pre-Deploy / Shell / One-Off Jobs | Not used |

**Exact Start Command:**

```sh
DB_FILE="${DB_DATABASE:-$PWD/database/database.sqlite}"; mkdir -p "$(dirname "$DB_FILE")"; touch "$DB_FILE"; php artisan pcdr:preview:initialize --no-interaction && php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
```

Required environment variables:

| Variable | Value |
|---|---|
| `APP_KEY` | Generated per environment; never committed |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | Canonical HTTPS preview URL |
| `DB_CONNECTION` | `sqlite` |
| `DB_DATABASE` | Absolute path to the SQLite file, or unset to use Laravel's `database_path()` default |
| `CACHE_STORE` | `database` |
| `SESSION_DRIVER` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `LOG_CHANNEL` | `stderr` |
| `PCDR_PREVIEW_INIT` | `true` (the explicit preview opt-in; leave unset in real production) |

This command works on repeated restarts: migrations are idempotent, the marker
prevents duplicate seeding, and the demo seeder is itself idempotent. SQLite is
ephemeral on Render Free — data resets on redeploy/restart; do not store real
data.

### Local Docker equivalent

The Docker entrypoint now calls `pcdr:preview:initialize` by default
(`RUN_INITIALIZATION=1`). Set `PCDR_PREVIEW_INIT=true` to seed the preview, or
`RUN_INITIALIZATION=0` to fall back to migrations only.

---

## 4. Initialization failures

| Symptom | Cause | Action |
|---|---|---|
| `a different Platform Owner already exists` | A real owner is present | Do nothing; do not demote or overwrite it. Remove preview opt-in if unexpected. |
| `the configured email belongs to an existing account` | `info@pcdr.org` is taken | Resolve the conflict outside the seeder; never rewrite that user's password. |
| Migration error in logs | Schema/DB path problem | Check `DB_DATABASE`, file writability, and the migration log; fix and redeploy. |
| Seeder conflict warning but site is up | Expected safe-skip | Verify login; if the account is missing, resolve the conflict and redeploy. |
| Duplicate-looking content | Marker was lost (new DB) | Expected on ephemeral SQLite; seeders are idempotent, so no duplicate rows are created. |

### Resetting the preview database

- **Render Free:** redeploy/restart the service; the ephemeral SQLite file is
  recreated and initialization runs again.
- **Local only:** stop the app, delete the temporary SQLite file you created
  for testing, then run the Start Command steps again. Never run
  `migrate:fresh`, `db:wipe`, or any destructive reset against a real database.
