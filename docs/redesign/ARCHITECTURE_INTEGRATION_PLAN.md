# PCDR PRO — Architecture Integration Plan

**Phase:** 3/5 — Consolidated implementation contract
**Date:** 2026-11-26
**Status:** Active implementation baseline
**Governance:** `NOT CLEARED`. This plan implements only additive technical capability. It does not approve ADR-001–017, close OQ-24–29, mark OQ-49 formally resolved, authorize Phase 6, publish invented facts, or deploy anywhere.

---

## 1. Scope and non-negotiables

### 1.1 Workspace isolation

- Authoritative implementation directory: `/root/PCDR/pcdr-pro`.
- `/root/PCDR/pcdr` is never modified.
- `pcdr-redesign` is never created or used.
- No remote is added; no push is performed; no production deployment is performed.
- The copied Git object store, branches, and pre-existing uncommitted work remain intact. Only reviewed files are staged.

### 1.2 Source fidelity

The supplied homepage is the visual contract recorded in `HOMEPAGE_SOURCE_AUDIT.md`. Preserve:

- Arabic RTL-first organization with an English/LTR route driven by the existing locale system.
- Navy `#1B2B4B`, teal `#237E7A`, sand `#D8B77F`, alert `#C23B30`.
- Cairo/Tajawal typography through the existing font setup; no new remote font dependency.
- Floating pill header, five gradient hero slides with SVG motifs, red ticker, numbered eyebrows `01`–`05`, eleven program axes, tabbed news/publications/projects, five album placeholders, tabbed mission/goals/vision/values, three privacy-gated story placeholders, and deep navy footer.
- Dark mode, reduced motion, logical properties, safe-area insets, and all documented breakpoints.
- The source caveat verbatim: `مستهدفات تخطيطية أولية لعام 2027، تخضع للمراجعة بعد تحديد التمويل المتاح والقدرات التنفيذية.`

### 1.3 Accuracy guardrails

- Planning values remain planning targets, never achievements.
- Do not invent statistics, achievements, donors, budgets, certifications, testimonials, partners, board members, tenders, jobs, or contact details.
- Success stories remain explicitly provisional until consent and privacy review.
- Publications and film remain marked as foundational/upcoming unless a verified asset exists in a reviewed register.
- Social links and unspecified contact fields remain null until an owner supplies them.
- `complaints@pal-arc.org` is already seeded and test-pinned; no new public contact address is introduced.
- The unreferenced media object under `storage/app/public/media` is not published.

### 1.4 Byte-pinned files

The following files are not edited by this work:

- `resources/css/pcdr-home.css`
- `resources/views/app.blade.php`
- `resources/js/Components/LanguageSwitcher.vue`

`Home.vue` keeps every marker and link pattern asserted by `HomepageBackendIntegrationTest.php` and `PublicWebsiteTest.php`, including ordered section markers, `props.sections`, one `href="#main"`, no `const content = {`, and no `navOpen`.

---

## 2. Reuse before addition

### 2.1 Reused existing components

| Concern | Existing implementation |
|---|---|
| Localization | `SetLocale`, `SetWebsiteLocale`, `app/Support/Locale.php`, `HasTranslations::t()` |
| Publication model | `ContentStatus`, `Publishable`, `ContentQuery` |
| Homepage data | `homepage_sections`, translations, `homepage_section_items`, item translations |
| Media | `Media`, media library, `ContentMediaField.vue`, signed private links |
| Site-wide copy | `SiteSettings`, `website_settings`, `config/website.php` |
| Navigation | `NavigationMenu`, `NavigationItem`, `usePublicNavigation` |
| Public shell | `PublicLayout.vue`, `SiteHeader.vue`, `SiteFooter.vue`, `SafeRichText.vue` |
| CMS shell/primitives | `CmsDashboardLayout.vue`, `pc-*` classes, toasts/dialogs, `SelectionControl.vue` |
| Permissions | `homepage.view`, `homepage.update` only; no new permission |
| Testing | PHPUnit/SQLite `:memory:` and Playwright |

### 2.2 Additive schema

One migration adds only:

