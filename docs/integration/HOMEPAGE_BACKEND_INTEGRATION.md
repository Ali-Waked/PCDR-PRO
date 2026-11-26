# Homepage Backend Integration

Date: 2026-11-26
Worktree: `/root/PCDR/pcdr-pro-homepage-integration`
Baseline commit: `c1dadf6` (detached HEAD)
Source homepage: `/root/PCDR/pcdr` (untouched)

## Purpose

This integration makes CMS/admin content authoritative on the public homepage while preserving the imported prototype homepage’s visual identity. No parallel settings system, CMS module, table, frontend framework, or API-driven rendering path was introduced.

## Boundaries preserved

- `/root/PCDR/pcdr` was not modified.
- The original shared `pcdr-pro` worktree was not reset, committed, pushed, or otherwise altered.
- No assistant-side commits, remotes, pushes, servers, builds, tests, or browser runs were performed in this worktree.
- Authentication, authorization, validation, CSRF protection, link sanitisation, and media restrictions remain enabled.
- The prototype CSS namespace, sprite symbols, anchors, timers, responsive behaviour, RTL/LTR handling, and animation preferences remain intact.

## Canonical data contract

The prototype nine-section contract is treated as canonical:

`hero`, `ticker`, `impact`, `programs`, `news`, `albums`, `about`, `stories`, `footer`

The public page renders only sections present in the Inertia `sections` prop. `HomepageService::sections()` supplies enabled, published sections ordered by `display_order`, with published items ordered by `sort_order`. Disabled or draft sections disappear from the public page; CMS preview still includes them.

Item key prefixes remain part of the visual mapping, not a content source: `slide-`, `stat-`, `target-`, `axis-`, `news-`, `pubs-`, `album-`, `about-`, `story-`, `footer-`, and `social-`.

## Architecture decisions

1. **Inertia remains the homepage data path.** `/api/public/homepage` stays read-only and is not consumed by `Public/Home.vue`.
2. **CMS content is the only content source.** Prototype components no longer carry `FALLBACK` arrays or positional copy that can override CMS values. Static chrome labels and locale strings are retained only where the CMS contract has no field.
3. **Rendering follows CMS order and visibility.** `Home.vue` maps section keys to prototype components and iterates `content.orderedSections()`. The footer is rendered outside `<main>` only when the `footer` section is visible.
4. **API keys stay public-facing.** The read-only API maps `order` to `sort_order`, `ctaLabel` to `cta_label`, and `ctaLink` to `cta_link`, and omits internal `enabled`/`publishedAt` fields. There is no requirement for the Vue page to consume it.
5. **Caches are flushed on authoritative mutations.** CMS homepage mutations already flushed `HomepageService`. Media translation updates now flush it as well, because serialized media alt/caption text is part of the cached homepage payload.

## Implemented changes

### Frontend

- `resources/js/composables/useHomepageContent.js`
  - Accepts values, refs, and getters through `toValue`.
  - Exposes `isVisibleSection()`, `visibleSection()`, `visibleSections`, and `orderedSections()`.
  - Accepts `order` or API-style `sort_order` for section ordering.
  - Accepts `sortOrder` or `sort_order` for item ordering.
  - Keeps `safeUrl()` limited to internal single-slash paths, strict anchors, and `https://` URLs.
  - Keeps links to removed public inner pages inert, matching the `chore/remove-public-pages` branch contract.

- `resources/js/Pages/Public/Home.vue`
  - Replaced fixed section tags with a `SECTION_COMPONENTS` map and a dynamic `<component>` loop.
  - Hides absent/disabled/draft sections and renders the footer outside `<main>`.
  - Keeps prototype chrome, anchors, head metadata, CSS import, and RTL/LTR behaviour.

- `resources/js/Components/RedesignHome/*.vue`
  - Removed hardcoded prototype content arrays and positional fallbacks.
  - Read section and item values only through `useHomepageContent`.
  - Preserved visual motifs, sprite symbols, gradients, timers, and static accessibility/chrome labels.

### CMS admin

- `resources/js/Pages/Cms/Homepage/Index.vue`
  - Fixed delete URL mismatch.
  - Sends `tab_labels` as an array instead of a raw JSON string.
  - Added the missing `dateLabel` length constraint.
  - Sends `publishedAt` with the section payload.
  - Avoids sending empty video/media fields unconditionally.

- `app/Http/Controllers/Cms/HomepageController.php`
  - Exposes `publishedAt` in the CMS index.
  - Existing homepage create/update/toggle/reorder/delete paths flush the homepage cache.

