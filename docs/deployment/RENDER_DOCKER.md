# PCDR — Docker preview on Render Free (SQLite, temporary)

This document describes a **temporary public preview** of PCDR on Render Free.
It is deliberately portable: the image contains no Render-specific application
code, and the same image can be run on Hostinger or any other Docker host.
The SQLite database is ephemeral and must **not** be treated as production
storage.

---

## 1. Docker architecture

Multi-stage `Dockerfile`:

| Stage | Base | Purpose |
|---|---|---|
| `php-base` | `php:8.4-fpm-alpine` | PHP 8.4 + required extensions, nginx, supervisor, SQLite tools |
| `vendor` | `php-base` | `composer install --no-dev --optimize-autoloader`, `package:discover` |
| `assets` | `node:22-alpine` | `npm ci` + `npm run build` (Vite 8 requires Node >= 22.12) |
| `runtime` | `php-base` | App code, production `vendor/`, built `public/build`, server configs |

Runtime process tree:

```
PID 1: supervisord
  ├── php-fpm (127.0.0.1:9000, never published)
  └── nginx (listens on ${PORT}, default 8080)
```

- `docker/entrypoint.sh` renders the nginx template with the runtime `PORT`,
  prepares writable directories, creates the SQLite file, runs non-destructive
  migrations, warms Laravel caches, then `exec`s supervisor (correct SIGTERM
  handling and graceful shutdown).
- nginx and PHP-FPM write access/error logs to stdout/stderr.
- No `.env` is copied into the image; all configuration comes from runtime
  environment variables.
- No development Composer or npm packages are present in the runtime image.

### PHP extensions

Base image provides: `ctype`, `dom`, `fileinfo`, `filter`, `hash`, `iconv`,
`json`, `libxml`, `mbstring`, `openssl`, `pcre`, `phar`, `session`,
`tokenizer`, `xml`, `xmlwriter`, `sqlite3`, `pdo_sqlite`, Zend OPcache.

Installed explicitly: `bcmath`, `intl`, `pcntl`, `pdo_mysql`, `pdo_pgsql`,
`zip` (`ZipArchive` is used by `AuditArchiveService`). The build fails if any
required extension is missing.

---

## 2. Build and run locally

Docker is not available in the environment where this document was written,
so these commands are provided for a machine that has Docker installed.

```bash
# Build (requires network access: npm/Vite downloads the configured Bunny font)
docker build -t pcdr-preview .

# Generate a throwaway key for local testing; never commit it.
KEY="$(php artisan key:generate --show)"

docker run --rm --name pcdr-preview -p 8080:8080 \
  -e APP_KEY="$KEY" \
  -e APP_URL=http://localhost:8080 \
  pcdr-preview

curl -fsS http://localhost:8080/up
docker logs pcdr-preview

# Graceful shutdown: supervisor forwards SIGTERM to nginx and PHP-FPM.
docker stop pcdr-preview
```

To override the internal port:

```bash
docker run --rm -p 9000:9000 -e PORT=9000 -e APP_KEY="$KEY" \
  -e APP_URL=http://localhost:9000 pcdr-preview
```

---

## 3. Render Web Service configuration

| Render setting | Value |
|---|---|
| Service type | Web Service |
| Runtime | Docker (build from repository `Dockerfile`) |
| Plan | Free (temporary preview) |
| Build command | None — Docker build is used |
| Start command | None — the image `CMD` starts supervisor |
| Health Check Path | `/up` |
| Persistent disk | Not used |
| SSH / workers / cron | Not required for the preview |

Render injects `PORT`; the entrypoint renders nginx to listen on it. `EXPOSE
8080` is only the local default and does not conflict with Render.

### Required environment variables on Render

| Variable | Value | Notes |
|---|---|---|
| `APP_KEY` | Generated per environment | `php artisan key:generate --show`; paste the result into the Render dashboard. Never commit it. |
| `APP_ENV` | `production` | Image default, can also be set explicitly. |
| `APP_DEBUG` | `false` | Image default; do not enable on a public service. |
| `APP_URL` | `https://<your-service>.onrender.com` | Use the canonical public URL. |
| `DB_CONNECTION` | `sqlite` | Image default; no external database needed. |
| `DB_DATABASE` | `/var/www/html/database/database.sqlite` | Must be absolute. Image default. |
| `CACHE_STORE` | `database` | Uses framework `cache` table in SQLite. |
| `SESSION_DRIVER` | `database` | Uses framework `sessions` table in SQLite. |
| `QUEUE_CONNECTION` | `database` | Jobs are stored but **not executed** in this preview. |
| `LOG_CHANNEL` | `stderr` | Sends Laravel logs to the Render log stream. |
| `RUN_INITIALIZATION` | `1` | Default; runs `pcdr:preview:initialize` (migrations + opt-in preview seeding). |
| `RUN_MIGRATIONS` | `1` | Fallback when `RUN_INITIALIZATION=0`; migrations only. |
| `PCDR_PREVIEW_INIT` | `true` for the temporary preview | Explicit opt-in for one-time seeding; leave unset in real production. |

Optional variables: `MAIL_MAILER=log` (default, no SMTP needed for preview),
`APP_LOCALE=ar`, `APP_FALLBACK_LOCALE=en`, `PCDR_HIDDEN_MODULES_ACCESS=false`.
Never place real SMTP credentials, CMS bootstrap passwords, or owner bootstrap
values in the repository.

Deployment: connect the repository, select Docker runtime, add the variables
above, and deploy. Rollback is a Render redeploy of the previous image; no
database rollback is automatic.

### Root cause of `conf.d/default.conf: nonexistent directory`

