# PCDR PRO — Backend Adaptation

**Date:** 2026-11-26  
**Workspace:** `/root/PCDR/pcdr-pro`  
**Status:** Additive backend adaptation implemented; verification is recorded in `docs/reconstruction/TEST_REPORT.md`.  
**Governance:** `NOT CLEARED`. This document is technical only. It does not approve ADR-001–017, close OQ-24–29 or OQ-49, authorize Phase 6, or deploy. OQ-49 remains `PARTIALLY RESOLVED — PENDING FORMAL APPROVAL`; OQ-24–29 remain `DECISION REQUIRED`; Phase 6 remains `NOT STARTED / NOT AUTHORIZED`.

---

## 1. Adaptation principle

The existing Laravel application was not replaced. The approved homepage and dashboard work reuse the existing authentication, RBAC, CMS permissions, content models, media library, settings, audit logging and localized routing. Changes are additive wherever existing data or uncommitted work could be affected.

- `/root/PCDR/pcdr` was treated as read-only.
- No remote was added; nothing was pushed or deployed.
- No auth, governance, officeholder, delegation or ADR approval data was introduced.
- Existing homepage rows are disabled rather than deleted.
- Unrelated uncommitted work in the worktree was preserved.

---

## 2. Homepage content pipeline

### Reading path

```text
GET /{locale}            → Public\HomeController → HomepageService::sections($locale)
GET /api/public/homepage → Public\HomepageApiController → same service
GET /{locale}/homepage/preview → Cms\HomepageController::preview → sections($locale, false, true)
```

`HomepageService` is the single serialization boundary. It loads `homepage_sections` with section translations, items, item translations and media, then returns locale-aware arrays.

Public reads use `onlyEnabled=true` and `includeUnpublished=false`; CMS preview uses `onlyEnabled=false` and `includeUnpublished=true`. Items are limited to published items and sorted by `sort_order`, then `id`. Restricted media and unresolved model items are omitted.

Cache keys vary by locale, enabled/all, and published/all-status. All homepage mutations call `HomepageService::flush()`, which clears every variant for every configured locale.

### Writing path

The CMS homepage editor submits section metadata, bilingual translations and item metadata. `Cms\HomepageController` writes through `updateOrCreate`, while validation lives in dedicated FormRequests:

- `StoreHomepageSectionRequest`
- `UpdateHomepageRequest`
- `DeleteHomepageSectionRequest`

New routes:

| Method | URI | Name | Permission |
|---|---|---|---|
| POST | `/{locale}/cms/homepage/sections` | `cms.homepage.sections.store` | `homepage.update` |
| DELETE | `/{locale}/cms/homepage/sections/{homepageSection}` | `cms.homepage.sections.destroy` | `homepage.update` |

Existing reorder, status, toggle, update and preview routes were retained.

Section create defaults to `published` and appends after the current maximum `display_order` unless explicitly supplied. Destroy removes the section and its translations/items, records an audit event and flushes the homepage cache.

---

## 3. Additive schema and seeder

`2026_11_26_090000_enhance_homepage_prototype_content.php` adds only nullable columns and one inert boolean default:

- `homepage_section_translations`: `ticker_label`, `tab_labels`, `video_url`, `video_caption`
- `homepage_section_items`: `tab`, `is_featured`, `motif_key`
- `homepage_section_item_translations`: `date_label`, `cta_label`, `caption`, `unit`

No existing column is changed or removed. `down()` drops only these columns.

`HomepagePrototypeContentSeeder` seeds nine canonical sections in prototype order and disables legacy-only keys. It is idempotent: existing translations are not overwritten, and the cache is flushed after seeding. Item display strings use the existing `display_value` column.

---

## 4. Serialization contract

The service adds these section fields: `tickerLabel`, `tabLabels`, `videoUrl`, `videoCaption`.

It adds these item fields: `tab`, `featured`, `motifKey`, `dateLabel`, `ctaLabel`, `caption`, `unit`.

The public API resource preserves the existing snake-case legacy names (`sort_order`, `cta_label`, `cta_link`) while exposing the new prototype names in camelCase. The Inertia page receives the service arrays directly. See `FRONTEND_BACKEND_CONTRACTS.md`.

Safe links are sanitized at serialization as well as validated on write. Accepted values are `#anchor`, a single-slash internal path that does not start with `//`, and `https://`. Everything else becomes `null`, including `http://`, `javascript:`, `data:`, protocol-relative URLs, whitespace/control characters, backslashes and encoded backslashes.