### Backend hardening

- `app/Http/Controllers/Cms/MediaController.php`
  - Injects `HomepageService`.
  - Flushes the homepage cache after a media translation update so public alt/caption text does not remain stale for the cache TTL.

### Tests

Updated source-contract coverage in:

- `tests/Feature/RedesignHomepageFrontendTest.php`
  - Dynamic CMS-ordered rendering, footer position, no fixed section tags, no hardcoded fallback constants, asset wiring, and anchor-regex parity.

- `tests/Feature/HomepageBackendIntegrationTest.php`
  - Replaced fixed component-order assertions with dynamic rendering assertions and footer-outside-`<main>` checks.

- `tests/Feature/PublicWebsiteTest.php`
  - Updated footer presence assertions for the conditional, CMS-driven prototype footer.

- `tests/homepage-content.test.mjs`
  - Section ordering/visibility, `sort_order` aliases, `isVisibleSection()`, `visibleSection()`, and safe anchor patterns.

Added focused coverage in:

- `tests/Feature/RedesignHomepageCmsTest.php`
  - CMS edit → Inertia/API parity, invalid `tab_labels`, `publishedAt` in CMS index, reorder → public order, and media cache invalidation.

- `tests/Feature/RedesignHomepageApiTest.php`
  - Public API key names and omission of internal CMS fields.

## Ten-phase status

| Phase | Outcome |
| --- | --- |
| 1. Audit routes, versions, backend flow, CMS admin, frontend bindings, API, and security boundary | Complete |
| 2. Isolate work in a dedicated worktree and preserve concurrent work elsewhere | Complete |
| 3. Define the canonical nine-section/authoritative CMS contract | Complete |
| 4. Rewire `Home.vue` for dynamic, CMS-ordered section rendering | Complete |
| 5. Remove component fallback content while preserving prototype visuals | Complete |
| 6. Fix CMS editor save/payload defects | Complete |
| 7. Align public API naming and document its read-only role | Complete |
| 8. Harden cache invalidation for media mutations | Complete |
| 9. Update/add source, composable, and feature tests | Complete |
| 10. Document implementation and hand verification to the user | Complete; user-run verification pending |

## Verification status

### Verified by source review

- No modified prototype component contains `FALLBACK`, `DEFAULT_ITEMS`, or `HARDCODED` content arrays.
- Remaining locale/chrome constants are not CMS content fields.
- `Home.vue` renders only mapped, visible sections from the Inertia prop.
- The footer component is outside `<main>` and conditional on the `footer` section.
- CMS homepage mutations and media translation updates call `HomepageService::flush()`.
- The public API remains read-only and is not imported by the Vue homepage.

### Not run in this worktree

The worktree has no prepared `vendor`, `node_modules`, `.env`, or `public/build`. Under the current verification boundary, the assistant did not run PHPUnit, Node tests, Pint, Vite, Artisan servers, Playwright, or screenshots.

User-run commands:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
node --test tests/homepage-content.test.mjs
npm run build
```

Targeted narrowing is also supported, for example:

```bash
php artisan test --compact tests/Feature/RedesignHomepageFrontendTest.php
php artisan test --compact tests/Feature/RedesignHomepageCmsTest.php
php artisan test --compact tests/Feature/RedesignHomepageApiTest.php
php artisan test --compact tests/Feature/HomepageBackendIntegrationTest.php
php artisan test --compact tests/Feature/PublicWebsiteTest.php
```

Manual browser checks on the user’s `8070` server remain the final acceptance gate: edit copy, status, visibility, and order in CMS; confirm the public homepage reflects them after reload; confirm disabled sections disappear; confirm RTL/LTR, anchors, animations, and responsive layout are unchanged. No browser evidence exists from this worktree and none is claimed.

## Known caveats

- `HomepageService` caches serialized sections for 300 seconds. Flushes cover CMS homepage mutations and media translation updates; direct database writes outside CMS still require an explicit flush.
- The API deliberately uses snake_case public keys and omits internal flags; it is not a drop-in replacement for the Inertia prop shape.
- Existing API locale resolution and organization logo serialization were retained; no parallel logo/default-locale pathway was added.
- The public page trusts the CMS section keys from the seeder/contract. Unknown section keys are skipped rather than rendered with guessed content.
- Embedded media URLs are sanitised by `HomepageService`; restricted media is excluded from serialized homepage payloads.
