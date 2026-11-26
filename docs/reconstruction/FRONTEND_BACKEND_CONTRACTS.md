# PCDR PRO — Frontend/Backend Contracts

**Date:** 2026-11-26  
**Status:** Implemented contract reference. Final verification is recorded in `docs/reconstruction/TEST_REPORT.md`.  
**Governance:** `NOT CLEARED`. This contract does not approve ADR-001–017, close OQ-24–29 or OQ-49, authorize Phase 6, or deploy.

---

## 1. Contract surfaces

| Surface | Source | Consumer | Scope |
|---|---|---|---|
| Inertia homepage | `Public\HomeController@__invoke` | `resources/js/Pages/Public/Home.vue` | Published, enabled sections |
| Public JSON API | `GET /api/public/homepage?locale=ar|en` | External/read-only consumers | Published, enabled sections |
| CMS preview | `GET /{locale}/homepage/preview` | CMS editor | All sections, including draft/disabled |
| CMS editor payload | `Cms\HomepageController@update` | Homepage editor form | Bilingual section/item fields |
| Theme prop | `HandleInertiaRequests::share()` | All Inertia pages | `light|dark|system` |

Both public surfaces call `HomepageService::sections($locale)` and therefore share the same section/item shape before API resource formatting.

---

## 2. Inertia `Public/Home` props

`HomeController` supplies:

| Prop | Type | Purpose |
|---|---|---|
| `vision`, `mission` | `string|null` | Existing configuration-driven copy |
| `pillars` | `array` | Published pillars with `code`, `title`, `shortTitle`, `description`, `url` |
| `framework` | `array` | Impact framework steps with `key`, `label` |
| `articles` | `array` | Three featured articles with id/title/excerpt/date/category/url/image |
| `programs` | `array` | Six published programs with id/title/summary/pillar/url |
| `publications` | `array` | Four featured publications, including signed-safe download fields |
| `sections` | `array` | Canonical CMS-managed homepage content |
| `meta` | `object` | SEO metadata from the shared public controller |

The prototype components consume `sections`. The other props remain available for compatibility and are not used to substitute approved prototype content.

Shared layout props include `locale`, `direction`, `availableLocales`, `translations`, `auth`, `authority`, `adminNavigation`, `siteSettings` and `theme`.

---

## 3. Public API envelope

`GET /api/public/homepage?locale=ar|en` returns:

```json
{
  "data": {
    "locale": "ar",
    "dir": "rtl",
    "organization": {
      "name": "...",
      "tagline": "...",
      "logo": "https://... or null"
    },
    "sections": []
  }
}
```

`locale` is validated against `config('website.locales')` and defaults to the configured default locale. `dir` is `rtl` for Arabic, otherwise `ltr`. Responses carry `Cache-Control: public, max-age=60`. The route is throttled to 60 requests per minute.

---

## 4. Section object

`HomepageService` produces the following fields for every section:

| Field | Type | Notes |
|---|---|---|
| `id` | int | Database id |
| `key` | string | Canonical section key |
| `type` | string | Existing section type |
| `status` | string | `published` or `draft` |
| `publishedAt` | string ISO-8601 or null | Publication timestamp |
| `enabled` | bool | Visibility toggle |
| `order` | int | Display order |
| `eyebrow` | string or null | Localized |
| `title` | string or null | Localized |
| `body` | string or null | Localized |
| `ctaLabel` | string or null | Localized, safe text |
| `ctaLink` | string or null | Localized, sanitized URL |
| `tickerLabel` | string or null | Added field |
| `tabLabels` | object or null | Added field; decoded JSON |
| `videoUrl` | string or null | Added field, sanitized URL |
| `videoCaption` | string or null | Added field |
| `items` | array | Published, sorted items |

The API resource renames `order` to `sort_order` and `ctaLabel`/`ctaLink` to `cta_label`/`cta_link`; added fields retain camelCase. CMS preview returns the service shape directly.

Canonical section order:

```text
hero → ticker → impact → programs → news → albums → about → stories → footer
```

---

## 5. Item object

