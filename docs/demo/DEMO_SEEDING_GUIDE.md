# Demo dataset seeding guide

The demo dataset is an **explicit opt-in** for local development and preview environments only. It is not called by `DatabaseSeeder`, is not invoked by application boot, and is refused in production before any write occurs.

## 1. Prerequisites

- Work on a local/preview database that has been migrated. The command below applies migrations through `pcdr:preview:initialize` without wiping or resetting anything.
- Do not use a production database, a production `.env`, or `APP_ENV=production`.
- Set both opt-in flags in the **local** environment before seeding:

```dotenv
APP_ENV=local
PCDR_PREVIEW_INIT=true
PCDR_DEMO_SEED=true
```

`config/preview.php` reads these as `preview.initialize` and `preview.demo_seed`. Both must be exactly `true`. `Database\Seeders\Demo\DemoDatabaseSeeder` calls `DemoSeedManifest::assertSeedable()`, which throws before any write if either flag is missing or if production is detected via `app()->isProduction()`, `app()->environment('production')`, `config('app.env')`, or `env('APP_ENV')`.

## 2. One-command initialization

```bash
php artisan pcdr:preview:initialize --no-interaction
```

This command:

1. Refuses immediately if demo seeding was requested while `APP_ENV=production`.
2. Runs `migrate --force --no-interaction`.
3. Runs the normal `DatabaseSeeder` when `PCDR_PREVIEW_INIT=true`; it already skips demo seeding.
4. Runs the dedicated `DemoDatabaseSeeder` only when `PCDR_DEMO_SEED=true` as well.
5. Records a private `preview.initialized_at` marker so later boots do not re-seed.

Initialization is wrapped in a database transaction; guard failures are fatal, not silently skipped.

## 3. Dedicated seeding command

If migrations and reference seeders are already present, the dataset can be seeded explicitly:

```bash
php artisan db:seed --class="Database\\Seeders\\Demo\\DemoDatabaseSeeder" --force --no-interaction
```

`DemoDatabaseSeeder` runs `pcdr:permissions:sync`, then the existing reference seeders (`CmsRolesSeeder`, `SiteSettingsSeeder`, `WebsiteSeeder`, the three homepage seeders and `EmailTemplatesSeeder`) so it also works on a freshly migrated database. Those reference rows are not demo-owned and are not removed by demo cleanup. It then runs, in dependency order:

1. `DemoUsersSeeder` — accounts, role assignments, role scopes, email-change history.
2. `DemoMediaSeeder` — 17 locally committed, licensed JPEGs copied into the media library.
3. `DemoSettingsSeeder` — private internal settings, supplementary public contact settings.
4. `DemoContentSeeder` — pages, programs, projects, categories, tags, articles, publications, revisions, homepage items and media pivots.
5. `DemoEngagementSeeder` — inquiries, notes, replies, delivery logs, notifications, sessions and audit lifecycle records.
6. `DemoComplaintsSeeder` — complaints, status history, notes and attachments.
7. `DemoDirectorySeeder` — partner categories, partners and translations.
8. `DemoOperationsSeeder` — locations, inventory, distribution, volunteers, committees, partners, grants, M&E and operational translations.
9. `DemoPhase5Seeder` — workflows, tasks, accountability, referrals, notifications, automations, reviews and integrations.

Finally it writes `run:version` and `run:completed_at` to the private manifest. See `DEMO_DATA_CATALOG.md` for exact row counts.

## 4. Idempotency and ownership

`App\Support\DemoSeedManifest` stores ownership in the existing private `settings` table under group `demo_registry`. It uses:

- `row:<table>:<id>` — one JSON marker per demo-created row, including generated IDs and fixed ULIDs.
- `key:<module>:<natural key>` — stable natural-key aliases (users by account key **and** email, media by slug, etc.).
- `pivot:<table>:<hash>` — JSON match attributes for demo-created pivot rows.
- `file:<sha256>` — disk, path and checksum for every generated media file.
- `run:version`, `run:completed_at` — completion markers.

On reruns, tracked rows are reused. Untracked existing rows are reused for reference lookups but are **never adopted as demo-owned**. Append-only tables (domain/inventory/workflow/accountability/referral/document-review events and audit logs) are inserted once with fixed timestamps and are never updated. The manifest group is absent from `config/settings.php`, so it is not published by `SettingsService`, API resources, Inertia props or model columns.

## 5. Visibility rules

- Public-facing copy is natural institutional Arabic and English. No visible label marks records as demo, fictional, rehearsal or sample.
- Ownership markers, internal notes and the preview flag are private and are never exposed in frontend props.
- The `operations` and `phase5` dashboard features stay hidden in `config/dashboard_features.php`. Their tables are seeded, but no gate, permission or menu visibility is flipped.
- All 17 photographs are local, real and licensed; see `IMAGE_SOURCES_AND_LICENSES.md`. No remote image URLs or placeholders are used.

## 6. Verification

After seeding, run the read-only verifier:

```bash
php artisan pcdr:demo:verify
```

It checks manifest completion, tracked-row resolution, table coverage, FK integrity, JPEG checksums/readability, visible-label wording and isolation of `DatabaseSeeder`. It does not modify data. A template for manual browser and workflow checks is in `DEMO_SEEDING_TEST_REPORT.md`.

## 7. Removal

Always preview the impact first:

```bash
php artisan pcdr:demo:cleanup --dry-run
```

Then remove tracked data with explicit confirmation:

```bash
php artisan pcdr:demo:cleanup --force
```

See `DEMO_CLEANUP_GUIDE.md` for the exact guarantees and safe-use notes.
