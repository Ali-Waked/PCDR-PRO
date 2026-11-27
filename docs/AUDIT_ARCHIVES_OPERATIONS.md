# Audit Archives Operations and Deployment

Status: implemented operator runbook
Audience: platform operators, deployment engineers and on-call maintainers
Applies to: `pcdr:audit:automation:check`, `GenerateAuditArchiveJob` and the audit archive UI

## 1. Runtime model

HTTP requests never generate archives. `POST /{locale}/admin/audit-archives` creates a durable `pending` row and dispatches `App\Jobs\GenerateAuditArchiveJob` to the `audit` queue. The same is true for automatic archiving: the scheduler only runs the lightweight due-check; it never builds a ZIP.

Two long-running pieces are required in production:

1. A scheduler invocation at least once per minute.
2. A queue worker consuming the `audit` queue with a timeout longer than the job timeout.

The job itself declares `$tries = 3`, `$timeout = 540` seconds and `$uniqueFor = 600` seconds. The worker timeout below is intentionally slightly longer than the job timeout.

## 2. Required commands

### Scheduler cron

Install this single cron entry for the PHP application user:

```cron
* * * * * php artisan schedule:run >> /dev/null 2>&1
```

The registered schedule is every 15 minutes with `withoutOverlapping(30)`, `onOneServer()` and `runInBackground()`. Laravel's `schedule:run` must still be called every minute so the 15-minute slot is evaluated.

### Queue worker

```bash
php artisan queue:work database --queue=audit --tries=3 --timeout=600
```

Run this under Supervisor/systemd in production. Do not rely on `queue:work --stop-when-empty` for scheduled automatic archives. If the default database queue connection is changed, keep the `database` argument aligned with `QUEUE_CONNECTION`.

### Manual commands

```bash
php artisan pcdr:audit:automation:check
php artisan pcdr:audit:automation:check --force
php artisan pcdr:audit:cleanup-due
php artisan schedule:list
php artisan route:list --name=audit-archives
```

`--force` is the only way the scheduler will retry a `failed` automatic period. Without it, failed periods are skipped and remain visible in the archive list for manual review.

## 3. Environment variables

The default queue connection is `database` and the default archive queue name is `audit`. No Redis is required.

```dotenv
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=660

PCDR_AUDIT_QUEUE_CONNECTION=
PCDR_AUDIT_QUEUE_NAME=audit
PCDR_AUDIT_ARCHIVE_DISK=private
PCDR_AUDIT_ARCHIVE_PATH=audit-archives

PCDR_AUDIT_EXPORT_SCHEMA_VERSION=1.0
PCDR_AUDIT_EXPORT_BATCH_SIZE=500
PCDR_DEPLOY_VERSION=
PCDR_DEPLOY_COMMIT=

PCDR_AUDIT_LIFECYCLE_EMAILS=
PCDR_AUDIT_RETENTION_ENABLED=false
PCDR_AUDIT_RETENTION_MONTHS=
PCDR_AUDIT_RETENTION_REQUIRE_DRY_RUN=true
PCDR_AUDIT_RETENTION_MIN_ARCHIVE_AGE_DAYS=7
PCDR_AUDIT_CLEANUP_BATCH_SIZE=500
PCDR_AUDIT_CLEANUP_WINDOW_START=01:00
PCDR_AUDIT_CLEANUP_WINDOW_END=05:00
PCDR_AUDIT_CLEANUP_TIME=03:30
```

`DB_QUEUE_RETRY_AFTER=660` is required because Laravel's default database `retry_after` is 90 seconds, which is shorter than the 540-second job timeout and would allow duplicate processing.

`PCDR_AUDIT_QUEUE_CONNECTION` may be left empty to use `QUEUE_CONNECTION`. If set, it must match the connection passed to `queue:work`.

The private disk must be writable and must not be publicly served. The download endpoint streams through PHP with `Cache-Control: no-store, private` and `X-Content-Type-Options: nosniff`.

## 4. Website Settings catalogue

Automatic archiving is configured in the CMS at **Site Settings → Audit archive automation**, backed by these private `website_settings` keys:

