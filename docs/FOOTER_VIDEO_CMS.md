# Footer Introductory Video CMS

Status: implemented architecture and editor guide
Audience: CMS editors, backend and frontend maintainers, QA
Applies to: `resources/js/Pages/Cms/Settings/Index.vue` and `resources/js/Components/RedesignHome/ProPrototypeFooter.vue`

## 1. Single source of truth

The introductory video is configured in **Website Settings**. There is no separate video module, no second database table and no frontend-only setting. Legacy homepage `footer-video` items are left untouched and are not read by the public player.

| Canonical key | Type | Default | Purpose |
| --- | --- | --- | --- |
| `footer.video.provider` | `video_provider` | `youtube` | `youtube` or `facebook`; controls which allowlist is applied. |
| `footer.video.url` | `video_url` | `null` | Provider URL. Stored as submitted; delivered publicly only after canonicalization. |
| `footer.video.title` | localized `text` | Arabic `الفيلم التعريفي بالجمعية`, English `Association Introduction Film` | Title above the player and accessible name for the play button/iframe. |
| `footer.video.poster_media_id` | `media` | `null` | Optional ID of a **public image** in the existing Media Library. |
| `footer.video.visible` | `boolean` | `1` | Shows or hides the footer section without deleting configuration. |

The keys are declared once in `App\Support\SiteSettingsCatalog`. `SiteSettingsSeeder` initializes only missing values and never overwrites an editor's saved value.

## 2. CMS editing and preview

An editor with `settings.update` permission sees a dedicated **Introductory video** card at the top of Website Settings. The card provides:

- provider selection (YouTube or Facebook);
- a full video URL field;
- localized Arabic and English titles;
- a Media Library poster picker restricted to public images;
- a separate visibility control;
- a backend preview backed by `POST /{locale}/cms/settings/video-preview`.

The preview endpoint reuses the same server-side resolver as the public site. It accepts only `{ provider, url }`, validates them with `PreviewFooterVideoRequest`, and returns the canonical `url`, `embedUrl`, `sandbox` and `allow` values. It never accepts raw iframe HTML. A successful preview proves the URL was accepted by the allowlist; it cannot prove that the provider will actually stream the video in a third-party frame.

Saving any setting flushes the cached public site, homepage and navigation payloads, so public changes appear immediately on the next request.

## 3. Server-side validation and canonicalization

`App\Support\VideoEmbed` is the only authoritative resolver. It rejects missing providers, unsupported providers, empty or malformed URLs, any whitespace/control characters or `<`, `>`, `"`, `'`, non-HTTPS schemes, non-default ports, embedded credentials, unsupported hosts, arbitrary iframe markup, `javascript:` and `data:` schemes.

### YouTube

Accepted, HTTPS only:

- hosts: `youtube.com`, `www.youtube.com`, `m.youtube.com`, `youtu.be`, `www.youtu.be`, `youtube-nocookie.com`, `www.youtube-nocookie.com`;
- `/watch?v={id}`, `/shorts/{id}`, `/embed/{id}`, `/live/{id}`;
- `youtu.be/{id}` and `www.youtu.be/{id}`.

The ID must be exactly 11 characters matching `[A-Za-z0-9_-]`. The public canonical URL is always `https://www.youtube.com/watch?v={id}` and the embed URL is always `https://www.youtube-nocookie.com/embed/{id}`.

### Facebook

Accepted, HTTPS only:

- hosts: `facebook.com`, `www.facebook.com`, `m.facebook.com`, `fb.com`, `www.fb.com`;
- `/watch?v={id}`, `/video.php?v={id}`, `/photo.php?v={id}`;
- `/reel/{id}`, `/videos/{id}`, `/{profile}/videos/{id}`.

The ID must be 8–20 digits. The public canonical URL is always `https://www.facebook.com/watch/?v={id}` and the embed URL uses the official plugin endpoint:

`https://www.facebook.com/plugins/video.php?href={encoded canonical URL}&show_text=false&width=560`

Vimeo, TikTok, local files and all other providers are rejected by the central setting. The legacy `resources/js/Support/videoEmbed.js` helper still supports Vimeo and same-origin video files for older consumers, but the public footer does not import it and must not construct its own iframe URL.

## 4. Public payload contract

`SiteSettings::footerVideo()` adds `site.footerVideo` to the shared Inertia `site` prop. The exact shape is:

```json
{
  "visible": true,
  "available": true,
  "provider": "youtube",
  "title": "Association Introduction Film",
  "url": "https://www.youtube.com/watch?v=abcdefghijk",
  "watchUrl": "https://www.youtube.com/watch?v=abcdefghijk",
  "embedUrl": "https://www.youtube-nocookie.com/embed/abcdefghijk",
  "posterUrl": null,
  "sandbox": "allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-presentation",
  "allow": "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
}
```

Field semantics:

- `visible` comes only from `footer.video.visible`.
- `available` means the stored provider/URL pair resolved to a safe canonical embed. It does **not** mean the provider currently permits playback.
- `provider`, `url`, `watchUrl`, `embedUrl`, `sandbox` and `allow` are produced by `VideoEmbed`, never copied from raw administrator input. `url` and `watchUrl` are the canonical provider-facing URL, not the raw submitted URL.
- `title` is the localized setting value, falling back to `config('website.footer_video')` only when the setting is empty.
- `posterUrl` is resolved inside the cached public payload from `footer.video.poster_media_id` only when the Media Library record is an image on the `public` disk and is not restricted. An invalid, deleted, restricted or non-image record resolves to `null` without breaking the page.
- `sandbox` and `allow` are fixed per provider. Facebook uses `allow: encrypted-media; picture-in-picture`.

Hiding the section sets only `visible: false`; the rest of the configuration remains available and editable.

## 5. Public player behavior

`ProPrototypeFooter.vue` renders the section only when both `visible` and `embedUrl` are truthy. It consumes `site.footerVideo` directly and contains no URL parsing or provider allowlist.

- The player is a 16:9 frame with a click-to-load poster button. No iframe is created before the editor-configured user action, and nothing autoplays with sound.
- The poster is a real `<img loading="lazy">` with empty alternative text; the play button carries an accessible label including the localized title.
- The iframe uses the server-provided `src`, `sandbox` and `allow`, plus `referrerpolicy="strict-origin-when-cross-origin"` and `loading="lazy"`.
- An always-available `target="_blank" rel="noopener noreferrer"` link labelled `مشاهدة الفيديو` / `Watch video` points to the canonical provider URL.
- If the iframe emits an error event, a localized fallback message directs visitors to the provider website.
- Layout uses `min-width: 0` and fluid frame sizing; no dialogs, modals or fixed widths are used. Arabic and English inherit the document's RTL/LTR direction. Hover/scaling transitions are disabled under `prefers-reduced-motion: reduce`.

## 6. Exact provider limitations

1. **A valid URL is not a guarantee of an embeddable video.** YouTube and Facebook can refuse third-party playback because of uploader privacy, audience, region, age, copyright, login or embedded-player restrictions. The browser cannot reliably detect those provider-side decisions cross-origin, so the public footer keeps the external watch link visible even when the iframe loads an error page.
2. **Preview is allowlist validation plus an iframe render.** It cannot verify private/unlisted access, playback rights, region availability or Facebook audience permissions. Editors must open the canonical watch URL while logged out to confirm public access.
3. **Facebook embedding depends on the video owner's privacy and embedding settings.** A valid numeric Facebook video ID may still be blocked in `plugins/video.php`. This app cannot override that restriction or turn a private video public.
4. **The app does not scrape provider metadata.** Poster images must be selected from this application's public Media Library; thumbnails are not fetched from YouTube or Facebook.
5. **Only the two allowlisted providers are supported in the central setting.** Changing the provider does not migrate the old URL; editors must enter a URL valid for the selected provider.
6. **Restricted local media cannot be used as a poster.** The picker, save validation and cached public resolution all enforce public images. `DeleteMedia` also blocks deleting an image still referenced by `footer.video.poster_media_id`.

## 7. Verification

```bash
php artisan test --compact \
  tests/Feature/FooterVideoCmsTest.php \
  tests/Feature/PublicFooterIntegrationTest.php \
  tests/Feature/PublicSiteSettingsIntegrationTest.php \
  tests/Feature/CmsSiteSettingsRedesignTest.php \
  tests/Feature/WebsiteCmsScopeTest.php \
  tests/Feature/HomepageBackendIntegrationTest.php \
  tests/Feature/RedesignHomepageCmsTest.php \
  tests/Feature/MediaPublicDeliveryTest.php
node --test tests/videoEmbed.test.mjs
php artisan route:list --name=cms.settings
npm run build
```

`tests/e2e/footer-video-cms.spec.mjs` covers admin configure → save → guest footer → URL change → hide → re-enable → Facebook switch, plus RTL/LTR and 360/390/430/768/1280/1440px layout checks. It requires a running app and `E2E_EMAIL` / `E2E_PASSWORD` for an already-seeded CMS-capable user; it skips rather than inventing credentials.