Inline items have `type: "inline"`. Model-backed items resolve through the existing item model and include resolved media.

| Field | Type | Notes |
|---|---|---|
| `id` | int | Inline item id or resolved model id |
| `key` | string | `item_key` or generated key |
| `type` | string | `inline` or model item type |
| `sortOrder` / API `sort_order` | int | Stable order |
| `label`, `title`, `body` | string or null | Localized |
| `display` | string or null | From `display_value`; planning/target display text |
| `url` | string or null | Sanitized |
| `value` | float or null | Numeric value when present |
| `tab` | string or null | News/about tab key |
| `featured` | bool | From `is_featured` |
| `motifKey` | string or null | Decorative motif selector; not an invented image URL |
| `dateLabel` | string or null | Display-only date text |
| `ctaLabel` | string or null | Display-only CTA text |
| `caption` | string or null | Localized caption |
| `unit` | string or null | Localized unit suffix |
| `media` | object or null | `id`, `url`, `alt`, `caption`, `width`, `height` |
| `published` | bool | Always true in public payloads |

Agreed tab values: `news`, `publications`, `projects`, `mission`, `objectives`, `vision`. The About tab key `objectives` keeps the DOM id `goals` for prototype fidelity.

---

## 6. Safe-link contract

Server validation and serialization use the same rule set:

| Input | Result |
|---|---|
| `#top`, `#news` | accepted |
| `/ar/about`, `/path?x=1` | accepted |
| `/path#section` | accepted |
| `https://example.org/path` | accepted |
| `//example.org` | rejected → `null` |
| `http://example.org` | rejected → `null` |
| `javascript:…`, `data:…`, `mailto:…` | rejected → `null` |
| `\evil`, `%5cevil`, `\u0000`, newlines, surrounding whitespace | rejected → `null` |

`UpdateHomepageRequest::isSafeLink()` is the authoritative write boundary. `HomepageService::safeLink()` is the authoritative read boundary. The Vue composable mirrors the rules for local rendering only and is not a security boundary.

Source `href="#"` placeholders are stored and emitted as `null`; they are not promoted to links.

---

## 7. CMS editor payload normalization

The editor may submit camelCase public names. The controller normalizes them before writing snake-case columns rather than defining recursive Eloquent aliases:

| Submitted alias | Stored column |
|---|---|
| `tickerLabel` | `ticker_label` |
| `tabLabels` | `tab_labels` |
| `videoUrl` | `video_url` |
| `videoCaption` | `video_caption` |
| `display` | `display_value` |
| `dateLabel` | `date_label` |
| `ctaLabel` | `cta_label` |

Snake-case keys win when both forms are supplied and the snake-case value is non-empty. The editor supports bilingual Arabic/English fields, section create/delete, publish/toggle/reorder, item metadata, safe-link hints and a structured preview.

---

## 8. Theme contract

| Name | Value |
|---|---|
| Shared prop | `theme` |
| Allowed values | `light`, `dark`, `system` |
| Guest fallback | `system` |
| Stored value | `null` for `system`; otherwise `light` or `dark` |
| Update | `PUT /{locale}/dashboard/profile` with optional `theme` |
| Runtime attribute | `data-theme` set only for explicit light/dark; deleted for system |
| Raw choice | `data-theme-preference` |
| Local storage | `pcdr-theme-preference`, guest/local fallback only |

The pinned `resources/views/app.blade.php` is unchanged. Runtime behavior is implemented in `resources/js/app.js`, `resources/js/composables/useTheme.js` and additive CSS.

---

## 9. Content authenticity boundaries

- Planning figures are targets or structural counts, not achievements.
- The planning caveat is verbatim: `مستهدفات تخطيطية أولية لعام 2027، تخضع للمراجعة بعد تحديد التمويل المتاح والقدرات التنفيذية.`
- No donor, partner, testimonial, certification, budget, social, film, download or contact destination was invented.
- Motif keys are decorative selectors, not claims of verified media.
- Hardcoded Vue fallback copy is source-derived scaffolding, not approval of OQ-05/OQ-06.
