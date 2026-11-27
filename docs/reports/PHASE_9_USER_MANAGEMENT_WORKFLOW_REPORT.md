# Phase 9 — User Management Workflow Repair Report

Generated: 2026-11-27

Repository: `/root/PCDR/pcdr-pro`

Branch: `main`

Baseline HEAD: `4020d26`

Classification: **PASS WITH TWO PRE-EXISTING, UNRELATED FULL-SUITE FAILURES**

---

## 1. Executive summary

The Phase 9 user-management workflow is repaired end to end on `main`: an administrator creates a user, the user is immediately marked verified, roles are assigned atomically, the user can log in through the existing Laravel `web` guard, reach the protected dashboard, keep the session across subsequent navigation, and log out. Last-login history is now written only after a real active/verified login, and failed/unverified/inactive attempts are audited without polluting login history.

The same change set also removes misleading success toasts from auth pages, corrects RTL/LTR search-icon spacing with logical properties, contains narrow-screen table scrolling without introducing whole-page horizontal scroll, and preserves the exact bilingual technical-access advisory. The previously prepared mobile error-page hardening is included in the same commit.

No schema migration was required. No role or user was deleted, no existing password was reset, no verification requirement was disabled, no second authentication system was introduced, and no institutional approval authority was granted.

---

## 2. Exact root causes and fixes

| # | Root cause | Observed effect | Fix |
| --- | --- | --- | --- |
| 1 | `App\Models\User` `#[Fillable]` omitted `email_verified_at`. | `User::create([... 'email_verified_at' => now()])` silently dropped the intended verified state. With `security.require_email_verification` still defaulting to `true`, a newly created user was redirected to the verification notice instead of the dashboard. | Added `email_verified_at` to the fillable attribute list. Admin-created users remain pre-verified with `now()`. |
| 2 | Login and creation email input was not normalized. | Mixed-case or padded email addresses could be stored/compared inconsistently, so a valid newly created user could fail normal login or duplicate checks could miss the same address. | `LoginRequest` and `StoreUserRequest` now apply `Str::lower(trim(...))` in `prepareForValidation()`. |
| 3 | User creation and role synchronization were not one transaction. | A role-sync failure left a user row behind with no roles and a partial success state. | `UserController::store()` wraps `User::create()` and `AccessManagementService::syncRoles()` in `DB::transaction()`; `RuntimeException` is caught outside the transaction and returned as a real `flash.error`. |
| 4 | Login-history writes were embedded in the session controller and not consistently scoped to the verified/active success branch. | Failed, locked, inactive, or unverified attempts could be confused with successful login activity. | Added `App\Services\AuthenticationActivityService`; `recordSuccessfulLogin()` is called only after `Auth::attempt()`, active status, and verification all pass. `recordFailedLogin()` and `recordLogout()` centralize the other audit effects. |
| 5 | `useAdminNotifications.js` emitted `notify.action('updated')` for any non-GET success, even when the backend returned no flash, and `AuthLayout.vue` mounted `AdminToastProvider`. | Failed or redirected login attempts could display a false success-like toast on public auth pages. | Removed the generic success emission; success toasts now require real backend flash data. `AdminToastProvider` is no longer mounted in `AuthLayout.vue`. |
| 6 | RTL search CSS reversed logical padding with `[dir='rtl']` overrides, pushing entered text against the search icon. | Placeholder, entered value, validation, and disabled states misaligned in Arabic relative to English. | Converted `.pc-nav-find`, `.pc-search`, and `.pc-content-filters__search` to `padding-inline: <icon-gutter> 12px` and removed the bad RTL overrides. Sizes and visual design are unchanged. |
| 7 | Narrow dashboards allowed fixed-min-width tables, action groups, badges, and details to escape their containers. | Content was clipped at 360/390/430px rather than scrolling inside the table/card. | Retained the existing contained `.pc-table-wrap { overflow-x: auto }` and `.pc-app { overflow-x: clip }`, and added `<=560px` rules that wrap actions, badges, lists, and details and allow min-widths to shrink. No whole-page `overflow-x: auto` was added. |

