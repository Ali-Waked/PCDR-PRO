# PCDR PRO — New Information Architecture

**Date:** 2026-11-26  
**Status:** Implemented at shell/navigation level; page-level migration is incremental.  
**Governance:** `NOT CLEARED`.

---

## 1. Public information architecture

The approved homepage is the primary public entry point and follows the source prototype order:

`hero → ticker → impact → programs → news → albums → about → stories → footer`

Required anchors: `#top`, `#impact`, `#targets`, `#programs`, `#news`, `#albums`, `#about`, `#stories`, `#contact`.

Non-home public pages remain available for detail views (about, strategic areas, programs, projects, news, publications, transparency, partnerships, get involved, contact, complaints and CMS pages). Their shared header/footer is unchanged.

---

## 2. Dashboard navigation

Authoritative source: `config/admin_navigation.php`. It exposes 18 active destinations in 5 groups:

| Group | Count | Items |
|---|---:|---|
| Overview | 1 | Dashboard |
| Content | 10 | Homepage, pillars, programs, projects, news, publications, partners, media, settings, content editor |
| Communications | 2 | Inquiries, email templates |
| Identity and access | 4 | Access, users, audit logs, audit archive |
| System | 1 | Admin settings |

Permission filtering happens server-side in `AdminNavigationService::visibleFor()` and is shared through `HandleInertiaRequests`. The frontend does not add its own authorization boundary.

### Navigation improvement

The sidebar previously rendered all 18 items as a flat list, with the `content` group visually overloaded. The shell now supports a collapsible visual group for `content` only. Search and sidebar collapse auto-expand it. Permission filtering is untouched. Mobile drawer behaviour remains intact; hidden links are excluded from focus trapping via `inert`.

Hidden `operations` and `phase5` modules remain absent from navigation by design.

---

## 3. Page structure

The shared shell is `DashboardChrome.vue`, wrapped by `AppLayout.vue` for admin/account pages and `CmsDashboardLayout.vue` for CMS pages. Shared primitives include:

- `PageHeader` (titles, subtitles, actions, breadcrumbs)
- `DataTable`, `DataToolbar`, `DataTablePagination`
- `FormField`, `FormSection`
- `ConfirmDialog`, `StatusBadge`, `CmsStatusBadge`
- `EmptyState`, `FlashMessages`, `UiIcon`

`AppLayout.vue` now forwards a `breadcrumb` prop so retained pages can render breadcrumbs without rewriting their markup. `CmsDashboardLayout.vue` already forwarded breadcrumbs.

---

## 4. Interaction patterns

| Need | Existing/shared pattern | Status |
|---|---|---|
| Search and filtering | `DataToolbar` + page-level filters | Reused; not replaced |
| Tables | `DataTable` + `.pc-table` | Reused |
| Forms | `FormField`, `FormSection`, `.pc-input` | Reused |
| Detail views | page-level `Show` components | Retained |
| Confirmations | `ConfirmDialog` | Reused |
| Empty states | `EmptyState` | Reused |
| Loading/error states | page-level patterns and flash messages | Retained; not uniformly standardized |
| Permission-aware actions | server-side RBAC + shell capability flags | Preserved |
| Mobile behaviour | 1100px drawer breakpoint, 560–640px refinements | Preserved |

---

## 5. Deliberate constraints

- No generic SaaS template was introduced.
- No public editorial components were forced into operational screens.
- No module without functional justification was invented.
- No hidden module was promoted into visible navigation.
- No legacy Roles/Permissions page was deleted without approval.

---

## 6. Completion notes

The shell now has clearer grouping, breadcrumb support, a theme control and a shared token system. Retained module pages continue to use the existing shared primitives; some page-local legacy literals remain. Those are recorded as residual work in `DASHBOARD_REBUILD.md` rather than claimed as fully rewritten.