| Key | Type | Default | Purpose |
| --- | --- | --- | --- |
| `audit_automation.enabled` | boolean | `false` | Master switch. |
| `audit_automation.interval_months` | interval | `1` | Normal cadence: 1, 2 or 3 months. |
| `audit_automation.execution_day` | integer | `2` | Day of month, clamped to 1–28. |
| `audit_automation.execution_time` | time | `02:30` | Local execution time. |
| `audit_automation.timezone` | timezone | app timezone | Timezone for the day/time check. |
| `audit_automation.notifications_enabled` | boolean | `true` | Reserved for lifecycle notification preferences. |
| `audit_automation.lookback_months` | integer | `3` | Missed-run recovery window, 1–12. |

All seven are marked `public => false` in `SiteSettingsCatalog` and excluded from `SiteSettings::publicValues()`. They can only be changed by a CMS user with `settings.update`.

An interval of `N` means each of the previous `lookback_months` completed calendar months is archived as its own monthly ZIP when the current local month is divisible by `N`. The lookback window deliberately recovers missed scheduler runs. `verified`, `pending` and `processing` periods are skipped; `failed` periods are skipped unless `--force` is supplied.

## 5. Verification and failure handling

`GenerateAuditArchiveJob::handle()` calls `AuditArchiveService::processArchive()`. It:

1. locks the archive row;
2. verifies the month and expected attempt;
3. transitions `pending → processing`;
4. exports CSV/JSONL, writes the manifest, builds the ZIP and inspects checksums and row counts;
5. stores the ZIP on the private disk;
6. marks source events archived and transitions to `verified` in one transaction;
7. records lifecycle notifications.

Any exception transitions to `failed`, records the error and notifies owners. `failed()` on the job is idempotent: if `processArchive()` already recorded the failure, only the error text is refreshed; no duplicate notification is sent. Verified and pruned archives are never failed by a late job callback.

Central status rules are enforced in `AuditArchiveStatusService`: `verified → processing` and `pruned → verified` are rejected. Retention uses the same service for `verified → pruned`.

## 6. Deployment checklist

1. Run `php artisan migrate` and confirm `audit_archives` has `source`, `format`, `operation_key`, `queued_at`, `started_at`, `finished_at` and `failed_at`, plus the `audit_archive_status_transitions` table.
2. Run `php artisan config:clear && php artisan config:cache`.
3. Set the environment values above, especially `DB_QUEUE_RETRY_AFTER=660`.
4. Start the `audit` queue worker under a process supervisor.
5. Install the cron entry for `schedule:run`.
6. Confirm `php artisan schedule:list` shows `pcdr:audit:automation:check` every 15 minutes and `pcdr:audit:cleanup-due` daily.
7. Confirm `php artisan route:list --name=audit-archives` lists index, store, show, update, download, transition and verify routes.
8. Log in as an administrator, open **Audit archives**, read the blue info alert, open **Help / User guide** and queue a test archive for the previous complete month.
9. Watch the queue worker log until the row becomes `verified`, then download once and confirm the `audit_archives.download` audit event.

## 7. Troubleshooting

| Symptom | Check |
| --- | --- |
| Rows stay `pending` | Worker not running, wrong queue name, or failed connection. Check `queue:work --queue=audit`. |
| Rows stay `processing` | Worker timeout below 540 seconds, or job killed before verification. Check `DB_QUEUE_RETRY_AFTER=660` and worker logs. |
| Repeated duplicate attempts | `retry_after` shorter than the job timeout, or multiple workers on the same period. Job uniqueness lasts 600 seconds. |
| Automatic archiving does nothing | `audit_automation.enabled` false, local day before `execution_day`, or current month not divisible by `interval_months`. Run the check command manually and read its output. |
| Failed automatic period never retries | Run `pcdr:audit:automation:check --force`, or retry manually from the UI with a reason. |
| Download refused | Status is not `verified`, file missing from private disk, or checksum/manifest mismatch. Open **View details** for the recorded error. |
| Settings not visible in CMS | User lacks `settings.view`; private settings never reach the public site payload. |
