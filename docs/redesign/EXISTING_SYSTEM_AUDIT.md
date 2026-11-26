# PCDR PRO — Existing System & Governance Audit

**Phase:** 3 — Existing System and Governance Audit
**Date:** 2026-11-26
**Status:** ✅ Read-only audit complete

Scope: `/root/PCDR/pcdr-pro`. Source project was not touched. No tests were
executed during the read-only phase.

---

## 1. Backend architecture

- Laravel 13 / PHP 8.5, Inertia 3.7 + Vue 3, Vite 7.
- Controllers are thin; business logic is split between `app/Actions/*`,
  `app/Services/*`, and `app/Models/Concerns/*`. `DB::transaction()` is used for
  multi-table CMS writes.
- Localization is first-class: `SetLocale`, `SetWebsiteLocale`,
  `RedirectToLocalizedUrl`, `app/Support/Locale.php`, `lang/ar` + `lang/en`,
  translation tables with `HasTranslations::t()` fallback chain.
- `bootstrap/app.php` appends `SetLocale`, `SetWebsiteLocale`,
  `EnsureDashboardFeatureAvailable`, `HandleInertiaRequests`,
  `AddSecurityHeaders`; aliases `cms`, `permission`, `account.active`, `locale`.
- JSON error rendering already handles `api/*` and `expectsJson()`, but there is
  **no** `routes/api.php`, no `app/Http/Resources/`, and no public JSON endpoint.

## 2. Frontend architecture

- `resources/js/Pages/Public/*` = institutional website;
  `resources/js/Pages/Cms/*` + `Pages/Admin/*` = management dashboard.
- `DashboardChrome.vue` is the shared shell; `AppLayout.vue` (admin) and
  `CmsDashboardLayout.vue` (cms) are wrappers.
- Public layout provides `<main id="main">`, `SiteHeader.vue`, `SiteFooter.vue`,
  `useI18n.js`, `useLocale.js`, `SafeRichText.vue`.
- Styling: `resources/css/public-site.css` and `dashboard.css` with logical
  properties, RTL overrides, `:focus-visible`, `prefers-reduced-motion`,
  breakpoints 560–1359 px. No Tailwind in the public redesign.
- `vite.config.js` fetches Bunny fonts at build time; `public/build/manifest.json`
  exists.

## 3. Database structure

- Migration naming is timestamped and descriptive; translations live in paired
  `*_translations` tables with unique `(entity_id, locale)` keys.
- Publication is expressed by `app/Enums/ContentStatus.php` +
  `app/Models/Concerns/Publishable.php` + `app/Services/ContentQuery.php` on
  content modules (articles, pillars, programs, projects, publications).
- Homepage tables (`homepage_sections`, `homepage_section_translations`,
  `homepage_section_items`, `homepage_section_item_translations`) exist but have
  **no** publication status and no media relation.
- `media` / `media_translations` store ULID paths, checksums, MIME, dimensions,
  alt text and captions; public/private disks with 30-minute signed links.
- `website_settings` / `website_setting_translations` hold 19 public-site keys;
  platform `settings` hold admin configuration.

## 4. Existing modules

Website CMS: homepage, content (articles/publications/pages), programs, pillars,
projects, media library, navigation, inquiries, site settings, email templates,
partner directory, preview. Operations and Phase 5 modules cover workflows,
tasks, documents, notifications, integrations and audits. Admin covers users,
roles, permissions, settings, audit logs/archives.

## 5–8. Authentication, authorization, roles, organizational relationships

- Session auth with localized login; inactive users are logged out and sessions
  invalidated (`EnsureAccountActive`).
- `config/permissions.php` catalogue + `RbacService`, `PermissionService`,
  `CmsAccess::allows()`, `EnsureCmsPermission` (`cms:<permission>`),
  `RequirePermission`, `EnsureDashboardFeatureAvailable`.
- `homepage.view` / `homepage.update` already exist and are granted only to
  `website_administrator` in `CmsRolesSeeder`. No new permission is required for
  the homepage work.
- `HandleInertiaRequests` shares `auth.permissions`, `auth.cmsPermissions`,
  `auth.roles`; controllers pass `can` objects for UI gating (homepage currently
  does not).
- Organizational relationships relevant to content: parent organization → sites
  (Gaza) → programs/pillars → projects/articles/publications; media is shared;
  navigation menus are localized.

## 9. API architecture

