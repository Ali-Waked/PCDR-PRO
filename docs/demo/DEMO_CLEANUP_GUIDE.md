# Demo dataset cleanup guide

Cleanup removes **only records and generated files that the demo seeder itself registered** in the private manifest (`settings` group `demo_registry`). It must never be run against production.

## Guards

`php artisan pcdr:demo:cleanup` calls `DemoSeedManifest::assertCleanable()` before it reads or deletes anything. It refuses if any of these is true:

- `app()->isProduction()` or `app()->environment('production')`;
- `config('app.env') === 'production'` or `env('APP_ENV') === 'production'`;
- `config('preview.demo_seed') !== true` (the `PCDR_DEMO_SEED=true` opt-in).

`PCDR_PREVIEW_INIT` is not required for cleanup because an already-seeded preview database may be cleaned after initialization flags are removed; `PCDR_DEMO_SEED` remains mandatory.

## 1. Always preview first

```bash
php artisan pcdr:demo:cleanup --dry-run
```

The command prints a table grouped by table/disk with the exact counts of demo-owned rows, pivot rows and generated files, plus the number of private ownership markers. `--dry-run` is read-only: no rows, files or markers are deleted.

## 2. Confirm and delete

Interactive confirmation:

```bash
php artisan pcdr:demo:cleanup
```

The command shows the full summary first, then asks `Delete only the preview-owned records and files shown above?`. Answering anything other than yes cancels with no changes.

Non-interactive confirmation:

```bash
php artisan pcdr:demo:cleanup --force
```

`--force` is treated as the explicit confirmation; it does not bypass any production or flag guard.

## 3. What is deleted

Inside a database transaction, the command deletes:

1. Demo-owned pivot rows, in reverse insertion order, matched only by the exact JSON attributes stored in `pivot:<table>:<hash>` markers.
2. Demo-owned rows, in reverse insertion order, matched by table and primary key from `row:<table>:<id>` markers. This includes rows inserted once into append-only tables (domain, inventory, workflow, accountability, referral, document-review events and audit events).
3. Every `settings` row in group `demo_registry` (row/pivot/file/key/run markers).

After the database transaction succeeds, generated media files are deleted one by one. A file is removed only if it still exists on the recorded disk **and** the SHA-256 of its current bytes matches the manifest checksum. Otherwise it is preserved and reported as skipped. Finally the RBAC cache is invalidated so deleted demo role assignments stop affecting access checks.

No `truncate`, `drop`, `fresh`, `wipe`, `migrate:rollback` or raw table reset is used. Administrator-created records, reference seeder rows and untracked files are never deleted.

## 4. After cleanup

```bash
php artisan pcdr:demo:verify
```

The verifier should report missing completion markers and zero tracked rows; this is expected after a successful cleanup. Re-run `php artisan pcdr:demo:cleanup --dry-run` to confirm that no ownership markers remain.

Public pages should still load using reference seeder content. Accounts and records created after seeding by an administrator should still be present. If a file was skipped because it was edited, remove it manually only after inspecting it.

## 5. Practical cautions

- Take a database backup before cleanup if the preview database also contains manual test data you want to keep.
- Do not edit demo-owned rows expecting cleanup to find them by business keys; cleanup deliberately matches only registered IDs/pivots and checksum-verified paths.
- Do not reuse the demo credentials in another environment; see `DEMO_ACCOUNTS.md`.
- If cleanup fails midway, the database is rolled back. Files are deleted only after the transaction commits, so a database failure leaves files intact.
