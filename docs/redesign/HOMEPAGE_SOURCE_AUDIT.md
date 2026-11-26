# PCDR PRO — Homepage Source Audit

**Phase:** 2 — Homepage Download and Analysis
**Date:** 2026-11-26
**Status:** ✅ Source downloaded and fully inspected

---

## 1. Download verification

| Item | Value |
|---|---|
| Supplied URL | `https://drive.google.com/file/d/11q3Nk5P5EIGbgTbGwnLwDhD9HmRpQLR3/view?usp=drivesdk` |
| Public access | Yes — no authentication required |
| Download method | `drive.usercontent.google.com/download?id=…&export=download&confirm=t` |
| HTTP status | 200, `content-disposition: attachment; filename="parc-gaza-website.zip"` |
| Downloaded size | 198,573 bytes |
| ZIP SHA-256 | `1852e4153ea274bf14d46f16cb504e525bd65700050477811ec60a0956633c18` |
| Archive type | Valid ZIP (extracted with `unzip`) |
| Preserved source | `docs/redesign/source-homepage/` (ZIP + both extracted files, hashes verified) |

**No substitute design was created.** Every finding below comes from the actual
supplied file.

---

## 2. Complete file inventory

| File | Size | SHA-256 | Notes |
|---|---:|---|---|
| `index.html` | 186,034 bytes | `32ec0224e632708f6941369ff60a50c9c16197d4de344591a5401a16ad6e84f4` | 773 lines; single self-contained page: inline SVG sprite, inline `<style>`, inline `<script>` |
| `logo.jpg` | 90,908 bytes | `1aedb3ecec106d3de83f1bd5e99338487fbe880b89831559c116786af5bdfa4e` | 800×800 JPEG; official PCDR round logo; standalone copy of the embedded data URI |

There are **no** external CSS/JS files, no fonts bundled, no icon fonts, and no
additional photographs. The HTML embeds the same JPEG logo three times as a
`data:image/jpeg;base64,…` URI (42,911 characters each) plus references the
standalone `logo.jpg`.

Only external dependency: Google Fonts (`Cairo` 600/700/800, `Tajawal`
400/500/700) loaded via `fonts.googleapis.com`.

---

## 3. Document structure and semantics

- `<html lang="ar" dir="rtl">` — Arabic, right-to-left. No English variant supplied.
- `<meta charset>`, responsive viewport with `viewport-fit=cover`.
- `<title>جمعية العمل التشاركي</title>` — no meta description or Open Graph tags in source.
- Inline SVG `<symbol>` sprite with 14 icons: `i-arrow`, `i-cal`, `i-chev`, `i-clock`, `i-mail`, `i-play`, `m-bars`, `m-book`, `m-doc`, `m-drop`, `m-home`, `m-people`, `m-sun`, `m-wheat`.
- One `<header>` (floating glass pill), one `<nav>` (dropdown), one `<main>`, ten `<section>` elements (including three footer sub-sections), three `<article>` cards, one `<footer>`.

### Section inventory

| # | Section ID | Type | Arabic heading | Purpose |
|---|---|---|---|---|
| 0 | — | top strip | date / Hijri date / clock | Live Gregorian + Umm al-Qura dates and time |
| 1 | — | header | جمعية العمل التشاركي | Logo, name, subtitle "في الأزمات والكوارث – قطاع غزة", menu button |
| 2 | `menu` | dropdown nav | القائمة الرئيسية | About / Programs / Media / Partners / Contact / Tenders / Jobs |
| 3 | — | hero carousel | أبرز برامج الجمعية | 5 gradient slides with SVG motifs, tag, headline, description; progress bars + play/pause; touch swipe |
| 4 | — | ticker | أهم الأخبار | Red label + rotating headline links |
| 5 | `impact` | stats | بصمتنا في المجتمع / مجالات عملنا | 11 strategic axes + 2027 planning targets with explicit caveat |
| 6 | `programs` | numbered list | برامجنا / محاور عملنا | 11 strategic program axes (01–11) |
| 7 | `news` | tabbed content | آخر الأخبار | Tabs: أخبار وأنشطة / الإصدارات / المشاريع; 3 news items, 4 publications, 4 projects |
| 8 | `albums` | media grid | ألبوم الصور | 5 photo-album placeholders (gradient thumbs, no real photos supplied) |
| 9 | `about` | tabbed content | رسالتنا ورؤيتنا | Tabs: رسالتنا / أهدافنا / رؤيتنا; logo mark, mission, 11 objectives, vision, 10 values |
| 10 | `stories` | cards | قصص نجاح | 3 success-story placeholders with privacy notices |
| 11 | `contact` | footer | عن الجمعية / اتصل بنا / المرئيات | About blurb, complaints channel, social links (X, Facebook), film, copyright |

