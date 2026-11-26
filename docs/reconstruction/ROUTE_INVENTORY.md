# PCDR PRO — Route Inventory

**Date:** 2026-11-26  
**Source:** `php artisan route:list --json` (490 routes), `routes/web.php`, `routes/operations.php`, `routes/phase5.php`, `bootstrap/app.php`, controllers and policies.  
**Governance:** `NOT CLEARED`. This inventory is technical and does not approve ADRs, close open questions, or authorize Phase 6.

---

## 1. Classification summary

| Class | Meaning | Count | Decision |
|---|---|---:|---|
| A | Public website / visitor-facing | 26 | Keep; homepage redesigned, others retained |
| B | Authentication / verification | 13 | Keep unchanged |
| C | Authenticated dashboard, admin, CMS, account | 125 | Keep; adopt shared design system and theme |
| D | Machine/API endpoints | 2 | Keep; public homepage API + hidden HMAC webhook |
| E | Internal/system/hidden operational modules | 322 | Preserve hidden; no public exposure |
| F | Legacy compatibility redirects | 2 | Keep as bookmark compatibility |
| **Total** |  | **490** |  |

Global middleware is registered in `bootstrap/app.php:28-69`: locale, website locale, hidden-module gate, shared Inertia data, activity tracking and security headers. Guests redirect to `login.show`; authenticated users redirect to `dashboard`.

Special cases:

- `public.operational-documents.download` is class E, not A: it requires `auth`, `account.active`, `signed` and `permission:documents.download`.
- `phase5.webhooks.inbound` is class D: machine-to-machine HMAC receiver, CSRF exempt.
- `operations.reports.export` is class E: authenticated hidden CSV export, not an open API.
- `account.sessions.index` returns JSON but belongs to the authenticated profile area (class C).

---

## 2. Class A — Public website (26)

All routes are guest-accessible unless noted.

| Route name | Method / URI | Controller / page | Notes |
|---|---|---|---|
| `root` | GET `/` | closure → default locale | Keep; never block visitors at root |
| `sitemap` | GET `/sitemap.xml` | `Public\SitemapController` | Keep; static and published paths |
| `robots` | GET `/robots.txt` | `Public\RobotsController` | Keep |
| `public.home` | GET `/{locale}` | `Public\HomeController` → `Public/Home` | Approved prototype homepage |
| `public.about` | GET `/{locale}/about` | `Public\AboutController` | Keep |
| `public.about.focus` | GET `/{locale}/about/{focus}` | `Public\AboutController` | Keep; SEO focus pinned |
| `public.pillars.index` / `.show` | GET `/{locale}/strategic-areas[/…]` | `Public\PillarController` | Keep |
| `public.programs.index` / `.show` | GET `/{locale}/programs[/…]` | `Public\ProgramController` | Keep |
| `public.projects.show` | GET `/{locale}/projects/{project}` | `Public\ProgramController@project` | Keep |
| `public.news.index` / `.show` | GET `/{locale}/news[/…]` | `Public\NewsController` | Keep |
| `public.publications.index` / `.show` | GET `/{locale}/publications[/…]` | `Public\PublicationController` | Keep |
| `public.publications.download` | GET `/{locale}/publications/{publication}/download` | `Public\PublicationController@download` | Signed URL required |
| `public.transparency` | GET `/{locale}/transparency` | `Public\StaticPageController` | Keep |
| `public.partners` | GET `/{locale}/partners` | `Public\StaticPageController@partners` | Duplicate alias; keep pending canonical decision |
| `public.partnerships` | GET `/{locale}/partnerships` | `Public\StaticPageController@partners` | Current canonical nav/sitemap path |
| `public.involve` | GET `/{locale}/get-involved` | `Public\StaticPageController` | Keep |
| `public.contact` | GET `/{locale}/contact` | `Public\StaticPageController` | Keep |
| `public.complaints` | GET `/{locale}/complaints` | `Public\StaticPageController` | Keep |
| `public.inquiry.contact` | POST `/{locale}/contact/inquiry` | `Public\InquiryController@contact` | Keep |
| `public.inquiry.volunteer` | POST `/{locale}/volunteer/inquiry` | `Public\InquiryController@volunteer` | Keep |
| `public.page` | GET `/{locale}/pages/{page}` | `Public\PageController@show` | Keep; CMS-created public pages |
| `public.media.download` | GET `/{locale}/media/{uuid}/download` | `Public\MediaController@download` | Signed; restricted media gated |

Dependencies: `resources/js/composables/usePublicNavigation.js`, `app/Http/Controllers/Public/SitemapController.php`, `PublicWebsiteTest.php`, `PartnerDirectoryTest.php`, `ComplaintsBoxTest.php`, and the two public e2e specs. No active class A route is safe to delete without coordinated navigation, sitemap, SEO and test changes.

---

## 3. Class B — Authentication (13)

Keep unchanged: `login.show`, `login.store`, `password.request`, `password.email`, `password.reset`, `password.store`, `logout`, `verification.notice`, `verification.verify`, `verification.resend`, `cms.login`, `cms.login.attempt`, `cms.logout`.

Middleware includes `guest` for login/reset forms, `throttle:login` and `throttle:password-reset` for submissions, `auth` for logout/verification, and `throttle:6,1` for signed verification/resend. No authentication route was removed or altered.

---

## 4. Class C — Authenticated dashboard/admin/CMS (125)

### C1 — Dashboard and self-service account (13)

`dashboard`; `account.profile.show` / `.update`; email change request/confirm/cancel; password update; session list/revoke/revoke-others; avatar store/destroy/download. All are `web,auth,account.active` with sensitive throttles where applicable.

### C2 — Admin (29)