The error was raised by `docker/entrypoint.sh` (the `envsubst` redirect at
lines 18-20). Because that script only exists at `/usr/local/bin/entrypoint.sh`
after the Dockerfile `COPY`, this confirms Render **is** building and running
the intended Docker image. The base is `php:8.4-fpm-alpine`; Alpine's `nginx`
package loads virtual hosts from `/etc/nginx/http.d` and creates that
directory, but does **not** create the Debian-style `/etc/nginx/conf.d`. The
entrypoint wrote the rendered site to the wrong path, so startup failed before
Laravel ran.

The fix writes the rendered server block to `/etc/nginx/http.d/default.conf`,
creates both nginx include directories, and runs `nginx -t` before supervisor
starts nginx and PHP-FPM. **No Start Command change is required**: keep Docker
runtime, no Build/Start Command overrides, Health Check `/up`.

---

## 4. SQLite limitations (read before using the preview)

- The SQLite file lives in `/var/www/html/database/database.sqlite`, created at
  container startup. It is **inside the container filesystem**: Render Free
  resets it on redeploy, restart, and after the service sleeps due to
  inactivity. **All content entered through the preview can disappear.**
- Do not attach a persistent disk: the preview intentionally depends on none.
- Do not seed real user data. The entrypoint/Start Command runs
  `pcdr:preview:initialize`, which applies migrations and seeds reference/demo
  data **once per database** only with `PCDR_PREVIEW_INIT=true`. See
  `docs/deployment/PREVIEW_INITIALIZATION.md`.
- Do not run multiple instances/replicas. Each would get its own private
  SQLite database and sessions would not be shared.
- Uploaded files under `storage/app/public` are equally ephemeral (`public/storage`
  is a symlink inside the image). If uploads matter beyond a preview, use an
  S3-compatible disk; the application already has an `s3` filesystem disk
  configured, but the Flysystem S3 package must be added to Composer first.
- Changing `APP_KEY` invalidates previously encrypted data/sessions.

Migrations are safe for a fresh preview database: all 30 migrations were
verified against SQLite on a temporary file (143 tables, no pending
migrations). For a shared external database, set `RUN_MIGRATIONS=0` and run
`php artisan migrate --force` exactly once as a separate deployment step.

---

## 5. Health check

`/up` is Laravel's built-in health endpoint (configured in
`bootstrap/app.php`). It returns HTTP 200 with "Application up" while the app
is not in maintenance mode, and 503 during maintenance. It does not require a
database query, so it is suitable as a liveness check.

- Render: set **Health Check Path** to `/up`.
- Docker: the image also declares a `HEALTHCHECK` using `curl` against
  `http://127.0.0.1:${PORT}/up` (Render ignores this and uses its own check).

---

## 6. Hostinger portability

Nothing in `app/`, `config/`, or `routes/` was changed for Render. The image
uses only standard Laravel environment variables, so it can be reused on
Hostinger if Docker is available. If Hostinger uses a classic PHP host instead:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci --ignore-scripts
npm run build
php artisan package:discover
```

Deploy the project with `public/` as the document root, place `.env` outside
the web root, make `storage/` and `bootstrap/cache/` writable by the PHP user,
create the storage link with `php artisan storage:link`, and run
`php artisan migrate --force` once.

For Hostinger/production use a real database:

```
DB_CONNECTION=mysql     # or mariadb/pgsql — already supported by config/database.php
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
RUN_MIGRATIONS=0        # run migrations as an explicit, one-time step
```

Keep the same `APP_KEY`, provide SMTP settings for real mail, and run a
separate `php artisan queue:work` process plus cron `php artisan schedule:run`
if queued work/scheduling is enabled. Review the project's own production gate
in `docs/architecture/DEPLOYMENT_ARCHITECTURE.md` before go-live.

---

## 7. Validation performed and limitations

Checks actually run in the local workspace (no Docker binary available):

| Check | Result |
|---|---|
| `composer validate --no-check-publish` | Passed |
| `npm run build` | Passed — 801 modules, `public/build/manifest.json` + hashed assets |
| Asset-stage simulation (only package files + `resources/`) | Passed — 12 files in `build/` |
| SQLite migrations on a temporary DB | Passed — 30 migrations / 143 tables / 0 pending |
| HTTP health endpoint via PHP dev server | Passed — `GET /up` → HTTP 200 "Application up" |

Not performed locally because the required binaries are unavailable: Docker
image build, container startup, nginx `nginx -t`, and supervisor config parse.
These must be run on a Docker-capable host before relying on the preview.

---

## 8. Common problems

| Symptom | Cause / fix |
|---|---|
| Container exits: "APP_KEY is required" | Set `APP_KEY` in the Render dashboard; do not put it in the repo. |
| 502 Bad Gateway briefly after boot | PHP-FPM starts before nginx; wait for the health check. Persistent 502 means FPM crashed — check logs. |
| 500 after env change | Config cache is built at boot; redeploy/restart so the entrypoint rebuilds it. |
| Data disappears | Expected with ephemeral SQLite on Render Free; see §4. |
| 413 on upload | nginx/PHP limit is 10 MB (`client_max_body_size`, `upload_max_filesize`). |
| Missing CSS/JS | Vite build failed (check the build log); the Bunny font plugin needs network access at build time. |
| Migration failure at boot | Check the Render log stream; fix the migration and redeploy. Use `RUN_INITIALIZATION=0` (then `RUN_MIGRATIONS=0`) for a shared DB. |
| `etc/nginx/conf.d/default.conf: nonexistent directory` | Fixed in the entrypoint: Alpine nginx uses `/etc/nginx/http.d`. Redeploy the rebuilt image; do not add a native PHP Start Command. |
| 503 on `/up` | Application is in maintenance mode; run `php artisan up` in the container/host. |
