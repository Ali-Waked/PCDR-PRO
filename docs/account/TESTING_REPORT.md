# Account Management Testing Report

> **Status:** Automated verification completed on 2026-11-26. Results below are the actual command outputs observed in this workspace. Manual/browser checks remain explicitly unverified where noted.

---

## 1. Verification status

| # | Area | Command / check | Actual result | Status |
|---|---|---|---|---|
| 1 | Route registration | `php artisan route:list --name=account` | 12 account routes listed under `{locale}/dashboard/profile…` with `auth`, `account.active`, `signed` and throttle middleware where applicable (6 additional `phase5.accountability.*` routes also match the filter) | Pass |
| 2 | Route cache | `php artisan route:cache && php artisan route:clear` | `INFO Routes cached successfully.` / `INFO Route cache cleared successfully.` — signed routes remain serializable | Pass |
| 3 | Migrations | Covered by `tests/Feature/MigrationIntegrityTest.php` in the full suite | `sessions.public_reference`/`created_at`, `users.avatar_media_id`/`last_activity_at`, and `user_email_changes` are exercised by the passing migration-integrity test | Pass |
| 4 | Changed PHP formatting | `vendor/bin/pint --dirty --format agent` | Account PHP files already conformed; Pint reported no further account-file changes | Pass |
| 5 | Focused account tests | `php artisan test --compact --filter=Account` | `Tests: 57, Assertions: 711, Passed: 57` | Pass |
| 6 | Six account suites | `php artisan test --compact tests/Feature/AccountProfileTest.php tests/Feature/AccountEmailChangeTest.php tests/Feature/AccountSecurityTest.php tests/Feature/AccountAvatarTest.php tests/Feature/AccountActivityTest.php tests/Feature/AccountDashboardWidgetTest.php` | `Tests: 45, Assertions: 515, Passed: 45` | Pass |
| 7 | Translation key parity | `php artisan test --compact tests/Feature/TranslationKeysTest.php` | `Tests: 1, Assertions: 1, Passed: 1`. The scanner verifies every referenced `t()`/`__()` key exists in both `lang/en` and `lang/ar` | Pass |
| 8 | Full PHPUnit suite | `php artisan test --compact` | `Tests: 408, Assertions: 16,817, Passed: 405, Failed: 3` — all three failures are pre-existing public-navigation/homepage files unrelated to account work (details in §5) | Partial |
| 9 | Regression group A | `php artisan test --compact tests/Feature/AuthenticationTest.php tests/Feature/RbacTest.php tests/Feature/AdminAuthorizationTest.php tests/Feature/PlatformOwnerProtectionTest.php` | `Tests: 30, Assertions: 151, Passed: 30` | Pass |
| 10 | Regression group C | `php artisan test --compact tests/Feature/LocalizedUrlFallbackTest.php tests/Feature/MigrationIntegrityTest.php tests/Feature/AdminNavigationReorganizationTest.php tests/Feature/WebsiteCmsScopeTest.php tests/Feature/ErrorPagesTest.php tests/Feature/PublicationsMediaUsersSettingsRedesignTest.php` | `Tests: 59, Assertions: 1,850, Passed: 59` | Pass |
| 11 | Frontend build | `npm run build` | Vite build succeeded (`✓ built in 12.15s`, manifest and assets emitted). Warnings only: optional `fontaine` package absent and a chunk larger than 500 kB | Pass |

---

## 2. Automated test coverage (actual files)

| Test file | Focus covered |
|---|---|
| `tests/Feature/AccountProfileTest.php` | Profile show/update, allowlisted fields, protected columns, locale/RTL output, audit event |
| `tests/Feature/AccountEmailChangeTest.php` | Request → pending state, old-address notice, cancellation, signed confirmation, expiry, single-use token, other-session revocation, remember-token rotation |
| `tests/Feature/AccountSecurityTest.php` | Current-password check, password rules, session revocation, current session preserved, rate limits |
| `tests/Feature/AccountAvatarTest.php` | MIME/magic-byte/`getimagesize()` validation, 2 MB cap, private disk, restricted media, signed 30-minute owner-checked download, cleanup, initials fallback |
| `tests/Feature/AccountActivityTest.php` | Event catalogue, masked IPv4/IPv6, device derivation, throttled `last_activity_at`, no raw IDs/tokens |
| `tests/Feature/AccountDashboardWidgetTest.php` | Real last activity, last login, active-session count, security status, profile links, user-specific data |