- Users: `admin.users.index/create/store/show/edit/update/destroy/status/roles/reset-password`.
- Unified access: `admin.access.index`.
- Legacy bookmark redirects: `admin.roles.*`, `admin.permissions.index`.
- Audit logs: `admin.audit-logs.index/export/show`.
- Audit archive: `admin.audit-archives.index/store/show/download/verify`.
- Admin settings: `admin.settings.index/update`.

Legacy Roles/Permissions Vue pages are unrouted but retained; no administrative permission CRUD was re-added.

### C3 — CMS (83)

| Group | Routes | Permission family |
|---|---|---|
| Dashboard | `cms.dashboard` | `pillars.view` |
| Homepage | `cms.homepage.index/update/preview/reorder/sections.store/sections.destroy/status/toggle` | `homepage.view` / `homepage.update` |
| Pillars, programs, projects, news, publications | 7 routes each (`index/create/store/edit/update/destroy/status`) | respective `.view` families |
| Media | `cms.media.index/store/update/destroy/signed` | `media.view/upload/update/delete` |
| Partner directory | 16 routes (categories + partners CRUD, status, reorder) | `directory_partners.*` |
| CMS settings | `cms.settings.index/update/upload-image` | `settings.view` / `settings.update` |
| Preview | `cms.preview` | `account.active` |
| Inquiries | `cms.inquiries.index/show/status/assign/note/reply` | `inquiries.*` |
| Email templates | `cms.email-templates.index/store/preview/send-test/duplicate/status/update/destroy` | `email_templates.*` |

Navigation contract: `config/admin_navigation.php` lists 18 active destinations; `AdminNavigationReorganizationTest.php:32-50` pins them to exact route names.

---

## 5. Class D — API / machine endpoints (2)

| Route | Middleware | Notes |
|---|---|---|
| `GET /api/public/homepage` (`api.public.homepage`) | `throttle:60,1`, public | Published homepage only; keep |
| `POST /webhooks/inbound/{integration:key}` (`phase5.webhooks.inbound`) | `throttle:30,1`, HMAC, CSRF exempt | Hidden machine endpoint; never browser-facing |

---

## 6. Class E — Internal/system/hidden operational modules (322)

### E1 — Hidden operations resources (240)

`routes/operations.php:62-109` registers 8 routes for each of 30 resources in `config/operations.php`:

`locations`, `interventions`, `activities`, `distribution_plans`, `distribution_rounds`, `distribution_records`, `warehouses`, `inventory_items`, `volunteers`, `volunteer_teams`, `volunteer_assignments`, `volunteer_trainings`, `committees`, `committee_meetings`, `community_feedback`, `partners`, `partner_agreements`, `partner_contacts`, `grant_opportunities`, `grant_proposals`, `grant_awards`, `results_frameworks`, `reporting_periods`, `indicators`, `indicator_measurements`, `evaluations`, `corrective_actions`, `assessments`, `data_collection_instruments`, `operational_reports`.

Per resource the suffixes are `.index`, `.create`, `.store`, `.edit`, `.update`, `.destroy`, `.transition`, `.documents.attach`, with `{family}.view/create/update/transition` and `documents.upload` permissions.

### E2 — Hidden operations specials (11)

`operations.dashboard`, `operations.reports.index/export/generate`, `operations.inventory.index`, `operations.inventory.movements.store/reverse`, `operations.measurements.verify`, `operations.proposals.submit`, `operations.distributions.reconcile`, and the protected operational document download.

### E3 — Hidden Phase 5 (64)

Source `routes/phase5.php:25-146`: dashboard/activity (2), workflows (9), tasks (10), discussions (6), accountability (6), referrals (4), notifications (6), automations (6), calendar (1), document reviews (6), integrations (4), webhook UI (4). All are hidden behind `EnsureDashboardFeatureAvailable` and permissions; none is in public navigation.

### E4 — Framework/system (7)

`boost.browser-logs`, two Inertia devtools entries, `storage.local` GET/PUT, health check `up`, and the localized URL fallback. Keep internal.

Hidden modules are disabled in `config/dashboard_features.php`; developer override is `PCDR_HIDDEN_MODULES_ACCESS`. Ordinary users receive 403.

---

## 7. Class F — Legacy compatibility redirects (2)

`GET /login` → `login.show` and `GET /forgot-password` → `password.request` remain for unlocalized bookmarks and are pinned by `LocalizationTest.php:16-17`.

---

## 8. Route-name references that constrain removal

- `usePublicNavigation.js:11-67`: public paths and footer groups.
- `SitemapController.php:17-18`: 11 static paths.
- `PublicWebsiteTest.php:390,503-555,601-659`: nav, components, header/footer markers.
- `PartnerDirectoryTest.php:63-162,289,429`: both partner route aliases.
- `ComplaintsBoxTest.php:73-75`, `LocalizedUrlFallbackTest.php:276`, `CmsWorkflowTest.php:725-743`, `AccountAvatarTest.php:156`, `AuditArchiveLifecycleTest.php:441`.
- `routes.mjs:6-7,108-137`: e2e route discovery.

No Vue page calls `route('public.*')`, `route('admin.*')` or `route('cms.*')` directly; operations/phase5 use `globalThis.route(...)` helpers.

---

## 9. Delete vs redirect rules

Should remain 404 (already absent): `/{locale}/partnership/inquiry`, `/{locale}/cms/pages`, `/{locale}/cms/navigation`, and removed admin permission CRUD routes.

Should remain redirects: unlocalized `/login` and `/forgot-password`, and legacy admin roles/permissions bookmarks to unified access.

Unknown localized paths must 404 through `RedirectToLocalizedUrl`; they must not redirect to the homepage.