---

## 5. Eloquent alias correction

An earlier implementation added camelCase accessors for snake-case columns. In the installed Laravel/Eloquent version these accessors recursed through the mutator lookup and exhausted memory during seeding.

The correction was made in the controller, not by changing stored column names:

- alias accessors/mutators were removed from the translation models;
- `Cms\HomepageController::normalizeSectionTranslationValues()` maps `tickerLabel → ticker_label`, `tabLabels → tab_labels`, `videoUrl → video_url`, `videoCaption → video_caption`;
- `normalizeItemTranslationValues()` maps `display → display_value`, `dateLabel → date_label`, `ctaLabel → cta_label`;
- the seeder writes `display_value` directly.

Public JSON remains camelCase for the added fields. This keeps the database convention and avoids model-level recursion.

---

## 6. Theme persistence adaptation

Theme support is additive and uses the existing account/profile architecture.

| Concern | Implementation |
|---|---|
| Storage | `users.theme`, nullable string, `light|dark|system` |
| Validation | `UpdateProfileRequest`: `sometimes|nullable|in:light,dark,system` |
| Endpoint | `PUT {locale}/dashboard/profile` |
| Payload | shared top-level Inertia prop `theme`; `auth.user.theme`; `profile.theme` |
| System value | stored as `null`, resolved as `system` |
| Missing theme | leaves `name` and `locale` unchanged |
| Runtime | `resources/js/app.js` and `useTheme.js` apply the whitelisted preference |

`name` and `locale` are now `sometimes|required` so a theme-only profile update is valid. `AccountProfileService` writes only supplied fields. Existing audit logging and redirect behavior were preserved.

Two theme migrations are retained. The older `2026_11_26_050331_add_theme_to_users_table.php` is preserved user work; the newer guarded `2026_11_26_100000_add_theme_to_users_table.php` ensures the nullable/default-system definition. They are reconciled by guarded ordering: the first creates the column if absent, the second normalizes it, and reverse rollback drops it once. Neither was deleted by this work.

`resources/views/app.blade.php` remains byte-identical to the pinned source; theme bootstrap lives in non-pinned JS/CSS.

---

## 7. Route and module preservation

The route inventory found 490 routes. No active public route was deleted. All 322 internal/system routes under `operations` and `phase5` remain hidden behind the existing dashboard-feature gate and permissions. Authentication, password reset, email verification, CMS login, admin, account and CMS content routes were retained.

The duplicate `/partners` and `/partnerships` aliases remain pending an explicit canonical-path decision. Legacy Roles/Permissions bookmark redirects and unrouted page files were retained. Removing either alias would require coordinated navigation, sitemap, SEO and test changes.

---

## 8. Data and governance safeguards

- Planning figures remain planning targets, not achievements: `3,000`, `1,500`, `50`, `100`, `500`, `200`, `10`, `11`.
- The planning caveat is preserved verbatim: `مستهدفات تخطيطية أولية لعام 2027، تخضع للمراجعة بعد تحديد التمويل المتاح والقدرات التنفيذية.`
- Prototype `href="#"` destinations remain inert.
- No media, social, film, donor, partner, testimonial, certification, budget or contact URL was invented.
- No governance status was promoted and no Phase 6 action was taken.

---

## 9. Verification

Targeted verification passed before this document was written:

| Check | Result |
|---|---|
| `node --test tests/homepage-content.test.mjs` | 6/6 |
| `PrototypeHomepageTest` | 7/7 |
| `RedesignHomepageApiTest` + `RedesignHomepageCmsTest` | 12/12 |
| Homepage/legacy/public/theme/account set | 78/78 |
| Auth/admin/authorization set | 48/48 |
| `npm run build` | passed |
| PHP syntax and targeted Pint | passed |

Full-suite and Firefox browser results are recorded in `docs/reconstruction/TEST_REPORT.md`; they are not claimed here.

---

## 10. Known limitations

- The publication workflow bypass remains: direct `published ↔ draft` transitions are still accepted because existing tests depend on that behavior. No governance-approved transition was added.
- Hardcoded Vue fallback copy is source-derived prototype scaffolding, not an approval of OQ-05/OQ-06.
- A pre-existing role-delegation risk remains: a non-owner with `users.assign_roles` may grant system admin roles. It was not worsened.
- Page-level dashboard normalization remains incremental; the shared shell, tokens and theme apply across retained modules.
