# PCDR PRO — Official Logo Asset Audit

**Date:** 2026-11-26
**Scope:** Authentication redesign (`pcdr-pro` only; `pcdr` inspected read-only)
**Related:** `docs/redesign/LOGIN_DESIGN_SPECIFICATION.md`, `docs/redesign/LOGIN_ANIMATION_IMPLEMENTATION.md`

---

## 1. Outcome

The exact official PCDR logo already present in `pcdr-pro` was reused. **No asset was copied, redrawn, regenerated, or approximated.** No AI-generated logo, generic humanitarian icon, or text-only substitute was introduced. The original `pcdr` directory was not modified.

Two official assets are used, both already committed in `pcdr-pro`:

| Purpose | Public URL | Absolute path |
|---|---|---|
| Static brand mark (all auth pages) | `/assets/pcdr-homepage/logo/pcdr-logo.png` | `public/assets/pcdr-homepage/logo/pcdr-logo.png` |
| Animated intro mark (login only) | `/assets/pcdr-homepage/logo/pcdr-logo-animated.svg` | `public/assets/pcdr-homepage/logo/pcdr-logo-animated.svg` |

---

## 2. Verified asset properties

### 2.1 Static logo — `pcdr-logo.png`

| Property | Value |
|---|---|
| Dimensions | **640 × 640 px** (square, preserved 1:1) |
| Format | PNG, 8-bit RGBA with transparency |
| File size | **399,237 bytes** |
| SHA-256 | `5834fb72446a75cd56ed6a51a06ae1dafcaa56559c62e6906603978eb27ecb9e` |
| Rendered in auth shell | 56 × 56 px (`object-fit: contain`, white rounded tile) |

Verification command:

```bash
sha256sum public/assets/pcdr-homepage/logo/pcdr-logo.png
python3 -c "import struct;d=open('public/assets/pcdr-homepage/logo/pcdr-logo.png','rb').read(24);print(struct.unpack('>II',d[16:24]))"
```

### 2.2 Animated logo — `pcdr-logo-animated.svg`

| Property | Value |
|---|---|
| ViewBox | **`0 0 1130 1130`** (square, preserved 1:1) |
| File size | **68,974 bytes** |
| SHA-256 | `7dc83babf9f49f13e49d381d4c928e616cb41bf1285a6ead3310488aca98a80b` |
| Root element | Native `<svg>` with embedded `<style>` keyframes |
| Accessibility | `role="img"`, `aria-label="PCDR"` in the source; the intro renders it under `aria-hidden="true"` because the adjacent identity text carries the accessible name |

The SVG already contains its own restrained, staggered keyframes (`ringIn`, `grow`, `sway`, `fadeUp`, `rise`, `pulse`, `dropIn`, `bob`) using the project's decelerating ease `cubic-bezier(.16,1,.3,1)`. These paths and timings were **not edited**. The intro wraps the file in a plain `<img>` so the original SVG structure, colours, typography, composition, and viewBox remain byte-identical.

Verification command:

```bash
sha256sum public/assets/pcdr-homepage/logo/pcdr-logo-animated.svg
head -c 400 public/assets/pcdr-homepage/logo/pcdr-logo-animated.svg
```

---

## 3. How the official version was identified

The static PNG is explicitly registered as the official logo in:

- `docs/architecture/PLATFORM_OWNER_AND_DASHBOARD_FOUNDATION_REPORT.md` (static logo register)
- `docs/redesign/MEDIA_ASSETS_REGISTER.md` (approved media register)
- `resources/js/Components/Public/SiteHeader.vue` — public header renders it at 48 × 48 px
- `resources/js/Components/Public/SiteFooter.vue` — public footer renders it at 64 × 64 px
- `resources/js/Pages/Public/Home.vue` — homepage intro overlay
- `public/favicon.ico` / homepage link media

The animated SVG sits in the same `logo/` directory and shares the same square composition and palette as the static mark; it was adopted for the intro because it is the official vector variant already shipped with the homepage assets, not a newly authored animation.

The read-only comparison in the original `pcdr` project confirmed the same asset bytes; no copy was necessary because `pcdr-pro` already contained the exact files.

---

## 4. Brand palette observed in the official identity

The auth system uses only the approved identity colours. Auth tokens are aliases of the immutable palette in `resources/css/tokens.css`:

| Token | Hex | Use in auth |
|---|---|---|
| Navy | `#3E5172` | Brand panel gradient end, language switch thumb, focus borders |
| Teal | `#71BCB7` | Accent line, intro halo, success mark, focus ring |
| Sand | `#DAC3A3` | Eyebrow text, intro halo border |
| Wheat | `#D7C5A1` | Intro/identity accents (from the immutable token family) |
| White | `#FFFFFF` | Form surface, logo tile, primary text on navy |

Darker navy stops (`#25334B`, `#30415E`) are existing homepage gradient stops, not new colours. No unrelated hue was introduced.

---

## 5. Typography

The auth shell inherits the application's approved fonts (`IBM Plex Sans Arabic` for Arabic, `Inter` for English) from `resources/css/app.css`. No new font family was added. Arabic letter-spacing is neutralised in `dashboard.css` for uppercase eyebrows and headings so Arabic does not receive Latin tracking.

---

## 6. Acceptance

- [x] Official logo located and verified by SHA-256.
- [x] Exact asset reused (no copy, no edit, no recreation).
- [x] Original proportions preserved (640 × 640 PNG; 1130 × 1130 SVG viewBox).
- [x] Original SVG paths, colours, and embedded animation preserved.
- [x] No generic icon or text-only substitute.
- [x] `pcdr` source project untouched.
