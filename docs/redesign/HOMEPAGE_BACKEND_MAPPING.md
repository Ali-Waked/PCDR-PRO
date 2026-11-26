# PCDR PRO — Homepage Backend Mapping

**Date:** 2026-11-26  
**Status:** Implementation contract. Verification is recorded in `HOMEPAGE_TEST_REPORT.md`.  
**Governance:** `NOT CLEARED`. This mapping is technical only; it does not resolve open decisions or authorize Phase 6.

---

## 1. Data sources

The approved homepage is rendered from the existing `homepage_sections`, `homepage_section_translations`, `homepage_section_items` and `homepage_section_item_translations` tables. Media continues to come from the existing media library via `HomepageService::serializeMedia()`, with restricted media omitted.

`HomepagePrototypeContentSeeder` is the canonical source for prototype section order and source-derived content. It is idempotent: existing translations are not overwritten, legacy-only sections are disabled rather than deleted, and the homepage cache is flushed after seeding.

---

## 2. Section contract

| Order | Key | Vue component | Section-level fields | Item tabs / key prefixes |
|---:|---|---|---|---|
| 0 | `hero` | `ProHeroCarousel` | `title`, `eyebrow`, `body`, `ctaLabel`, `ctaLink` | `slide-*`; `motifKey` |
| 1 | `ticker` | `ProTicker` | `tickerLabel` | headline items; `url` |
| 2 | `impact` | `ProImpact` | `title`, `body` | `target-*`, `axis-*`; `display`, `unit` |
| 3 | `programs` | `ProProgramAxes` | `eyebrow`, `title`, `body` | `axis-*`; ordered 01–11 |
| 4 | `news` | `ProNewsTabs` | `title`, `body`, `tabLabels` | `news`, `publications`, `projects`; `tab`, `featured`, `dateLabel`, `ctaLabel` |
| 5 | `albums` | `ProMediaGallery` | `eyebrow`, `title`, `body` | `album-*`; `caption` |
| 6 | `about` | `ProAboutTabs` | `eyebrow`, `title`, `body`, `tabLabels` | `mission`, `objectives`, `vision`, `value-*`; `tab` |
| 7 | `stories` | `ProStories` | `eyebrow`, `title`, `body` | `story-*`; `caption` |
| 8 | `footer` | `ProPrototypeFooter` | `title`, `body`, `videoUrl`, `videoCaption` | `about-*`, `contact-*`, `media-*` |

Agreed tab values are `news`, `publications`, `projects`, `mission`, `objectives`, `vision`. The About tab label keys are `mission`, `objectives`, `vision`; the DOM id for `objectives` remains `goals` for prototype fidelity.

---

## 3. Additive schema

Migration: `database/migrations/2026_11_26_090000_enhance_homepage_prototype_content.php`

### `homepage_section_translations`

- `ticker_label` (string, nullable)
- `tab_labels` (json, nullable)
- `video_url` (string, nullable)
- `video_caption` (string, nullable)

### `homepage_section_items`

- `tab` (string 32, nullable)
- `is_featured` (boolean, default `false`)
- `motif_key` (string 32, nullable)

### `homepage_section_item_translations`

- `date_label` (string, nullable)
- `cta_label` (string, nullable)
- `caption` (text, nullable)
- `unit` (string 64, nullable)

No existing column is changed or removed. `down()` drops only these additive columns.

The CMS editor may submit public camel-case names (`tickerLabel`, `tabLabels`,
`videoUrl`, `videoCaption`, `dateLabel`, `ctaLabel`, `display`). The controller
normalises them to the stored snake-case columns before writing, rather than
using Eloquent accessors on the snake-case columns. This avoids mutator
recursion while keeping the public JSON contract camel-case.

---

## 4. Serialized fields

`HomepageService` serializes the following prototype fields in addition to the existing contract:

**Section:** `tickerLabel`, `tabLabels`, `videoUrl`, `videoCaption`.

**Item:** `tab`, `featured`, `motifKey`, `dateLabel`, `ctaLabel`, `caption`, `unit`.

Published items are sorted by `sort_order` then `id`. Restricted media and unresolved model items are omitted. Media URLs are resolved through the existing public/private disk logic.

Public consumers receive only published, enabled sections:

```text
HomeController@index → HomepageService::sections($locale)
GET /api/public/homepage?locale=ar|en → same service contract
Cms/HomepageController@preview → includeUnpublished=true, onlyEnabled=false
```

Cache keys vary by locale, enabled-only/all, and published/all-status. Every homepage mutation flushes all variants.

---

## 5. Safe-link boundary

Links are validated in `UpdateHomepageRequest::isSafeLink()` and sanitized again in `HomepageService::safeLink()` so the Inertia payload and public API never emit unsafe raw values.

Accepted:

- `#anchor` matching `/^#[A-Za-z0-9_:-]+$/`
- a single-slash internal path not starting with `//`
- `https://` URLs

Rejected:

- backslashes and encoded backslashes (`\`, `%5c`, case-insensitive)
- whitespace, control characters, newlines, leading/trailing whitespace
- protocol-relative `//`
- `http://`, `javascript:`, `data:`, `mailto:` and any other scheme

Unsafe `url`, `ctaLink` and `videoUrl` values become `null`. The client composable `useHomepageContent.safeUrl()` mirrors the same rules as defence in depth, but is not the security boundary.

---

## 6. CMS routes

| Method | URI | Name | Permission |
|---|---|---|---|
| POST | `/{locale}/cms/homepage/sections` | `cms.homepage.sections.store` | `homepage.update` |
| DELETE | `/{locale}/cms/homepage/sections/{homepageSection}` | `cms.homepage.sections.destroy` | `homepage.update` |
| POST | `/{locale}/cms/homepage/reorder` | `cms.homepage.reorder` | `homepage.update` |
| PATCH | `/{locale}/cms/homepage/{homepageSection}/status` | `cms.homepage.status` | `homepage.update` |
| POST | `/{locale}/cms/homepage/{homepageSection}/toggle` | `cms.homepage.toggle` | `homepage.update` |

Store defaults to `published` and appends after the current maximum `display_order` unless explicitly supplied. Destroy removes the section and its translations/items, audits the action and flushes cache.

---

## 7. Content authenticity

- Source `href="#"` placeholders are stored as `null`; they are never promoted to links.
- `#anchor` values used in seeded content are internal prototype anchors only.
- No media, social, video, donation, partner, donor, testimonial or publication-download URL is stored without verification.
- Planning figures retain the verbatim caveat: `مستهدفات تخطيطية أولية لعام 2027، تخضع للمراجعة بعد تحديد التمويل المتاح والقدرات التنفيذية.`

---

## 8. Known limitations

- Publication workflow bypass remains unchanged: direct `published ↔ draft` transitions are still accepted because existing tests depend on that behaviour. No governance approval transition was added.
- Hardcoded prototype fallback copy in Vue components is source-derived scaffolding. It is not an approval of unresolved content ownership decisions (OQ-05, OQ-06).
- Browser chrome `theme-color` remains pinned by `resources/views/app.blade.php`; explicit light/dark/system handling is applied in non-pinned CSS/JS scopes.
