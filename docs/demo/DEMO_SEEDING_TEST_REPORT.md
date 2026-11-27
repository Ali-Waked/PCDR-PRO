# Demo seeding test report

## Scope and status

This document is a **user-run verification template**. Static review was performed on the seeders, guards, ownership manifest and documentation; no migration, seeder, test suite, tinker command, local server, browser automation or screenshot was run by the assistant. Runtime verification must be performed by the user in a local/preview environment.

Environment under test (fill in):

- Branch: `main`
- PHP version: `php -v` → ____________________
- Database driver/version: ____________________
- `APP_ENV`: ____________________ (must not be `production`)
- `PCDR_PREVIEW_INIT`: `true`
- `PCDR_DEMO_SEED`: `true`

## Static review checklist

- [x] Migrations are the schema source of truth; no demo migration or schema change was added.
- [x] Models are the behavior source of truth; no model was modified for demo visibility.
- [x] `DatabaseSeeder` does not call `DemoDatabaseSeeder` or `DemoAdminSeeder`.
- [x] Production refusal checks `app()->isProduction()`, `app()->environment('production')`, `config('app.env')` and `env('APP_ENV')`.
- [x] Seeding requires both `preview.initialize === true` and `preview.demo_seed === true`.
- [x] Ownership is stored only in private `settings` group `demo_registry`; it is absent from `config/settings.php`, API resources and Inertia props.
- [x] Cleanup matches only manifest rows/pivots and checksum-verified files; no truncate/drop/reset.
- [x] Append-only tables are inserted once and never updated on rerun.
- [x] All 17 source JPEGs are local, readable and documented with licenses/checksums.
- [x] Internal status vocabularies and role keys were checked against `config/operations.php`, `config/phase5.php`, `config/workflows.php` and `CmsRolesSeeder`.
- [x] PHP syntax was checked on every changed PHP file; Pint was run on changed demo PHP files only.

## User-run commands and expected results

### 1. Production guard (without changing production data)

```bash
APP_ENV=production php artisan db:seed --class="Database\\Seeders\\Demo\\DemoDatabaseSeeder" --force --no-interaction
```

Expected: command fails before writes with a refusal mentioning `APP_ENV=production`. If the shell does not override cached config, run `php artisan config:clear` first in the local environment.

Result: PASS / FAIL — notes: ____________________

### 2. Fresh initialization

```bash
php artisan pcdr:preview:initialize --no-interaction
```

Expected: migrations apply; reference seeders and the demo dataset run; final line reports successful initialization. Setting `PCDR_DEMO_SEED=false` for a separate run should seed only reference data.

Result: PASS / FAIL — notes: ____________________

### 3. Read-only verification

```bash
php artisan pcdr:demo:verify
```

Expected: `Demo dataset verification passed.` Errors = 0. Warnings should be explained in notes. Coverage should list all required tables; JPEGs should pass checksum and MIME checks.

Result: PASS / FAIL — notes: ____________________

### 4. Idempotency

```bash
php artisan db:seed --class="Database\\Seeders\\Demo\\DemoDatabaseSeeder" --force --no-interaction
php artisan pcdr:demo:verify
```

Expected: no duplicate rows or duplicate manifest markers; verification still passes with identical counts. Append-only event rows keep their original IDs and timestamps.

Result: PASS / FAIL — notes: ____________________

### 5. Cleanup preview

```bash
php artisan pcdr:demo:cleanup --dry-run
```

Expected: summary table lists only manifest-owned rows/pivots/files; no deletion occurs.

Result: PASS / FAIL — notes: ____________________

### 6. Cleanup execution

```bash
php artisan pcdr:demo:cleanup --force
```

Expected: reported row/pivot/file counts match the dry run; demo accounts are gone; administrator-created and reference records remain; generated media files with matching checksums are removed.

Result: PASS / FAIL — notes: ____________________

### 7. Post-cleanup state

```bash
php artisan pcdr:demo:cleanup --dry-run
```

Expected: no preview-owned records or files are registered; nothing was changed.

Result: PASS / FAIL — notes: ____________________

## Manual application checks (user-run)

- [ ] Public home, news, projects, publications and contact pages render with natural Arabic/English copy and no visible demo/fictional/rehearsal/sample wording.
- [ ] All 17 media thumbnails and detail images load from local paths; none are broken or remote.
- [ ] Login works with the development-only credentials in `DEMO_ACCOUNTS.md`; suspended account is refused; owner-only screens behave correctly.
- [ ] Complaint, inquiry, directory, operations and phase-5 screens show coherent linked records where the feature gate normally exposes them.
- [ ] `operations` and `phase5` remain hidden behind their existing dashboard gates; seeding did not flip any gate or menu visibility.
- [ ] No ownership marker, manifest key, internal note or `.test` contact appears in public frontend props/responses.
- [ ] After cleanup, reference content remains visible and no demo-owned record is reachable.

## Sign-off

| Check | Result | Notes |
| --- | --- | --- |
| Production refusal |  |  |
| Seeding + verification |  |  |
| Re-seed idempotency |  |  |
| Cleanup + preservation |  |  |
| Frontend review |  |  |

Signed (user): ____________________ Date: ____________________
