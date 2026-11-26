# PCDR PRO — Homepage Content Mapping

**Phase:** 2/5 — Design-to-backend content classification
**Date:** 2026-11-26
**Status:** Draft aligned to the supplied homepage and the existing data model

---

## 1. Classification legend

- **A — Static structural:** layout, tokens, icons, interaction scaffolding. No database row.
- **B — Administrator-managed:** editable through the CMS; stored in existing
  homepage section/item tables.
- **C — Existing module:** content already owned by another entity; reuse, do
  not duplicate.
- **D — New capability:** genuinely missing backend behavior required to serve
  the page dynamically.

---

## 2. Section-by-section mapping

| # | Homepage element | Class | Backend owner | Fields / notes |
|---|---|---|---|---|
| 1 | Top strip (Gregorian/Hijri date, clock) | A | — | Client-side `Intl`; no content row |
| 2 | Header pill: logo + "جمعية العمل التشاركي" + "في الأزمات والكوارث – قطاع غزة" | C | Site settings (`settings`) + media library | Reuse `SettingsService` / `site_settings`; logo already uploadable |
| 3 | Dropdown menu (About / Programs / Media / Partners / Contact / Tenders / Jobs) | C | `NavigationMenu` / `NavigationItem` | Reuse existing localized navigation; `href="#"` placeholders stay structural until routes exist |
| 4 | Hero carousel shell, 5 gradient backgrounds, SVG motifs, controls | A + B | `homepage_sections` + `homepage_section_items` | Slides become section items: `tag`, `title`, `body`, `sort_order`, `is_visible`, optional `media_id` + `alt_text` |
| 5 | Ticker label "أهم الأخبار" + rotating headlines | B | `homepage_sections` items (new `ticker` section) | Headline text + target URL; no new table |
| 6 | Impact eyebrow "بصمتنا في المجتمع" + "11 محوراً" | B | Homepage section translations | Existing `eyebrow`, `title`, `body` fields |
| 7 | Impact planning figures (3,000 أسرة, 1,500, 50, 100, 100, 500, 200, 100, 10) | B | `homepage_section_items.numeric_value` + translations | **Must keep the planning-target caveat verbatim**; never publish as achievement |
| 8 | Programs section: 11 numbered axes | C + B | Existing `pillars` / `programs` OR homepage items | Prefer existing pillar/program entities where approved; homepage items may hold display order and intro copy |
| 9 | News tab — 3 news cards | C | `articles` (+ translations, media) | Published-only scope via `Publishable` |
| 10 | Publications tab — 4 publications | C | `publications` | Respect restricted/private download rules |
| 11 | Projects tab — 4 project cards | C | `projects` | Published-only scope |
| 12 | Albums — 5 thumbnails | C | `media` library (image type) | No photos supplied; placeholders must remain labeled or use verified media only |
| 13 | About tabs: mission / 11 objectives / vision / 10 values | B | Homepage sections + items | Existing `about` section can hold three tab groups as items; no new table |
| 14 | Success stories — 3 cards | B | Homepage section items (type `inline`) | Privacy notice text is part of the item body; never invent beneficiary stories |
| 15 | Footer: about blurb, complaints text, social links, copyright, film | C | Site settings + homepage footer items | Social links/copyright already settings-driven; "الفيلم التعريفي" remains a clearly marked upcoming item until a verified video exists |
| 16 | Dark mode tokens, reduced motion, breakpoints, RTL logic | A | — | Preserve exactly; no CMS control required |

---

## 3. Required backend relationships

```
HomepageSection (existing)
 ├─ HomepageSectionTranslation (existing, ar/en)
 └─ HomepageSectionItem (existing)
     ├─ HomepageSectionItemTranslation (existing, ar/en)
     ├─ media_id → Media (NEW relationship on item)
     └─ item_type ∈ inline|pillar|article|publication|page|project (existing enum-like field)

Content entities reused (existing, published-only):
 Article, Publication, Project, Pillar, Program, Page, Media, NavigationItem

Site-wide configuration reused (existing):
 SettingsService / site_settings (org name, logo, contact, social links)
```

No new table is needed for: ticker, impact stats, program axes, about tabs,
values, success-story placeholders, or section ordering. These fit the existing
`homepage_sections` / `homepage_section_items` / translations structure.

---

## 4. Public API contract (to implement)

`GET /api/public/homepage?locale=ar|en`

Returns a single consistent envelope:

```json
{
  "data": {
    "locale": "ar",
    "dir": "rtl",
    "organization": { "name": "...", "tagline": "...", "logo": "..." },
    "sections": [
      {
        "id": 1,
        "key": "hero",
        "type": "hero",
        "sort_order": 1,
        "eyebrow": "...",
        "title": "...",
        "body": "...",
        "items": [
          { "key": "slide-1", "label": "...", "title": "...", "body": "...",
            "media": { "url": "...", "alt": "..." } }
        ]
      }
    ]
  }
}
```

Rules: published + enabled only, locale-resolved with documented fallback,
eager-loaded translations/media/references, no admin-only fields, cacheable with
explicit invalidation on CMS updates. Additional public endpoints reuse
existing controllers' published scopes (`/api/public/organization`, `programs`,
`projects`, `news`, `events`, `gallery`) only where the homepage actually needs
them; no speculative endpoints.

---

## 5. CMS edits required (minimal)

| Capability | Existing state | Action |
|---|---|---|
| Edit section titles/body/eyebrow AR/EN | ✅ exists | Keep; switch to tab UX |
| Enable/disable section | ✅ `is_enabled` | Keep |
| Reorder sections | numeric input only | Add accessible move controls + reorder endpoint |
| Draft/publish section | ❌ | Add `status` to sections + filter in public API |
| Attach image + AR/EN alt text | ❌ on homepage | Reuse `ContentMediaField` + validation |
| Preview unpublished changes | static list only | Add CMS-only preview route |
| Permission gating in UI | server-only | Pass `can` prop from controller |
| Cache invalidation on update | ❌ for homepage | Invalidate homepage cache key on update |

---

## 6. Accuracy guardrails

- Planning targets stay labeled as planning targets with the original caveat.
- No beneficiary counts, budgets, donors, certifications, testimonials, or
  partner names may be added beyond verified documentation.
- Album/story/publication placeholders remain explicitly provisional until
  verified assets/content exist.
- English copy is a translation of verified Arabic content, not new claims.