---

## 3. Actual repaired workflow

1. An authorized administrator opens `Admin/Users/Create` and submits name, normalized email, temporary password, status, locale, and optional roles.
2. `StoreUserRequest` authorizes `users.create` and separately requires `users.assign_roles` when roles are present.
3. `UserController::store()` opens a database transaction, creates the user with `email_verified_at = now()`, hashes the password, and synchronizes allowed roles.
4. If any role-sync rule fails, the transaction rolls back and returns the real error flash; no orphan user is persisted.
5. On success, RBAC cache is invalidated, an audit record is written, and the admin receives the real `ui.user_created` flash.
6. The user logs in at `/{locale}/login` through the existing Laravel `web` guard. Credentials are normalized, rate limiting is retained, and active/verification checks remain enforced.
7. Only after full success are `previous_login_at`, `last_login_at`, `failed_login_attempts`, and `locked_until` updated, RBAC cache forgotten, `auth.login` audited, and the rate limiter cleared.
8. The session regenerates, stores `account.session_reference`, and redirects to the protected dashboard. Repeated navigation and profile access remain authenticated.
9. Logout audits `auth.logout`, forgets RBAC cache, invalidates the session, and regenerates the CSRF token. Protected routes then redirect to login.

---

## 4. Files changed

### Backend

