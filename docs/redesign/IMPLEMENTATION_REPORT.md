# PCDR PRO — Homepage Redesign Implementation Report

**Date:** 2026-11-26
**Workspace:** `/root/PCDR/pcdr-pro`
**Contract:** `docs/redesign/ARCHITECTURE_INTEGRATION_PLAN.md`
**Governance:** `NOT CLEARED`. This report records additive technical work only. It does not approve ADR-001–017, close OQ-24–29, mark OQ-49 resolved, authorize Phase 6, or deploy.

---

## 1. Outcome

The supplied institutional homepage is now represented as a CMS-managed, bilingual, RTL-first layer inside the existing Laravel/Inertia application. The original `pcdr` directory was never modified; `pcdr-redesign` was never created; no remote was added and nothing was pushed.

The implementation reuses the existing `homepage_sections` / `homepage_section_items` tables, translations, media library, settings, permissions, `ContentQuery` / `Publishable` behaviour, and CMS UI primitives. It adds one additive migration, dedicated FormRequests, a single public JSON endpoint, scoped CSS and six focused Vue components.

---

## 2. Stages delivered

1. **Safe duplication and audit** — full history preserved, 33,103 files verified byte-identical at copy time, `origin` removed only in `pcdr-pro`, source homepage archived under `docs/redesign/source-homepage/`.
2. **Schema and service** — additive `status` / `published_at` on sections and `media_id` on items; `Publishable` on `HomepageSection`; `HomepageService::sections()` keeps its two-argument behaviour, excludes drafts by default, includes drafts only for CMS preview, and flushes all cache variants on mutation.
3. **Public API** — `GET /api/public/homepage?locale=ar|en` with `dir`, organization data, published/enabled sections, API Resources and `throttle:60,1`.
4. **CMS** — rewritten `Cms/HomepageController`, four FormRequests, status/toggle/reorder/preview routes, explicit item deletion, safe link/media validation, audit records, and a rewritten locale-aware editor with status, reorder, toggles, media picker and permission gating.
5. **Frontend** — `pcdr-pro-home.css`, `useHomepageContent.js`, six `Pro*.vue` components, and `Home.vue` bindings with all pinned markers preserved and legacy fallbacks retained.
6. **Seed data** — canonical caveat tightened, 53 source-derived additive items, registered after the canonical seeder.
7. **Registers and verification** — media/seed registers, security review, testing report and this implementation report.

---

## 3. Key contracts

### Canonical section keys

`hero`, `intro`, `areas`, `framework`, `programs`, `targets`, `community`, `partners`, `knowledge`, `cta`

The CMS no longer creates conflicting `transparency` / `partnerships` / `participation` fallback keys.

### Publication state

`status` (`draft`/`published`) and `is_enabled` are independent. Public surfaces require both published and enabled. `published_at` is set when publishing and cleared when reverting to draft.

### Public JSON envelope

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
        "status": "published",
        "publishedAt": "...",
        "enabled": true,
        "order": 0,
        "eyebrow": "...",
        "title": "...",
        "body": "...",
        "ctaLabel": null,
        "ctaLink": null,
        "items": [
          {
            "id": 10,
            "key": "slide-1",
            "type": "inline",
            "sortOrder": 10,
            "title": "...",
            "label": "...",
            "body": "...",
            "display": null,
            "url": null,
            "value": null,
            "media": null,
            "published": true
          }
        ]
      }
    ]
  }
}
```

Media entries, when present, contain `id`, `url`, `alt`, `caption`, `width`, `height`, with restricted media omitted.

---

## 4. Frontend mapping

| Pinned section | Implementation | Data source |
|---|---|---|
| `hero` | Five-slide carousel plus ticker | `hero` slide/ticker items |
| `about` | Mission/vision fallback or tabbed objectives/values | `intro` items, existing `mission`/`vision` props |
| `areas` | Numbered strategic pillars | published pillars |
| `approach` | Humanitarian approach steps | `framework` items |
| `programs` | Program tiles/axes | published programs and `programs` items |
| `targets` | 2027 planning figures plus mandatory caveat | `targets` items |
| `community` | Participatory roles, album placeholders, gated stories | `community` items |
| `partners` | Partnership categories | `partners` items |
| `knowledge` | News/publication lists and empty state | `ContentQuery`-derived props |
| `cta` | Contact/get-involved call to action | `cta` items and site settings |

Shared `SiteHeader` and `SiteFooter` remain the only header/footer. No duplicate navigation state was introduced.

---

## 5. Deviations from the plan

These deviations are deliberate and documented rather than silently performed:

1. **Component count:** The plan listed nine component names; six were implemented: `ProHeroCarousel`, `ProTicker`, `ProPlanningTargets`, `ProAboutTabs`, `ProMediaGallery`, `ProStories`. The planned `SiteTopStrip`, `ProgramAxes`, `KnowledgeTabs` and `ImpactImprint` roles are covered by existing shared header/section blocks plus the planning-target component. No behaviour required by the pinned DOM contract was dropped.
2. **Migration filename:** The actual additive migration is `2026_11_26_031657_enhance_homepage_publication_and_media.php` rather than the plan's illustrative `2026_11_27_*` name. Its contents match the additive schema contract.
3. **Formatter command:** `vendor/bin/pint --dirty --format agent` and multi-file Pint invocations hung in this environment. Targeted single-file Pint runs were used and passed for every changed PHP file.
4. **Ticker links:** Initial additive seed data used fragment URLs (`#news`, `#targets`). On review these were removed because no verified destination exists; ticker items now render as non-clickable text. A seeder assertion prevents regression.
5. **Full Playwright suite:** Not run, because the specs require a seeded server fixture and institutional design/content approvals are not recorded. A local HTTP smoke check verified `GET /ar` and the public API both return 200.
6. **Monolithic test suite:** Timed out without usable output. The suite was executed in four parallel batches instead; results are recorded in `TESTING_REPORT.md`.