- `homepage_sections.status` (`string`, default `published`), `published_at` (`timestamp`, nullable).
- `homepage_section_items.media_id` (`foreignId`, nullable, constrained to `media`, `restrictOnDelete`).
- Supporting indexes for public filtering.

No new entity table is justified. Ticker copy, hero slides, album placeholders, about-tab rows, and story placeholders are section items under the existing ten canonical section keys. This keeps the ten-section DOM/test contract stable.

### 2.3 Canonical section keys

The canonical order is frozen from `HomepageContentSeeder.php` and the integration test:

`hero`, `intro`, `areas`, `framework`, `programs`, `targets`, `community`, `partners`, `knowledge`, `cta`.

`Cms/HomepageController::index()` no longer creates the old conflicting `transparency`/`partnerships`/`participation` fallback keys. It creates the canonical ten keys when the table is empty.

---

## 3. Backend contract

### 3.1 Models and service

- `HomepageSection` gains `Publishable`, `ContentStatus` casting, and `published_at` casting. `is_enabled` remains a separate CMS visibility switch and is not conflated with publication status.
- `HomepageSectionItem` gains a `media()` relation and media-aware serialization.
- `HomepageService::sections(string $locale, bool $onlyEnabled = true, bool $includeUnpublished = false)`:
  - keeps the existing two-argument behavior for public Inertia requests;
  - excludes non-published sections by default, including for the historical `$onlyEnabled = false` call;
  - includes drafts only when explicitly requested by the CMS preview path;
  - eager-loads translations, media, and referenced entities;
  - returns a stable, locale-resolved array consumed by both Inertia and the public JSON API;
  - caches by locale/visibility flags and flushes via one explicit method.
- `HomepageService::flush()` forgets every homepage cache variant. It is called after homepage create/update/status/toggle/reorder operations.

### 3.2 CMS write path

- Replace inline `Request` validation with `App\Http\Requests\Cms\UpdateHomepageRequest`.
- Validate section status against `ContentStatus::values()`, media IDs against public image media, and CTA/item URLs as internal paths or `https://` URLs only.
- Stop deleting items merely because they were omitted. Deletion requires an explicit `remove: true` flag and an ID belonging to the section being saved.
- Preserve unknown/non-empty translation values, matching the seeder's customization policy.
- Add CMS routes, reusing existing permissions:
  - `POST /{locale}/cms/homepage/reorder` — `cms:homepage.update`;
  - `PATCH /{locale}/cms/homepage/{section}/status` — `cms:homepage.update`;
  - `POST /{locale}/cms/homepage/{section}/toggle` — `cms:homepage.update`;
  - `GET /{locale}/cms/homepage/preview` — `cms:homepage.view`, JSON only, includes drafts/disabled sections.
- Add a FormRequest for each state-changing endpoint or one dedicated request per operation, following existing CMS conventions.
- `DeleteMedia` refuses to delete media still referenced by homepage items and returns a clear CMS error instead of silently nulling the relation.

### 3.3 Public JSON surface

A single read-only endpoint:

`GET /api/public/homepage?locale=ar|en`

Rules:

- No authentication, no admin-only fields, no drafts, no disabled sections, no unpublished items.
- `locale` is validated and defaults to the configured default locale.
- `throttle:60,1` applies.
- JSON errors already work for `api/*` through `bootstrap/app.php`.
- Uses API Resources under `app/Http/Resources/` and one envelope:

```json
{
  "data": {
    "locale": "ar",
    "dir": "rtl",
    "organization": {
      "name": "...",
      "tagline": "...",
      "logo": "..."
    },
    "sections": [
      {
        "id": 1,
        "key": "hero",
        "type": "hero",
        "status": "published",
        "sort_order": 0,
        "eyebrow": "...",
        "title": "...",
        "body": "...",
        "items": [
          {
            "id": 10,
            "key": "slide-1",
            "type": "inline",
            "sort_order": 0,
            "label": "...",
            "title": "...",
            "body": "...",
            "display": "...",
            "url": "...",
            "value": null,
            "media": null
          }
        ]
      }
    ]
  }
}
```

No other public endpoints are added speculatively.

---