No public API convention exists today. Ad-hoc JSON returns (`signed` media URL,
audit-log detail) are authenticated only. `bootstrap/app.php` already sets JSON
error rendering for `api/*`/`expectsJson()`. The redesign will introduce the
first read-only public JSON surface and must document that decision.

## 10–11. Existing content and media management

- `Cms/HomepageController::index/update` already edits per-section AR/EN
  `eyebrow`, `title`, `body`, `cta_label`, `cta_link`, `is_enabled`,
  `display_order`, and section items (`item_type`, `item_id`, `item_key`,
  `numeric_value`, `is_published`, AR/EN label/title/body/display_value/url).
- It auto-creates 10 fallback sections when none exist; keys differ from
  `HomepageContentSeeder` keys — a pre-existing inconsistency to freeze.
- Media library supports upload, replace, AR/EN alt/caption, preview, signed
  download, restricted visibility, and deletion guards. `ContentMediaField.vue`
  is a reusable picker used by content pages but not by homepage.
- Site settings (logo, contact, social, slogan, footer) are already managed in
  the CMS.

## 12. Localization

Full AR/EN locale prefixes, direction shared as `dir`, translation fallback,
`TranslationKeysTest`. Homepage CMS uses stacked AR/EN panes rather than the
canonical `pc-tabs` tab pattern used elsewhere.

## 13. Current homepage

- `Public/HomeController` passes hardcoded `config('website.vision|mission')`
  and `impact_framework`, plus DB content from `ContentQuery`, plus
  `HomepageService::sections($locale, false)` — the `false` deliberately skips
  `is_enabled` filtering (pinned by tests).
- `resources/js/Pages/Public/Home.vue` is a fixed ten-section template driven by
  section keys; DOM order is pinned by `HomepageBackendIntegrationTest.php` and
  three files are pinned byte-for-byte by `PublicWebsiteTest.php`. This is the
  primary regression constraint for the redesign.
- `HomepageService` has no caching and no flush.

## 14. Existing tests

`phpunit.xml` uses SQLite `:memory:`, array cache/session, sync queue,
`APP_LOCALE=ar`. Relevant: `HomepageBackendIntegrationTest.php`,
`PublicWebsiteTest.php`, `StrategicPillarsPageTest.php`, `CmsWorkflowTest.php`,
`PublicationsMediaUsersSettingsRedesignTest.php`, `LocalizationTest.php`,
`TranslationKeysTest.php`. E2E: Playwright specs for public header/footer,
admin shell, strategic pillars responsiveness, email templates; axe/Lighthouse
checks exist but mostly cover header/footer/admin. Dependencies (`vendor/`,
`node_modules/`, browsers) are installed.

## 15. Technical debt

- Seeder/controller homepage key mismatch.
- `update()` deletes unsubmitted items; no audit record; no FormRequest.
- Fixed frontend template prevents genuine section reordering.
- `is_enabled` ignored publicly by design/test.
- No homepage cache invalidation.
- README §13 incorrectly says Playwright is unavailable.
- `trustProxies('*')` and report-only CSP in production are deployment-specific
  decisions requiring justification (see SECURITY_REVIEW.md).

## 16. Security concerns

Existing controls: security headers middleware, CSRF (except inbound webhooks),
CMS permission middleware, account-active checks, rate limits on login/reset/
inquiry/webhooks, upload MIME/extension/size validation, ULID server paths,
signed private media links, `RichText` sanitizer, published-only content scopes.

Gaps relevant to this work: no upload throttle, no magic-byte/pixel-bomb
defense, `original_name` stored verbatim, preview route lacks explicit
permission middleware, no public API filtering contract yet, `DeleteMedia` does
not know about homepage media references.

## 17. Deployment assumptions

- Web server terminates TLS and proxies headers (`trustProxies('*')`); HSTS
  expected at proxy.
- Public disk should be symlinked; private media served via signed routes.
- Cache driver `database` in production config, `array` in tests.
- `php artisan test` / Playwright locally available; MySQL/MariaDB behavior is
  not covered by the in-memory SQLite test baseline.

---

## Consolidated conclusion: reuse vs missing

**Reuse:** homepage section/item tables and translations; media library and
picker; site settings; navigation; permission catalogue; `Publishable`/`ContentQuery`;
`HasTranslations`; CMS toasts/dialogs/tables/tabs; SEO service; public layout.

**Missing (minimum):** publication status on homepage sections, section/item
media references with alt text, cache + invalidation, one localized public JSON
endpoint with API Resources, CMS reorder/status/toggle endpoints, homepage
preview route, and a componentized frontend that preserves the supplied design.
No new entity table is justified.