---

## 6. Files owned by this work

- Docs: `HOMEPAGE_SOURCE_AUDIT.md`, `EXISTING_SYSTEM_AUDIT.md`, `HOMEPAGE_CONTENT_MAPPING.md`, `PROJECT_COPY_REPORT.md`, `ARCHITECTURE_INTEGRATION_PLAN.md`, `MEDIA_ASSETS_REGISTER.md`, `SEED_DATA_REGISTER.md`, `TESTING_REPORT.md`, `SECURITY_REVIEW.md`, `IMPLEMENTATION_REPORT.md`, plus preserved `source-homepage/` files.
- Schema/model: `database/migrations/2026_11_26_031657_enhance_homepage_publication_and_media.php`, `HomepageSection.php`, `HomepageSectionItem.php`.
- Requests/service/controller: four `app/Http/Requests/Cms/*Homepage*Request.php`, `HomepageService.php`, `Cms/HomepageController.php`, `Public/HomepageApiController.php`, `app/Http/Resources/Public/*`, `Actions/DeleteMedia.php`, additive routes in `routes/web.php`.
- Seeders: `HomepageContentSeeder.php` (caveat only), new `RedesignHomepageContentSeeder.php`, `DatabaseSeeder.php`.
- Frontend: `resources/css/pcdr-pro-home.css`, `resources/js/composables/useHomepageContent.js`, six `resources/js/Components/RedesignHome/Pro*.vue`, `Pages/Public/Home.vue`, `Pages/Cms/Homepage/Index.vue`.
- Tests: four `tests/Feature/RedesignHomepage*Test.php` plus `tests/homepage-content.test.mjs`.

Byte-pinned files were not edited: `resources/css/pcdr-home.css`, `resources/views/app.blade.php`, `resources/js/Components/LanguageSwitcher.vue`.

---

## 7. Verification summary

- Focused redesign tests: **28 passed / 392 assertions**.
- Node composable regression tests: **4 passed / 0 failed**.
- `npm run build`: passed, 826 modules transformed.
- Targeted Pint: passed on all changed PHP files.
- Batched suite: 397 tests executed across four batches, 393 passed, with 4 failures attributable to preserved pre-existing work; `AccountEmailChangeTest` has an additional 1 fatal and 6 errors from pre-existing notification code.
- Runtime smoke: seeded Firefox checks of `/ar` and `/en` returned HTTP 200 with populated redesign components and no page or console errors; the reported `filter`/`length` TypeErrors were resolved by changing `useHomepageContent` from `unref()` to `toValue()` with an `Array.isArray()` guard.

Full details and failure evidence are in `TESTING_REPORT.md`.

---

## 8. Remaining gates

| Gate | Status |
|---|---|
| ADR-001–017 | `PROPOSED — pending human approval` |
| OQ-49 | `PARTIALLY RESOLVED — PENDING FORMAL APPROVAL` |
| OQ-24–29 | `DECISION REQUIRED` |
| Phase 6 | `NOT STARTED / NOT AUTHORIZED` |
| Production deployment | Not performed, not authorized |
| Institutional approval of supplied design/content | Not recorded |

No further work should convert placeholders into facts, publish case studies, add contact details, start Phase 6, or deploy without a recorded human decision.
