# PCDR PRO — Dashboard Rebuild

**Date:** 2026-11-26  
**Status:** Shell, navigation, design-system and theme rebuild implemented; retained module pages migrate incrementally.  
**Governance:** `NOT CLEARED`.

---

## 1. Architecture

The dashboard keeps one shell architecture:

- `DashboardChrome.vue` — sidebar, topbar, user menu, theme control, PageHeader slot.
- `AppLayout.vue` — wrapper for admin/account pages; now forwards `breadcrumb`.
- `CmsDashboardLayout.vue` — wrapper for CMS pages; already forwarded breadcrumbs.
- `PageHeader.vue` — title, subtitle, eyebrow, actions, breadcrumbs.

No second dashboard or alternate shell was introduced.

---

## 2. What changed

### Information architecture

- 18 active destinations in 5 groups from `config/admin_navigation.php`.
- `content` group is now visually collapsible to reduce sidebar density.
- Search and sidebar collapse auto-expand the content group.
- Hidden links are excluded from drawer focus trapping with `inert`.
- Permission filtering remains entirely server-side.

### Design system

- New `pcdr-identity-tokens.css` imported once from `app.css`.
- Operational tokens scoped to `.pc-app` / `.pc-auth`.
- Dashboard focus ring contrast corrected.
- Theme-control styles added for both compact menu and profile form contexts.
- Existing dashboard CSS was appended to, not rewritten; preserved uncommitted work remains intact.

### Theme

- `ThemeSelector.vue` in the topbar user menu offers light / dark / system.
- Preferences persist through `PUT {locale}/dashboard/profile`.
- Shared prop `theme` drives initial state; `app.js` applies explicit values and clears `data-theme` for system.
- `useTheme.js` supplies guest/local fallback behaviour and OS-change reactivity.
- Reduced motion is respected.

### Breadcrumbs and actions

- `AppLayout.vue` now accepts and forwards `breadcrumb`.
- `PageHeader` continues to own the action slot.
- Pages that render their own `PageHeader` with `showHeader:false` were left intact.

---

## 3. Module coverage

Retained modules are unchanged in identity and route:

- Dashboard overview.
- User management and unified access.
- Audit logs and audit archive.
- Admin settings.
- CMS homepage, pillars, programs, projects, news, publications, partner directory, media, settings and content preview.
- Inquiries and email templates.
- Account profile, security, sessions and avatar.
- Hidden `operations` and `phase5` modules (excluded from public navigation).

All inherited modules render inside the shared shell and therefore inherit the new token layer. No module was deleted or invented.

---

## 4. Responsiveness and accessibility

- 1100px breakpoint retains the mobile drawer.
- 560–640px table and user-meta refinements remain.
- Theme control uses radio semantics and translated labels.
- Focus rings meet at least 3:1 contrast.
- Navigation focus trapping and inert handling were preserved/extended.
- Breadcrumbs can now be supplied by admin/account pages.

---

## 5. Limitations

This was a shell-level rebuild, not a page-by-page visual rewrite of all 50 authenticated Inertia pages. Some retained pages still contain page-local CSS literals, duplicate submit controls or older table/form markup. Those were not silently replaced because much of that code is preserved uncommitted user work and governance does not authorize deleting modules.

The shared shell, tokens, navigation behaviour and theme now apply across all retained modules; deeper per-page normalization remains residual work.
