# PCDR PRO — Legacy Cleanup Report

**Date:** 2026-11-26  
**Status:** Conservative cleanup completed; destructive removal deliberately withheld where authorization or dependencies are unresolved.  
**Governance:** `NOT CLEARED`. No ADR/OQ/Phase 6 status was changed.

---

## 1. Scope

The approved homepage replaced the old public presentation at the existing `public.home` route. The cleanup reviewed 490 routes, 73 Inertia page components, navigation configuration, sitemap paths, tests and hidden operational modules.

Full route classification is in `ROUTE_INVENTORY.md`.

---

## 2. What was actually cleaned

### Public presentation

- `resources/js/Pages/Public/Home.vue` now renders the approved `.pcdr-prototype` composition.
- Legacy homepage markers (`.pcdr-home public-shell`, `SiteHeader`, `SiteFooter`, `routeFor(...)`, `pcdr-pro-home.css`) were removed from the homepage.
- The homepage uses `ProPrototypeFooter`; non-home public pages continue to use the shared `SiteFooter`.
- The approved embedded logo was extracted and used; the stale standalone `logo.jpg` was not substituted.
- The legacy `pcdr-pro-home.css` file is no longer imported. It remains on disk as an untracked/legacy artefact pending an explicit deletion decision.

### Backend and CMS

- The homepage CMS no longer auto-creates the obsolete section keys on every GET.
- Legacy-only homepage sections (`intro`, `areas`, `framework`, `targets`, `community`, `partners`, `knowledge`, `cta`) are disabled, not deleted.
- Homepage create/delete routes replaced ad-hoc section management for the new prototype sections.
- Unsafe links, encoded backslashes and protocol-relative URLs are rejected at validation and sanitized at serialization.

### Tests

Legacy tests that asserted the removed homepage shell are being migrated to assert the prototype contract instead of being silently skipped. See `TEST_REPORT.md`.

---

## 3. Obsolete public URLs

These were already absent before this task and must remain 404; no redirect to the homepage was added because that would be misleading:

- `/{locale}/partnership/inquiry`
- `/{locale}/cms/pages`, `/{locale}/cms/pages/create`
- `/{locale}/cms/navigation` and its detail routes
- removed admin permission CRUD routes

These remain compatibility redirects:

- `/login` → localized `login.show`
- `/forgot-password` → localized `password.request`
- legacy admin roles/permissions bookmarks → unified access page

---

## 4. What was deliberately not removed

### Active public routes

No active public route was deleted. Each has dependencies in one or more of:

- `usePublicNavigation.js`
- `SitemapController.php`
- `PublicWebsiteTest.php`, `PartnerDirectoryTest.php`, `ComplaintsBoxTest.php`
- public e2e specs
- signed download chains
- published CMS content

Deleting any of them would break visitor access, SEO, forms, media downloads or tests. The user's requirement not to make the application inaccessible to visitors takes precedence over cosmetic removal.

### `public.partners` / `public.partnerships`

These are duplicate aliases to the same controller and page. Consolidation would require an explicit canonical-path decision and coordinated updates to navigation, sitemap, SEO metadata and tests. Neither alias was silently removed.

### Legacy Roles/Permissions pages

The unrouted files under `resources/js/Pages/Admin/Roles/*` and `resources/js/Pages/Admin/Permissions/*` are dead UI, but the dashboard audit and governance constraints do not authorize deleting RBAC-related surfaces. They were retained.

### Hidden `operations` and `phase5`

All 322 internal/system routes and their Vue pages remain hidden behind `EnsureDashboardFeatureAvailable`. None was exposed, renamed or removed.

### Preserved uncommitted work

Dashboard CSS/Vue, email-template files, translation files and other uncommitted user changes were not overwritten.

---

## 5. Risk assessment

| Risk | Finding | Mitigation |
|---|---|---|
| Visitor lockout | Active public routes are dependencies | No active route deleted |
| Broken SEO | Sitemap/nav paths are pinned | Routes preserved |
| Misleading redirects | Removed CMS/inquiry URLs have no valid destination | 404 retained |
| Authorization regression | Hidden modules and RBAC are unchanged | Policy/middleware tests retained |
| Data loss | Legacy homepage rows preserved | Disabled, not deleted |
| Scope creep | Legacy RBAC pages lack deletion authorization | Retained |

---

## 6. Honest completion status

The old public homepage presentation was removed and replaced. No other active public page was removed because the route audit found no safe candidate that would not break access, SEO, forms or tests. This is a deliberate safety decision, not an oversight.