## 4. CMS contract

`resources/js/Pages/Cms/Homepage/Index.vue` gains:

- Tabbed AR/EN panes consistent with the rest of the CMS.
- Section status (`draft`/`published`) and a visible published/unpublished state.
- Accessible move up/down controls bound to the reorder endpoint.
- Enable/disable control bound to the toggle endpoint.
- Verified preview link using the CMS-only preview route.
- `can` props from `HomepageController::index()`; UI actions respect `homepage.view` / `homepage.update`.
- Reuse of `ContentMediaField.vue` for item image selection with localized alt/caption fields.
- Explicit item removal instead of omission-based deletion.
- A warning that planning figures are targets and that story/publication placeholders require approval before publication.

Existing CMS test payloads continue to work unchanged.

---

## 5. Frontend contract

### 5.1 New scoped design layer

- New file: `resources/css/pcdr-pro-home.css`, scoped under `.pcdr-pro-home`.
- New Vue components in `resources/js/Components/RedesignHome/`:
  - `SiteTopStrip.vue` — live Gregorian/Umm al-Qura dates and clock;
  - `HeroCarousel.vue` — five CMS-bound slides, gradient motifs, play/pause, progress bars, reduced-motion and keyboard support;
  - `NewsTicker.vue` — CMS-bound rotating headlines with `aria-live="off"` and reduced-motion pause;
  - `ImpactImprint.vue` — strapline, 11 axes, planning figures, and the mandatory caveat;
  - `ProgramAxes.vue` — 11 numbered thematic axes from published strategic pillars;
  - `KnowledgeTabs.vue` — news/publications/projects tabs from `ContentQuery`-published entities;
  - `AlbumGrid.vue` — CMS/API-bound media or explicitly labeled gradient placeholders;
  - `AboutTabs.vue` — mission/objectives/vision/values from homepage items;
  - `StoryPlaceholders.vue` — privacy-gated story placeholders.
- New composable: `resources/js/composables/useHomepageContent.js` derives typed section/item data from the one server serializer.

### 5.2 Public page integration

`resources/js/Pages/Public/Home.vue` remains the single page component and keeps all pinned markers. The supplied design is introduced by replacing section internals and wrapping the page in `.pcdr-pro-home`; the existing `SiteHeader`/`SiteFooter` remain shared components. No duplicate header, footer, navigation state, or hardcoded content object is introduced.

Mapping:

| Pinned section | Supplied design role | Data source |
|---|---|---|
| `hero` | 5-slide carousel + ticker | `hero` section items |
| `about` | Mission/vision card pair and early About intro | `intro` items, `props.vision`, `props.mission` |
| `areas` | 11-axis imprint | published pillars |
| `approach` | Humanitarian approach | `framework` items |
| `programs` | Numbered program axes | `programs` items / pillars |
| `targets` | 2027 planning figures + caveat | `targets` items |
| `community` | Participatory approach + story placeholders | `community` items |
| `partners` | Partnership categories | `partners` items |
| `knowledge` | News/publications/projects tabs + albums | `ContentQuery` props + `knowledge` media items |
| `cta` | Contact/get-involved call to action | `cta` items and site settings |

### 5.3 Accessibility

Preserve the source's carousel/tab semantics and add:

- One visible skip link (`href="#main"`) only.
- `aria-live` on ticker copy, disabled under reduced motion.
- Alt text from media translations; decorative SVG `aria-hidden="true"`.
- `aria-current`, `aria-selected`, roving `tabIndex`, Arrow/Home/End tab handling.
- No invented link targets: unresolved menu items remain non-clickable or clearly marked upcoming.

---

## 6. Seed/data contract

- `HomepageContentSeeder.php` remains the canonical ten-section bilingual source and remains idempotent. Its mandatory caveat wording is tightened only where it is currently a weaker paraphrase; non-empty administrator values are never overwritten.
- A new additive `RedesignHomepageContentSeeder.php` supplies only verified/source-derived items:
  - five hero slides;
  - ticker headlines from the approved strategic-plan title;
  - extra 2027 planning-target labels matching the supplied design;
  - 11 mission objectives and 10 values from the supplied design;
  - five album placeholders and three privacy-gated story placeholders;
  - no new section keys and no fabricated media.
