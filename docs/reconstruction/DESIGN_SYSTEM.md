# PCDR PRO — Shared Design System

**Date:** 2026-11-26  
**Status:** Additive token layer implemented.  
**Governance:** `NOT CLEARED`. The approved homepage remains the authoritative visual reference.

---

## 1. Sources of truth

| Layer | File | Role |
|---|---|---|
| Raw institutional identity | `resources/css/tokens.css` | Canonical five-colour identity and spacing |
| Approved public prototype | `docs/redesign/source-homepage/index.html` | Authoritative editorial visual language |
| Public runtime port | `resources/css/pcdr-prototype.css` | Scoped `.pcdr-prototype` implementation of the prototype |
| Shared semantic layer | `resources/css/pcdr-identity-tokens.css` | New `--pcdr-id-*` aliases and light/dark/system mappings |
| Operational UI | `resources/css/dashboard.css` | `.pc-app` / `.pc-auth` admin implementation |

No pinned file was modified. `pcdr-home.css`, `app.blade.php` and `LanguageSwitcher.vue` remain byte-identical.

---

## 2. Identity

Primary `#3E5172`, Secondary `#71BCB7`, Accent `#DAC3A3`, Supporting `#D7C5A1`, White `#FFFFFF`.

Slogan: `من الاستجابة الإنسانية إلى التعافي والتنمية المستدامة`.

The new token file aliases these canonical values rather than redefining them as a new authority.

---

## 3. Token namespace

`--pcdr-id-*` is the single shared namespace. Groups:

- Brand: `--pcdr-id-brand-*`
- Surface: `--pcdr-id-surface-*`
- Text: `--pcdr-id-text-*`
- Border: `--pcdr-id-border-*`
- State: `--pcdr-id-state-*`
- Focus: `--pcdr-id-focus-*`
- Radius: `--pcdr-id-radius-*`
- Shadow: `--pcdr-id-shadow-*`
- Motion: `--pcdr-id-motion-*`
- Space: `--pcdr-id-space-*`
- Type: `--pcdr-id-font-*`, `--pcdr-id-size-*`
- Icon: `--pcdr-id-icon-*`

Operational mappings are scoped under `html .pc-app` and `html .pc-auth`. Editorial mappings are scoped under `html .pcdr-prototype`. This keeps the approved homepage palette intact while giving the dashboard semantic themes.

---

## 4. Public editorial tokens

Extracted from the approved prototype:

| Concern | Token / value |
|---|---|
| Display font | Cairo 600/700/800 |
| Body font | Tajawal 400/500/700 |
| Base type | `400 16px/1.75` |
| Page | `#F1F4F8` |
| Surface | `#FFFFFF` |
| Ink | `#14213B` |
| Ink soft | `#4B5873` |
| Line | `#D3DAE6` |
| Rule | `#1B2B4B` |
| Accent | `#237E7A` |
| Sand | `#D8B77F` |
| Deep | `#1B2B4B` |
| Alert | `#C23B30` |
| Content width | `1180px` |
| Section rhythm | `clamp(52px,8vw,92px)` |
| Hero height | `clamp(520px,78vh,720px)` |
| Breakpoints | 560, 760, 860, 900px |

Dark prototype values are preserved from the source (`#0D1526`, `#15203A`, `#E9EEF7`, `#A6B2CA`, `#2A3857`, `#5CB8B2`).

---

## 5. Operational tokens

The dashboard remains density-oriented:

- IBM Plex Sans Arabic / Inter rather than Cairo/Tajawal.
- Compact `14px/1.55` body text.
- Smaller radii for controls, larger radii for cards.
- Status-heavy colours for danger, success, warning and toasts.
- Wider operational container (`--pcdr-dashboard: 1600px`).

The dashboard focus ring was remapped to the accessible teal token (`--pcdr-teal-700`, approximately 4.67:1 on white), replacing the previous low-contrast bright teal ring.

---

## 6. Theme support

Three preferences are supported: `light`, `dark`, `system`.

- Explicit dark: `:root[data-theme='dark']` and legacy `html.dark`.
- Explicit light: `:root[data-theme='light']` and `.light`.
- System: `@media (prefers-color-scheme: dark)` guarded by `:not([data-theme='light']):not(.light)`.
- `system` deliberately leaves `data-theme` unset so OS changes remain authoritative.
- Reduced motion is respected in transitions, carousel and ticker.

Per-user persistence uses `users.theme`, validated through `account.profile.update` and shared as the top-level Inertia prop `theme`.

Because `app.blade.php` is byte-pinned, its initial `color-scheme` markup is unchanged; runtime JS may adjust the meta values after hydration without modifying the source file.

---

## 7. Contrast guidance

| Pair | Ratio | Use |
|---|---:|---|
| `#3E5172` on white | 8.00:1 | Primary text/actions |
| `#FFFFFF` on `#3E5172` | 8.00:1 | Inverse text |
| `#71BCB7` on white | 2.19:1 | Decorative fill only |
| `#397F7B` on white | 4.67:1 | Safe teal text/focus |
| `#DAC3A3` / `#D7C5A1` on white | ~1.7:1 | Warm backgrounds only |
| `#3E5172` on sand/wheat | ~4.7:1 | Accepted with narrow margin |
| Prototype sand focus on dark navy | 7.36:1 | Kept in dark mode |

Raw secondary/Accent colours are never used as small text on white.

---

## 8. Residual work

Some page-local components still carry inline `var(..., fallback)` declarations or legacy literals. They inherit the new tokens where they use shared classes, but a complete literal-by-literal cleanup of every retained page was not performed. This is documented rather than claimed as complete.
