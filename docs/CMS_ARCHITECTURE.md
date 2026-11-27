# Centralized CMS Architecture

Status: implemented architecture record
Audience: backend, frontend and QA contributors
Related pages: `Content Management / إدارة محتوى الموقع`

## 1. Audit summary (before)

The application already contained a substantial but fragmented website CMS:

- `pages` / `page_translations` existed with SEO fields but had **no CMS controller, no routes and no public page route**. They were effectively dormant.
- `homepage_sections`, `homepage_section_translations`, `homepage_section_items` and their translations were the real homepage content store. Nine prototype section keys (`hero`, `ticker`, `impact`, `programs`, `news`, `albums`, `about`, `stories`, `footer`) coexisted with ten legacy keys seeded by other seeders. Three homepage seeders were run in sequence and wrote overlapping identities.
- Publication rules were split across `Publishable` (status + `published_at`), `HomepageSectionItem::is_published` (boolean only), and `Project::workflow_status` (separate lifecycle/status columns). There were no display start/end dates for homepage records.
- Page and section visibility were partly present (`homepage_sections.is_enabled`) but page-level visibility did not exist at all, and internal public pages did not consult any central visibility configuration.
- Hero slides, success stories, objectives, albums, ticker headlines, news and project selections were all stored as untyped inline items in one generic item table, edited through one very large form. There were no dedicated CRUD interfaces, no per-record validation, no policies and no reliable reorder/delete semantics for these content types.
- The public homepage consumed the same `HomepageService` payload as a separate `/api/public/homepage` JSON surface, but the two payload shapes used different field names. Some content and UI copy was still hardcoded in Vue components, seeders and `config/website.php`.
- Authorization was enforced through `cms:` middleware plus `CmsAccess`, but no policies existed for content models and the permission catalogue advertised capabilities that did not exist (for example `pillars.unpublish`).
- Three competing media pickers existed (public-image only, all media with signed URLs, and settings image upload), but all shared the single `media` table.
- Tests covered a lot of legacy homepage behaviour, but nothing asserted the three-level visibility model, typed content CRUD or date-window behaviour.

## 2. Target model

There are exactly two management responsibilities and one additional display control:

| Concern | Stored in | Effect |
| --- | --- | --- |
| Page visibility | `website_pages.is_enabled` | Hides a predefined public page and its navigation entry. Content is never touched. |
| Section visibility | `homepage_sections.is_enabled` (plus `status` / `published_at`) | Hides one predefined section inside its page. Content is never touched. |
| Homepage display | `homepage_section_items` relationship rows (`item_type`, `item_id`, `tab`, `is_published`) | Chooses whether an existing news article, project, pillar or partner is also presented in a homepage section. No copies are created. |

Content records belong to their content module. The news media centre and the homepage news tab read the **same** `Article` rows; the projects page and the homepage projects tab read the **same** `Project` rows; the ticker reads the **same** `Article` rows.

There is no arbitrary page creation, no arbitrary section creation and no JSON page-builder. Page and section identities are declared in `config/cms_structure.php` and seeded idempotently.

### 2.1 Predefined pages

`home`, `about`, `areas_of_work`, `projects`, `partners`, `media_center`, `contact`, `complaints`.

Each page has a stable key, a public route path, a visibility flag, a display order and localized SEO metadata in `website_page_translations`. The page registry is the single source for public route guarding and navigation visibility.

### 2.2 Predefined homepage sections

`hero`, `ticker`, `stories`, `about`, `vision_mission`, `objectives`, `albums`, `news`, `latest_projects`, `program_areas`, `interventions`, plus the page-bound sections (`about_intro`, `about_identity`, `areas_intro`, `areas_list`, `projects_list`, `partners_list`, `media_news`, `media_albums`, `contact_form`) and the shared `footer`.

Legacy keys (`impact`, `intro`, `areas`, `framework`, `targets`, `community`, `partners`, `knowledge`, `cta`) remain in the database as historical, disabled configurations. They are never deleted by a visibility change. The `footer` key is now an active typed section, not a disabled legacy section.

### 2.3 Typed content sections

Four sections need genuinely typed records rather than generic key/value items:

| Section | Storage | Notes |
| --- | --- | --- |
| Hero slider | `homepage_section_items` in `hero`, with `slide-*` keys | Bilingual title, description, button label, destination URL, alt text and Media Library `media_id`. |
| Success stories | `homepage_section_items` in `stories`, with `story-*` keys | Bilingual title, summary, body, image, optional child images, display period. |
| Objectives | `homepage_section_items` in `objectives` | Bilingual title, description, optional icon image and ordering. |
| Photo album | `homepage_section_items` in `albums` | Each item is one image with bilingual caption and ordering. |
| Site footer | `homepage_section_items` in `footer`, with `footer-` prefixes | Bilingual description, CTA, optional video URL and Media Library image. Contact details and social links stay in Site Settings. |