- `app/Models/User.php` — fillable `email_verified_at`.
- `app/Http/Requests/Auth/LoginRequest.php` — email normalization.
- `app/Http/Requests/Admin/StoreUserRequest.php` — email normalization.
- `app/Http/Controllers/Admin/UserController.php` — transactional create + role sync, outer `RuntimeException` handling.
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` — delegates login/logout/failure activity to the new service.
- `app/Services/AuthenticationActivityService.php` — new centralized successful-login, failed-login, and logout recording.

### Frontend and translations

- `resources/js/composables/useAdminNotifications.js` — flash-only success notifications.
- `resources/js/Layouts/AuthLayout.vue` — removed `AdminToastProvider` from auth pages.
- `resources/js/Pages/Admin/Users/Create.vue` — subtle `pc-advisory` with `role="note"` and `t('users_page.technical_note')`; duplicate hint removed.
- `resources/js/Pages/Admin/Access/Index.vue` — role and permission search fields reuse shared `.pc-search` spacing.
- `resources/css/dashboard.css` — advisory style, logical search padding, contained responsive scrolling/wrapping.
- `lang/en/ui.php` — exact English advisory: `Technical access only. Assigning roles does not grant institutional approval authority.`
- `lang/ar/ui.php` — retained exact Arabic advisory: `وصول تقني فقط. إسناد الأدوار لا يمنح سلطة الموافقة المؤسسية.`

### Error pages included from the prior prepared fix

- `resources/views/errors/layout.blade.php`
- `resources/views/errors/partials/artwork.blade.php`
- `tests/Feature/ErrorPagesTest.php`

### Tests

- `tests/Feature/UserManagementWorkflowTest.php` — new 15-case lifecycle/regression suite (178 assertions), including bilingual creation → login → dashboard → refresh/navigation → logout, last-login policy, rollback, authorization, governance boundary, advisory copy, notification guardrails, icon spacing, and responsive CSS guardrails.

---

## 5. Migrations and schema

No migration was created or required.

The access fields already exist in `database/migrations/2026_11_22_000004_add_access_fields_to_users_table.php`:

- `last_login_at`
- `previous_login_at`
- `failed_login_attempts`
- `locked_until`
- `status`

The existing `users`, `roles`, `role_user`, `permissions`, and `audit_logs` structures were used as-is.

---

## 6. Verification results

### Focused tests

`php artisan test --compact` on:

- `tests/Feature/UserManagementWorkflowTest.php`
- `tests/Feature/AuthenticationTest.php`
- `tests/Feature/AdminAuthorizationTest.php`
- `tests/Feature/LoginExperienceTest.php`
- `tests/Feature/ErrorPagesTest.php`

Result: **55 tests passed, 540 assertions passed**.

The lifecycle tests explicitly prove that a newly created user can log in, reach `/{locale}/dashboard`, remain authenticated across repeat dashboard and profile requests, and log out in both Arabic and English.

### Full suite

`php artisan test --compact`

Result: **526 tests, 524 passed, 15,747 assertions, 2 failures**.

The two failures are unrelated to this change set and were already observed before the Phase 9 test expansion:

1. `tests/Feature/PreviewInitializationTest.php:33` — expected one seeded `info@pcdr.org` user, got zero. This test owns preview initialization/demo seeding and was not touched.
2. `tests/Feature/ThemePreferenceTest.php:36` — hard-coded SHA-256 of `resources/views/app.blade.php` no longer matches the current committed shell hash. Neither the shell nor this test was modified in this change set.

### Formatting

`vendor/bin/pint --dirty --format agent` — passed.

### Frontend build

`npm run build` — passed with Vite 8.3.8, 816 modules transformed. Non-blocking warnings only:

- Optional `fontaine` package is not installed, so optimized font fallbacks are skipped.
- Main JS chunk exceeds 500 kB; existing code-splitting warning.

### Browser verification

Authenticated Chromium headless remains unavailable in this container. The error-page layout fix was verified with 18/18 headless Firefox computed-style checks at 360/390/768px for Arabic and English across 403/404/500 pages. No new browser-dependent behavior is required for the user-management workflow; its guarantees are covered by the feature tests above.

---

## 7. Governance and security decisions

- The canonical public identity remains CMS `SiteSettings` / `website_settings` via `SiteSettingsCatalog` and `SiteSettings::publicIdentity()`.
- The existing `media` / `UploadMedia` infrastructure was reused; no duplicate settings or media system was added.
- The existing Laravel `web` guard and database session driver remain the only authentication mechanism.
- `require_email_verification` remains enabled; admin-created users are explicitly pre-verified rather than bypassing verification policy.
- `RbacService` still filters authority-style permissions, and the code-owned `PermissionCatalog` only exposes dashboard-supported keys. The new tests assert that `institutional.approve` is not granted through role assignment, including to a Platform Owner.
- The advisory is translation-backed, exact in both locales, and rendered as a low-emphasis note, not an alert.

---

## 8. Git safety and preserved work

Work was performed only in `/root/PCDR/pcdr-pro` on `main`. No branch, worktree, merge, or push was created. No reset, clean, stash, destructive restore, `git add .`, or `git add -A` was used.

Unrelated modified or untracked artifacts were left untouched, including:

- `.env.example`, `.env.e2e`, `.env.mysql.bak`, `.env.theme`
- `package.json`
- `resources/css/pcdr-prototype.css`
- `resources/js/Components/RedesignHome/ProImpact.vue`
- `resources/js/Components/Public/`
- `scripts/__pycache__/`, `scripts/make-fingerprint.mjs`
- `test-results/`
- `tests/e2e/fingerprint.spec.mjs`, `tests/e2e/screenshots/`, `tests/fingerprint.test.mjs`

Only the intended files listed in Section 4 are included in the commit.

---

## 9. Remaining issues

- The two full-suite failures in `PreviewInitializationTest` and `ThemePreferenceTest` remain open as pre-existing/unrelated issues. They do not exercise user creation, authentication, notifications, search spacing, responsive user pages, or error pages.
- The production bundle still emits the existing large-chunk warning and optional-font-fallback notice.
- Authenticated browser visuals were not re-run in Chromium because headless Chromium is broken in this container; feature tests cover the workflow behavior.

---

## 10. Final classification

**PASS WITH TWO PRE-EXISTING, UNRELATED FULL-SUITE FAILURES.**

The Phase 9 workflow itself is complete, focused tests and build are green, formatting is clean, governance boundaries are preserved, and the commit contains only the intended repair files.