All navigation links are in-page anchors (`#about`, `#programs`, `#news`,
`#albums`, `#contact`, `#stories`, `#targets`, `#top`) or `href="#"` for
not-yet-available items (Board, Partners, Tenders, Jobs, complaints).

---

## 4. Visual system

### Color tokens (`:root`)

| Token | Light | Dark | Use |
|---|---|---|---|
| `--bg` | `#F1F4F8` | `#0D1526` | page background |
| `--surface` | `#FFFFFF` | `#15203A` | cards / panels |
| `--ink` | `#14213B` | `#E9EEF7` | primary text |
| `--ink-soft` | `#4B5873` | `#A6B2CA` | secondary text |
| `--line` | `#D3DAE6` | `#2A3857` | borders |
| `--rule` | `#1B2B4B` | `#8EA3CF` | heavy rules / numbers |
| `--accent` | `#237E7A` | `#5CB8B2` | teal accent (tabs, links) |
| `--accent-ink` | `#FFFFFF` | `#0D1526` | text on accent |
| `--sand` | `#D8B77F` | unchanged | gold pill / counters |
| `--sand-ink` | `#8A6A2C` | `#E3C48F` | eyebrow numerals |
| `--deep` | `#1B2B4B` | `#0A1120` | hero / footer navy |
| `--deeper` | `#111B31` | `#070C17` | top strip / safe area |
| `--on-deep` | `#EEF2F8` | unchanged | text on navy |
| `--on-deep-soft` | `#B4C0D6` | unchanged | muted text on navy |
| `--alert` | `#C23B30` | unchanged | ticker label |
| `--shadow` | `0 10px 30px rgba(20,33,59,.10)` | darker in dark mode | elevation |

Palette is explicitly derived from the logo (navy ring, teal drop, sand sun).

### Typography

- Headings: **Cairo** 600/700/800 with `Segoe UI, Tahoma, Arial` fallback.
- Body: **Tajawal** 400/500/700 with same fallbacks.
- Base: `400 16px/1.75`; headline `clamp(30px,5.6vw,58px)`, weight 800, line-height 1.28, `text-wrap: balance`; stat numerals `clamp(44px,10.5vw,100px)` with tabular figures.
- Logical spacing throughout: `padding-inline`, `margin-inline`, `border-inline-start`, `inset`, making RTL/LTR switching straightforward.
- Safe-area insets used for header, top bar and footer bottom.

### Layout, grids, breakpoints

- Max content width `1180px` (`.wrap`) with `max(16px, safe-area)` inline padding.
- Hero min-height `clamp(520px,78vh,720px)`, full-bleed gradient slides, 0.9s opacity crossfade, SVG motif at 20% opacity.
- Impact stats: 2-column grid → 4 columns at `min-width:760px`.
- News: 1 column → `1.15fr 1fr` at `min-width:900px`.
- Albums: 2 columns → 3 columns at `min-width:900px` (first item spans 2 rows/columns).
- About: stacked → `auto minmax(0,1fr)` at `min-width:760px`; goals 1 → 2 columns.
- Footer: 1 column → `1.4fr 1fr 1fr` at `min-width:860px`.
- Small-screen overrides at `max-width:560px`.
- Dark mode: `prefers-color-scheme: dark` plus explicit `data-theme="dark"|"light"` override.
- Reduced motion: `@media (prefers-reduced-motion: reduce)` disables animation/transition and JS auto-rotation/ticker.

