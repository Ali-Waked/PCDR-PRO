# Public Footer CMS Integration

Status: implemented architecture record
Audience: backend, frontend, CMS editors and QA
Applies to: `resources/js/Components/RedesignHome/ProPrototypeFooter.vue` on public pages in Arabic and English

## 1. Purpose

The public footer is rendered from the same centralized CMS and navigation services as the navbar and homepage. It introduces no second CMS, no navigation editor and no extra database table. Arabic and English differ only through the locale-aware payloads; the component contains no institutional copy of its own.

## 2. Sources of truth

| Footer content | Canonical source | Exposed through |
| --- | --- | --- |
| Footer section and its items | `homepage_sections` / `homepage_section_items`, key `footer` | `HomepageService::sections($locale)` |
| About submenu, complaints and media destinations | `navigation_menus` / `navigation_items`, menu `primary` | `NavigationService::all($locale)` |
| Organization name, logo, contact details, social links, copyright and legal note | Site Settings (`site.social` for social links) | `SiteSettings::publicIdentity($locale)` |
| Complaint route and visibility | `config/cms_structure.php` + `website_pages` | `public.complaints`, guarded by `PublicController::assertPageVisible()` |
| Introductory video | Website Settings `footer.video.*` | `SiteSettings::footerVideo()` as `site.footerVideo` |
| Images / thumbnails | Existing Media Library on the `public` disk | `Media::url()` |

`HandleInertiaRequests` shares a lazy `footer` prop containing only the filtered `footer` section. Inner pages may pass `:anchor-base` for legacy anchor compatibility, but they do not pass their own sections to force a footer render.

## 3. Three independent visibility levels

1. **Page visibility** — `website_pages.is_enabled`. A hidden page returns `404` and its navigation entry disappears. `HomepageService` also removes sections bound to that page, so hidden page content is not delivered in JSON.
2. **Section visibility** — `homepage_sections.is_enabled`, `status` and `published_at`. A hidden footer section produces `footer: []`; the rows remain in the database for editors.
3. **Item visibility** — `homepage_section_items.is_published` and the optional display window. Hidden items are omitted from the shared payload.

The footer component never decides visibility. It receives only backend-filtered data and renders nothing when a source is absent.

The complaints page is an explicit part of this model: both `GET /{locale}/complaints` and `POST /{locale}/complaints` call `assertPageVisible('complaints')`. FormRequest validation runs before the controller, so tests submit a valid anonymous complaint when proving the page guard.

## 4. Navigation synchronization

`NavigationService::serialize()` includes the stable `key` of each menu item. The footer reads `navigation.primary`, finds `about`, and renders every filtered child as a footer link. Admin changes to labels, order, enabled state or visibility therefore appear in both the navbar and the footer after the navigation cache is flushed.

`NavigationItem::resolvedUrl()` maps menu keys to predefined registry routes (`programs` → `areas_of_work`, `media` → `media_center`) and supports only an explicit allowlist of in-page fragments. Unknown keys fall back to the localized home page; they never become arbitrary URLs. External links accept HTTPS only.

Seeded primary/footer roots: `home`, `about`, `programs`, `media`, `partners`, `contact`, `complaints`. The `about` group currently contains `about`, `vision`, `objectives`, `achievements` and `board`.

## 5. CMS editing

`config/cms_structure.php` declares `footer` as a typed section (`content => 'footer'`) with item prefixes `footer-`, `social-` and `value-`. `resources/js/Pages/Cms/ContentManagement/Section.vue` exposes title, label, body, URL, CTA label, caption and media fields for those items.

Social icons are **not** editable as footer items. They come exclusively from `site.social`; `SocialPlatformIcon.vue` maps normalized platform keys to icons. Adding or removing a social account is a Site Settings change.

The introductory film is also **not** a footer item. It is edited in the dedicated Website Settings card (`footer.video.provider`, `footer.video.url`, localized `footer.video.title`, `footer.video.poster_media_id` and `footer.video.visible`) and delivered as `site.footerVideo`. See `docs/FOOTER_VIDEO_CMS.md` for the payload contract, provider allowlists and embedding limitations.

There is no CMS navigation module. The navigation editor was intentionally removed, and `tests/Feature/CmsNavigationModuleRemovalTest.php` forbids reintroducing it.

## 6. Video and media safety

The public player no longer parses URLs or opens a modal. It renders only the server-resolved `site.footerVideo` payload: a 16:9 click-to-load frame, an optional public Media Library poster, and an always-available external watch link. The iframe uses server-provided `src`, `sandbox` and `allow`, plus fixed `referrerpolicy` and lazy loading. There is no dialog, no `v-html`, no autoplay with sound and no frontend-built provider URL.

`App\Support\VideoEmbed` is the authoritative allowlist for the central setting: HTTPS YouTube and Facebook URLs only, with IDs parsed strictly and canonicalized to fixed embed endpoints. It rejects arbitrary markup, `javascript:` / `data:` schemes, unsupported hosts, credentials, non-default ports and malformed IDs. The legacy `videoSource()` helper still supports YouTube, Facebook, Vimeo and same-origin video files for older consumers, but is not imported by the public footer.

Provider-side restrictions are outside this application's control: YouTube and Facebook may refuse third-party embedding for private, restricted, region-locked or copyright-protected videos, and a valid URL may still fail in an iframe. The external watch link remains available because that failure cannot be reliably detected cross-origin. Full details are in `docs/FOOTER_VIDEO_CMS.md`.

Public image delivery uses the existing `public` disk and `public/storage` symlink. Restricted records always resolve to the signed download controller, even if an older installation left them on the public disk. Poster validation and cached public resolution accept only public images; `DeleteMedia` blocks deleting an image still referenced by a poster setting. See `tests/Feature/MediaPublicDeliveryTest.php` for the upload → storage → URL → HTTP → gallery lifecycle.

### Production checklist

- Set the final public `APP_URL` before running `php artisan config:cache`.
- Run `php artisan storage:link` on every deploy. The container entrypoint verifies that `public/storage` resolves to `storage/app/public`.
- `public/storage` must point to the current project's `storage/app/public`; a stale symlink produces missing assets.
- The `local` disk is no longer served at `/storage`; missing public files return `404`, while private files remain reachable only through permission-checked downloads.

## 7. Verification

```bash
php artisan test --compact \
  tests/Feature/PublicFooterIntegrationTest.php \
  tests/Feature/FooterVideoCmsTest.php \
  tests/Feature/MediaPublicDeliveryTest.php \
  tests/Feature/CmsNavigationModuleRemovalTest.php \
  tests/Feature/RedesignHomepageFrontendTest.php \
  tests/Feature/HomepageBackendIntegrationTest.php \
  tests/Feature/PublicSiteSettingsIntegrationTest.php \
  tests/Feature/LocalizedUrlFallbackTest.php
node --test tests/videoEmbed.test.mjs
npm run build
```

The integration suite checks shared payloads in both locales, live About-submenu synchronization, hidden page/section/item behavior, the complaints POST guard, central-source component markers and the dedicated footer editor fields. `tests/e2e/footer-video-cms.spec.mjs` adds a browser workflow for configuration, visibility, provider switching and responsive RTL/LTR layout; it skips without seeded CMS credentials.