- Register the additive seeder in `DatabaseSeeder.php` after `HomepageContentSeeder.php`.
- `MEDIA_ASSETS_REGISTER.md` and `SEED_DATA_REGISTER.md` record provenance, status, and what is deliberately not included.

---

## 7. Verification contract

### 7.1 Baseline already measured

- `php artisan test --compact tests/Feature/HomepageBackendIntegrationTest.php`: 10 passed / 175 assertions.
- `php artisan test --compact tests/Feature/PublicWebsiteTest.php`: 27 passed / 2 pre-existing failures:
  1. `test_public_shell_uses_seeded_primary_menu` — the pre-existing uncommitted `SiteHeader.vue` refactor no longer contains the exact pinned string `const { links, cta } = usePublicNavigation()`.
  2. `test_approved_homepage_baseline_files_remain_byte_identical` — the pre-existing uncommitted `LanguageSwitcher.vue` differs from its pinned hash.
- These failures are not caused by redesign work and are not fixed by discarding preserved user work. They are reported in `TESTING_REPORT.md`.

### 7.2 New tests

Add focused feature tests for:

- public JSON locale/direction/published filtering;
- draft/disabled/unpublished exclusion;
- cache invalidation after CMS update;
- status/toggle/reorder permission enforcement;
- media reference deletion guard;
- additive seeder idempotency and planning-target labeling;
- API rate limiting and URL validation;
- frontend source markers and scoped CSS isolation.

### 7.3 Required commands

- `vendor/bin/pint --dirty --format agent` after PHP changes.
- Narrow PHPUnit tests after each backend change.
- `npm run build` after frontend changes.
- Relevant Playwright specs where practical.
- Full PHPUnit suite in batches if one monolithic run times out; results are reported honestly.

---

## 8. File ownership

| Area | Files created or modified by this work |
|---|---|
| Docs | `docs/redesign/ARCHITECTURE_INTEGRATION_PLAN.md`, `MEDIA_ASSETS_REGISTER.md`, `SEED_DATA_REGISTER.md`, `TESTING_REPORT.md`, `SECURITY_REVIEW.md`, `IMPLEMENTATION_REPORT.md` |
| Schema | new `database/migrations/2026_11_27_*_enhance_homepage_publication_and_media.php` |
| Models | `HomepageSection.php`, `HomepageSectionItem.php` |
| Requests | new `app/Http/Requests/Cms/UpdateHomepageRequest.php`, `ReorderHomepageRequest.php`, `UpdateHomepageStatusRequest.php`, `ToggleHomepageSectionRequest.php` |
| Services | `HomepageService.php` |
| CMS controller | `Cms/HomepageController.php` |
| API controller/resources | new `Public/HomepageApiController.php`, new `app/Http/Resources/Public/*` |
| Actions | `DeleteMedia.php` |
| Routes | `routes/web.php` (append only, preserving uncommitted edits) |
| Seeders | `HomepageContentSeeder.php`, new `RedesignHomepageContentSeeder.php`, `DatabaseSeeder.php` |
| CSS | new `resources/css/pcdr-pro-home.css` |
| Vue | `Pages/Public/Home.vue`, `Pages/Cms/Homepage/Index.vue`, new `Components/RedesignHome/*`, new `composables/useHomepageContent.js` |
| Tests | new `tests/Feature/RedesignHomepageApiTest.php`, `tests/Feature/RedesignHomepageCmsTest.php`, `tests/Feature/RedesignHomepageSeederTest.php` |

No file outside this ownership list is modified without a new audit entry.

---

## 9. Delivery order

1. Commit this plan and reviewed audit docs.
2. Schema/models/service/cache + tests.
3. Public JSON API/resources + tests.
4. CMS requests/endpoints/UI/DeleteMedia guard + tests.
5. Scoped design CSS/components and `Home.vue` binding + build.
6. Additive seeders/registers + tests.
7. Security review, full test evidence, implementation report, local commits.

Each stage remains revertable independently and preserves the original repository history.