### Animations

`drop` (menu), `fade` (ticker), slide crossfade, tab clip-path polygons,
arrow nudge on stat hover, `.paused` play/pause state swap, 6.5s slide interval,
4.8s ticker interval. All CSS transitions are disabled under reduced-motion.

---

## 5. JavaScript behavior

Vanilla IIFE, no framework or external dependency:

1. **Date strip** — `Intl.DateTimeFormat` with `ar-u-ca-gregory-nu-arab`,
   `ar-u-ca-islamic-umalqura-nu-arab`, and Arabic numerals; updates every 30 s.
2. **Dropdown menu** — toggle, outside-click close, Escape-to-close with focus
   restore, accordion sub-menus (`aria-expanded`, `.open`), active-link marking,
   auto-close after selection.
3. **Hero carousel** — 5 slides, `show(i)`, progress bars with `aria-current`,
   play/pause with bilingual label, touch swipe threshold 50 px, timer reset.
4. **Ticker** — clones the link node every 4.8 s across four headline strings.
5. **Tabs** — generic `[data-tabs]` controller with `role="tab"`/`role="tabpanel"`,
   `aria-selected`, roving `tabIndex`, Arrow/Home/End keyboard navigation, plus
   `[data-open-tab]` deep-link handling from the menu.

---

## 6. Assets, icons and media

- Logo: 800×800 JPEG, used in header and twice in the About tab (as CSS/HTML images).
- Hero slide backgrounds: pure CSS gradients (`.s1`–`.s5`); `--photo` custom
  property is available for real photography but **no photo URLs are supplied**.
- Thumbnails (news, albums, stories): gradient placeholders with SVG motifs,
  driven by `--photo` / `--g`; no real photographs in the archive.
- Icons: inline SVG sprite (14 symbols), no icon font, no external icon CDN.
- Fonts: remote Google Fonts only.

**Implication for integration:** the supplied design does not include authentic
program photos. Album/news/story imagery must remain tasteful placeholders or
be sourced only from verified institutional assets (see `MEDIA_ASSETS_REGISTER.md`);
fake documentary imagery is prohibited.

---

## 7. Accessibility observations (to preserve and improve)

Already present: `aria-hidden` on slides, `aria-current` on bars, carousel
`aria-roledescription`, `role="tab"`/`tabpanel`, keyboard tab navigation,
Escape handling, `:focus-visible`, reduced-motion support, safe-area handling,
Arabic numerals via `Intl`, semantic headings, hidden menu managed with `hidden`.

Improvements to make during integration: add `<meta name="description">` and
Open Graph tags, an English/LTR equivalent driven by locale, real `href`
targets where routes exist, `alt` text for all images, `aria-live` for the
ticker, and visible skip link if the host layout does not already provide one.

---

## 8. Content provenance notes

- The strategic slogan, 11 axes, mission, objectives, vision and values match
  the project's approved 2027–2029 planning documentation (to be cross-checked
  in `EXISTING_SYSTEM_AUDIT.md` / `SEED_DATA_REGISTER.md`).
- The impact section explicitly labels figures as
  **"مستهدفات تخطيطية أولية لعام 2027، تخضع للمراجعة بعد تحديد التمويل المتاح
  والقدرات التنفيذية"** — planning targets, not achievements. This caveat must
  be preserved verbatim and must never be converted into reported results.
- Success stories are explicitly marked as publishable only after consent and
  privacy review; they are placeholders, not documented beneficiary stories.
- Publications and film are marked as "مرحلة التأسيس" / upcoming; they must not
  be presented as available downloads unless verified.

---

## 9. Fidelity contract

The integration must preserve: RTL-first layout, navy/teal/sand palette, Cairo +
Tajawal typography, floating pill header, 5-slide gradient hero with SVG motifs,
red news ticker, numbered section eyebrows (`01`–`05`), numbered program axes,
tab interaction patterns, stat cards, gradient thumbnails, deep navy footer,
dark-mode tokens, and all breakpoint behavior. Changes are limited to semantics,
accessibility, localization, performance, and CMS-driven content binding.
