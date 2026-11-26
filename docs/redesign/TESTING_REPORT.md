# PCDR PRO — Testing Report

**Date:** 2026-11-26
**Workspace:** `/root/PCDR/pcdr-pro`
**Governance:** `NOT CLEARED`. Test evidence is technical only; it does not approve ADRs, close OQ gates, authorize Phase 6, or imply deployment.

---

## 1. Focused redesign verification

| Command | Result |
|---|---|
| `php artisan test --compact tests/Feature/RedesignHomepageApiTest.php tests/Feature/RedesignHomepageCmsTest.php tests/Feature/RedesignHomepageSeederTest.php tests/Feature/RedesignHomepageFrontendTest.php tests/Feature/HomepageBackendIntegrationTest.php` | **27 passed / 389 assertions** |
| `php artisan test --compact tests/Feature/RedesignHomepageFrontendTest.php` | 2 passed / 97 assertions |
| `npm run build` | Passed — 826 modules transformed, exit 0 |
| `vendor/bin/pint --format agent <each changed PHP file>` | Passed; no formatting findings |

Focused coverage includes:

- public JSON locale (`ar`/`en`) and `dir` (`rtl`/`ltr`) output;
- draft, disabled and unpublished exclusion;
- cache invalidation after CMS update;
- status, toggle and reorder permission enforcement;
- CMS preview permission and no public draft exposure;
- unsafe URL rejection and safe item replacement;
- media-reference deletion guard;
- additive seeder idempotency, verbatim caveat and no-media placeholders;
- one skip link, pinned `Home.vue` markers and canonical section order;
- scoped CSS isolation and single Vue template root per redesign component;
- existing integration contract for the ten canonical sections and inline sources.

The final seeder adjustment removed invented fragment URLs from ticker headlines; all focused tests were rerun afterwards.

---

## 2. Batched full-suite evidence

A single monolithic `php artisan test --compact` had previously produced no useful output and timed out after 600000 ms. The suite was therefore run in four parallel batches against SQLite `:memory:`:

| Batch | Tests | Passed | Assertions | Failures |
|---|---:|---:|---:|---|
| Account/Admin/Audit (excluding `AccountEmailChangeTest`) | 77 | 77 | 1,814 | 0 |
| Auth/CMS/localization/migration/website | 136 | 135 | 8,451 | 1 pre-existing |
| Operations/public website/redesign/RBAC | 135 | 133 | 5,542 | 2 pre-existing |
| Settings/translations/unified roles/unit | 49 | 48 | 892 | 1 pre-existing |

`AccountEmailChangeTest.php` was isolated because a fatal PHP 8.5 property declaration conflict aborts that process. In a filtered run, 21 tests passed and 6 tests errored (`Undefined array key "notification"`); the remaining test triggers the fatal error:

> `Cannot redeclare non-readonly property Illuminate\Notifications\Notification::$locale as readonly App\Notifications\AccountEmailChangeNotification::$locale`

---

## 3. Pre-existing failures (not caused by redesign work)

| Failing test | Cause | Evidence of pre-existing state |
|---|---|---|
| `PublicWebsiteTest::test_public_shell_uses_seeded_primary_menu` | Preserved uncommitted `SiteHeader.vue` uses `const { links, cta, barEntries } = usePublicNavigation()` instead of the exact pinned string. | `SiteHeader.vue` is modified in the copied working tree and is byte-identical to `/root/PCDR/pcdr` (SHA-256 `3a5362ca…03dd25`). |
| `CmsNavigationModuleRemovalTest::test_public_shell_header_footer_mobile_nav_and_footer_groups_remain_wired` | Same `SiteHeader.vue` string assertion. | Same source file and hash as above. |
| `PublicWebsiteTest::test_approved_homepage_baseline_files_remain_byte_identical` | Preserved uncommitted `LanguageSwitcher.vue` hash is `29f9798f…f4471537`, not the pinned `d5182c3b…4dda03f1`. | Byte-identical to `/root/PCDR/pcdr`; `pcdr-home.css` and `app.blade.php` still match their pinned hashes. |
| `TranslationKeysTest::vue_and_blade_translation_keys_exist_in_lang_files` | 16 missing-key findings in pre-existing account/dashboard files (`DashboardChrome.vue`, account controllers/requests/services). | Those paths are outside redesign ownership and remain untouched. |
| `AccountEmailChangeTest` (1 fatal + 6 errors) | Pre-existing notification class conflicts with the installed framework on PHP 8.5. | `AccountEmailChangeNotification.php` and `EmailChangeController.php` are untracked pre-existing files in the copied working tree and absent from original `pcdr`. |

These failures were not fixed by discarding preserved user work, and none of the redesign-owned files appear in their failure paths.

---

## 4. Runtime smoke check

A local `php artisan serve` instance was started briefly on port 8123 and stopped after the check:

- `GET /ar` → HTTP 200 (193,442 bytes).
- `GET /api/public/homepage?locale=ar` → HTTP 200 (764 bytes) with `{"data":{"locale":"ar","dir":"rtl",...}}`.

The development database had no homepage sections at the time, so the API returned an empty `sections` array. The database was not seeded or modified during this check. Full Playwright specs were not run: they require a seeded server fixture and the relevant institutional design/content approvals are not recorded.

---

## 5. Conclusion

All redesign-specific tests pass, the production asset build succeeds, and formatting checks pass. The remaining suite failures are pre-existing, reproducible from preserved source work, and explicitly excluded from this change set. No result here should be read as acceptance of the institutional content or as authorization to deploy.