---

## 3. Manual / browser checks

| Check | Locale | Status | Notes |
|---|---|---|---|
| Profile page sections render: Overview, Personal Information, Profile Photo, Security (`#security`), Connected Devices, Activity History | EN / AR | Not executed | Component structure and props covered by automated tests; no browser session was run. |
| RTL/LTR layout, anchor navigation, and form validation messages | AR / EN | Not executed | `TranslationKeysTest` and account feature tests cover key/direction wiring only. |
| Email change: old address notified; old email remains active until signed confirmation | EN / AR | Covered by tests only | Mail fakes assert recipients and pending-state behaviour; inbox delivery not manually inspected. |
| Other devices disappear after password change and email confirmation; current stays signed in | EN / AR | Covered by tests only | Session rows and remember-token rotation asserted in tests. |
| Avatar upload, replacement, removal, signed download, initials fallback | EN / AR | Covered by tests only | Private path is never exposed in props; signed URL expiry/ownership asserted. |
| Dashboard Account Activity widget shows real data and links to profile sections | EN / AR | Covered by tests only | Widget receives `overview.account_activity` from `AdminDashboardService`. |
| Rate-limit responses for email, sensitive and avatar endpoints | EN / AR | Covered by tests only | 3/min, 5/min, 10/min + 30/hour thresholds asserted. |

---

## 4. Security evidence

- No raw session ID, remember token, password, or private avatar path appears in Inertia props or API responses; sessions expose only a 32-hex `public_reference`.
- Avatar downloads require a valid signed URL **and** ownership of the `Media` record; URLs expire after 30 minutes.
- Email-change tokens are stored only as SHA-256 hashes and are single-use; the old address remains active/verified until confirmation and is notified.
- Sensitive post-authentication endpoints are throttled and gated by `auth` + `account.active`.
- All account events are written through the existing append-only `AuditLog` mechanism; `auth.*` events are reused for sign-in/out.

---

## 5. Known failures unrelated to this work

The full suite has three failures caused by pre-existing, uncommitted public-site/homepage changes in the workspace:

1. `CmsNavigationModuleRemovalTest::test_public_shell_header_footer_mobile_nav_and_footer_groups_remain_wired` — `resources/js/Components/Public/SiteHeader.vue` now destructures `{ links, cta, barEntries }`, while the test expects `{ links, cta }`.
2. `PublicWebsiteTest::test_public_shell_uses_seeded_primary_menu` — same `SiteHeader.vue` expectation mismatch.
3. `PublicWebsiteTest::test_approved_homepage_baseline_files_remain_byte_identical` — `resources/js/Components/LanguageSwitcher.vue` hash differs from the committed baseline.

These files were already modified before the account work began and were not touched by the account implementation. They are left unchanged so that unrelated in-progress work is not overwritten.

---

## 6. Sign-off

| Gate | Criteria | Status |
|---|---|---|
| Backend contract | Schema, routes, middleware, services, audit and rate limiters implemented per [Account Management Architecture](ACCOUNT_MANAGEMENT_ARCHITECTURE.md) | Pass (automated) |
| Security review | Checks in [Security Review](SECURITY_REVIEW.md) enforced in code and tests | Pass (automated) |
| Frontend & localization | Profile page, widget, EN/AR, RTL/LTR wiring per [Profile Management](PROFILE_MANAGEMENT.md) | Pass (automated) / manual pending |
| Activity & privacy | Masking, device derivation, throttling per [Activity Tracking](ACTIVITY_TRACKING.md) | Pass (automated) |
| Release | All automated and manual rows have passing evidence | Not signed off — 3 pre-existing failures and manual checks pending |

---

## 7. Cross-links

- [Account Management Architecture](ACCOUNT_MANAGEMENT_ARCHITECTURE.md)
- [Profile Management](PROFILE_MANAGEMENT.md)
- [Session Management](SESSION_MANAGEMENT.md)
- [Activity Tracking](ACTIVITY_TRACKING.md)
- [Security Review](SECURITY_REVIEW.md)