Typed Form Requests, controllers and Inertia forms validate these records. All of them read and write the same item tables used by the public renderer, so there is exactly one source of truth.

Organizational identity (About description, vision and mission, destination link, logo) lives in one `website_organization` record with translations. The homepage About/Vision sections and the internal About page both read that record. No duplicated organizational descriptions are created.

### 2.4 Selection-only sections

`news`, `latest_projects`, `ticker`, `program_areas` and `interventions` do not own content. They own **selection relationships** to existing `Article`, `Project`, `StrategicPillar` and `Program` records through `homepage_section_items`. Editing a selection only adds, removes, orders or hides the relationship row. It never edits, publishes or deletes the underlying content record.

### 2.5 Publication and date windows

- Content publication remains with each module's own workflow (`status` + `published_at`, or `Project::workflow_status`).
- Homepage eligibility additionally requires `is_published = true` on the relationship row and, when present, `now()` between `display_start_at` and `display_end_at`.
- Null start/end dates never hide content. Expired content stays fully editable in the CMS.
- All timestamps are stored in UTC through Laravel casts and compared with `now()` on the backend. The frontend never makes publication decisions.

## 3. Request flow

```
Administrator
  -> /cms/content-management (pages, sections, visibility, links)
  -> /cms/content/{section} (typed CRUD + selection CRUD)
  -> FormRequest (authorize + validate)
  -> Controller -> Service/Model (transaction) -> AuditService
  -> HomepageService::flush()

Visitor
  -> middleware SetLocale/SetWebsiteLocale
  -> Public controller checks WebsitePage::isVisible()
  -> service queries published + eligible records (eager loaded)
  -> Inertia props (backend already filtered)
  -> Vue renders predefined components only
```

## 4. Authorization

- Every CMS route is behind `account.active` and a `cms:<permission>` middleware.
- New structure/content capabilities use the existing `CmsAccess` resolution chain and are seeded into the website administrator role only. Ordinary users receive no new permissions automatically.
- Form Requests re-check authorization; controllers call `Gate`/`CmsAccess` before writes. Deny by default.
- Sensitive actions (visibility changes, publication changes, deletions) are written to `audit_logs` through `AuditService`.
- Media selection validates that the referenced `media` row is a public image. Uploads stay in the existing media library.

## 5. Migration strategy

1. Add `page_key` to `homepage_sections` and backfill existing rows with `home`.
2. Create `website_pages` / `website_page_translations` and seed the predefined registry idempotently.
3. Create `website_organizations` / translations and seed from the existing About section content and `config/website.php` values.
4. Add `display_start_at`, `display_end_at` and `parent_item_id` to homepage section items; add `alt_text` and `summary` to item translations.
5. Re-key/move existing inline items into the new predefined sections by `item_key` prefix using updates, not deletes. Legacy rows are retained but unpublished/disabled.
6. Update the public services and Vue components to read the new sections. Old public routes are preserved.
7. Consolidate seeders so one seeder owns the canonical CMS structure; legacy seeders remain safe to run but no longer overwrite canonical content.

No destructive `drop` or `delete` of historical content is used. Migration down methods only reverse schema additions where safe.

## 6. Decisions and non-goals

- **Reuse over re-creation:** news, projects, partners, programs and media are existing modules; the CMS consumes them.
- **No second media library:** only `Media` IDs are stored.
- **No generic builder:** the section registry is fixed in application code; the CMS cannot create pages or sections.
- **No frontend publication logic:** the backend returns only eligible content; Vue components only render.
- **No redesign:** public pages keep the existing PCDR visual system and brand colours.
- **Separation of concerns:** visibility changes never write to content tables; content changes never change page/section visibility.

## 7. Verification

Automated coverage lives in `tests/Feature/CentralizedCmsTest.php`, `tests/Feature/PublicFooterIntegrationTest.php`, `tests/Feature/MediaPublicDeliveryTest.php` and the existing homepage/public/authorization suites. The footer-specific data flow and production media checklist are recorded in `docs/FOOTER_CMS_INTEGRATION.md`. The verification checklist is:

- hidden page returns 404 and disappears from navigation;
- hidden section disappears publicly while its rows remain;
- re-enabling restores the previous rows;
- hero CRUD, reorder, media validation and disabled-slide exclusion;
- ticker and news tab use the same `Article` records;
- projects tab and projects page use the same `Project` records;
- date windows hide, expire and restore without deletion;
- only published partners are exposed;
- Arabic and English payloads and RTL/LTR direction;
- private inquiries remain inaccessible;
- homepage queries are eager loaded;
- footer links follow navigation changes and hidden pages/sections/items do not leak;
- public media uploads return HTTP 200 as images while private files stay guarded;
- `php artisan test` and `npm run build` succeed.
