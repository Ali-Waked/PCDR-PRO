# PCDR PRO — Media Assets Register

**Date:** 2026-11-26
**Scope:** Homepage redesign media provenance and publication decisions.
**Governance:** `NOT CLEARED`. This register documents technical handling only. It does not approve institutional identity, privacy-sensitive imagery, or publication of any unverified asset.

---

## 1. Supplied source package (preserved, not published)

Stored under `docs/redesign/source-homepage/` with verified SHA-256 hashes:

| File | SHA-256 | Use |
|---|---|---|
| `parc-gaza-website.zip` | `1852e4153ea274bf14d46f16cb504e525bd65700050477811ec60a0956633c18` | Original archive, retained as the visual contract. |
| `index.html` | `32ec0224e632708f6941369ff60a50c9c16197d4de344591a5401a16ad6e84f4` | Extracted supplied homepage source; audited in `HOMEPAGE_SOURCE_AUDIT.md`. |
| `logo.jpg` | `1aedb3ecec106d3de83f1bd5e99338487fbe880b89831559c116786af5bdfa4e` | Supplied raster reference, retained as evidence only. |

The archive is not served by the application. No photograph or logo from the source package is copied into public media storage by this work.

---

## 2. Authentic assets already present in the platform

| Asset | Status | Handling |
|---|---|---|
| `docs/logo.svg` | Pre-existing authentic vector source | Not modified. Not newly registered in the media library. |
| `public/assets/pcdr-homepage/logo/pcdr-logo.png` | Published static asset | Existing `SiteSettingsSeeder` value `organization.logo`; reused for header, intro and API organization logo. |
| `public/assets/pcdr-homepage/logo/pcdr-logo-lg.png` | Published static asset | Reused as the large authentic logo variant. |
| `public/assets/pcdr-homepage/logo/pcdr-logo-animated.svg` | Published static asset | Reused for the existing hero motif in the legacy fallback block. |

No new logo, wordmark, partner logo or donor mark is created.

---

## 3. Media created by this work

**None.** The redesign adds no upload, no generated photograph, no stock image and no media-library record. All six new `Pro*.vue` components are driven by CMS/API data or by explicit CSS gradient placeholders.

### Seeded media references

`RedesignHomepageContentSeeder.php` creates 53 source-derived items. Every item is seeded with `media_id = null`:

- 5 hero slides — text only, gradient motif from source palette.
- 4 ticker headlines — source-derived plan titles; no URL is seeded because no verified destination exists.
- 4 additional 2027 planning-target labels — text and numeric targets only.
- 11 mission objectives and 10 institutional values — text from supplied strategic copy.
- 11 programme axes — text from verified strategic pillars.
- 5 album placeholders — explicitly labelled "no verified photograph available yet".
- 3 success-story placeholders — explicitly privacy-gated; no person, image or identifier.

The five album placeholders use the supplied navy/teal/sand gradient palette rather than invented imagery.

---

## 4. Deliberately excluded

| Excluded item | Reason |
|---|---|
| `storage/app/public/media/2026/11/01M84KJ9JXF4A8HJ9649QD0GFD.jpg` | Unreferenced media object; provenance not verified by a register. It is not attached to any homepage item and is not published. |
| Source `logo.jpg` as a public asset | The platform already has authentic SVG/PNG logo assets; publishing an extra raster copy would create an unmanaged duplicate. |
| Donor, partner, certification or award logos | No verified asset or owner approval is recorded. |
| Photographs of beneficiaries | Consent and privacy gates OQ-24–OQ-29 are `DECISION REQUIRED`. |
| Album images from external stock sources | Would invent institutional evidence. |

---

## 5. Handling rules implemented

- `HomepageService::serializeMedia()` returns `null` for `is_restricted` media, so restricted attachments never appear in the Inertia payload or public JSON API.
- Public media on a private disk are exposed only through the signed `public.media.download` route, never by guessing a storage path.
- `UpdateHomepageRequest` accepts `media_id` only when the media record is an **image** and is **public**.
- `DeleteMedia` refuses to delete any media still referenced by `homepage_section_items.media_id`; the CMS receives `MediaInUseException` instead of a silent nulling of the relation.
- Media alt text and captions come from existing `Media` translations; the redesign does not invent descriptive alt text.

---

## 6. Outstanding human decisions

None of the following is approved by this register:

1. Whether any real field photograph may be published, and under what consent record.
2. Whether the supplied `logo.jpg` is an approved institutional variant requiring its own managed asset.
3. Whether partner or accreditation logos exist and may be displayed.
4. Whether the three story placeholders may ever become real stories.

Until those decisions are recorded, placeholders stay labels, not evidence.
