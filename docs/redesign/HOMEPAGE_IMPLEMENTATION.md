# PCDR PRO — Approved Homepage Implementation

**Date:** 2026-11-26  
**Workspace:** `/root/PCDR/pcdr-pro`  
**Status:** Implemented; verification recorded in `HOMEPAGE_TEST_REPORT.md`.  
**Governance:** `NOT CLEARED`. This document records technical implementation only. It does not approve ADR-001–017, close OQ-24–29, mark OQ-49 resolved, authorize Phase 6, or deploy.

---

## 1. Authoritative source

The approved ZIP was used as the sole design source. No substitute or generic homepage was created.

| Artefact | SHA-256 |
|---|---|
| `parc-gaza-website.zip` | `1852e4153ea274bf14d46f16cb504e525bd65700050477811ec60a0956633c18` |
| `docs/redesign/source-homepage/index.html` | `32ec0224e632708f6941369ff60a50c9c16197d4de344591a5401a16ad6e84f4` |
| `docs/redesign/source-homepage/logo.jpg` | `1aedb3ecec106d3de83f1bd5e99338487fbe880b89831559c116786af5bdfa4e` |

The ZIP contains only `index.html` and `logo.jpg`. Its sole external dependency is Google Fonts (`Cairo` and `Tajawal`). The embedded 360×360 logo was extracted to `public/assets/pcdr-homepage/logo/pcdr-prototype-logo.jpg` (SHA-256 `f70699bdd9b1c259b0dc230affe81dff1d93701e82b746ebf481ca9a12fea5db`); the standalone 800×800 `logo.jpg` was deliberately not substituted.

---

## 2. Public page composition

`resources/js/Pages/Public/Home.vue` was rewritten as the real public homepage. It renders a single `.pcdr-prototype` root and composes the approved prototype in this order:

1. `ProPrototypeSprite`
2. skip link (`href="#top"`)
3. `ProTopStrip`
4. `ProPillHeader`
5. `ProHeroCarousel`
6. `ProTicker`
7. `ProImpact`
8. `ProProgramAxes`
9. `ProNewsTabs`
10. `ProMediaGallery`
11. `ProAboutTabs`
12. `ProStories`
13. `ProPrototypeFooter`

The legacy public shell (`SiteHeader` / `SiteFooter` markers, `routeFor(...)` links, `.pcdr-home` section markers and `pcdr-pro-home.css`) is no longer used on the homepage. Non-home public pages retain the shared header/footer.

### Section keys

Canonical CMS section keys and display order:

`hero` → `ticker` → `impact` → `programs` → `news` → `albums` → `about` → `stories` → `footer`

Legacy-only keys (`intro`, `areas`, `framework`, `targets`, `community`, `partners`, `knowledge`, `cta`) are not deleted. The prototype seeder disables them so existing data is preserved without appearing in the new public composition.

---

## 3. Styling and identity

- `resources/css/pcdr-prototype.css` is a scoped port of the prototype style block. Every selector is nested under `.pcdr-prototype`, so it cannot leak into the CMS, dashboard, auth or other public pages.
- Typography: `Cairo` 600/700/800 for display, `Tajawal` 400/500/700 for body.
- Prototype palette: `#F1F4F8`, `#FFFFFF`, `#14213B`, `#4B5873`, `#D3DAE6`, `#1B2B4B`, `#237E7A`, `#D8B77F`, `#C23B30`, with explicit dark-mode mappings.
- Institutional identity remains the canonical five-colour set: Primary `#3E5172`, Secondary `#71BCB7`, Accent `#DAC3A3`, Supporting `#D7C5A1`, White `#FFFFFF`.
- Slogan: `من الاستجابة الإنسانية إلى التعافي والتنمية المستدامة`.
- RTL and LTR are supported through logical CSS properties and the existing locale middleware. `Home.vue` binds `:lang` and `:dir`.
- Reduced motion disables carousel rotation and ticker animation where `prefers-reduced-motion: reduce` is set.

---

## 4. CMS and backend integration

- One additive migration extends homepage translation/item tables with the fields required by the prototype. No existing column is changed or removed.
- `HomepagePrototypeContentSeeder` seeds the nine sections idempotently, disables legacy-only sections, and flushes caches.
- The CMS homepage editor now supports create/delete, publish/toggle/reorder, bilingual Arabic/English fields, item metadata, safe-link hints and a structured preview.
- Public home and public API receive only published, enabled sections through `HomepageService::sections($locale)`. CMS preview intentionally includes disabled/draft sections.
- Safe links are validated in `UpdateHomepageRequest` and sanitized again during serialization in `HomepageService`. Unsafe values become `null` in both Inertia and API payloads.

See `HOMEPAGE_BACKEND_MAPPING.md` for the exact field and section contract.

---

## 5. Content safeguards

- `3,000`, `1,500`, `50`, `100`, `500`, `200`, `10` and `11` are planning targets or structural counts, not achieved results.
- The planning caveat is preserved verbatim: `مستهدفات تخطيطية أولية لعام 2027، تخضع للمراجعة بعد تحديد التمويل المتاح والقدرات التنفيذية.`
- Prototype `href="#"` destinations remain inert. No donation, donor, partner, testimonial, certification, budget, social, video or contact destination was invented.
- No verified media URLs were supplied; album/story placeholders remain non-linking motifs rather than fabricated photographs or claims.
- The exact slogan was preserved; no new institutional achievements or endorsements were introduced.

---

## 6. Pinned files

The following files remain byte-identical to the required sources:

| File | SHA-256 |
|---|---|
| `resources/css/pcdr-home.css` | `d555d276d7abbce07004fc4f73c54e4a104a5e0f9bffa26ff47df71e299dc9c9` |
| `resources/views/app.blade.php` | `2a7affa19d09acda482e3d3dbce2ef97d374c259eaaa677848145c24ba871765` |
| `resources/js/Components/LanguageSwitcher.vue` | `29f9798faee179ba36da521bd69d0e28badc73e33dcc6f51420f9215f4471537` |

The theme bootstrap required by the platform reconstruction lives in non-pinned files (`resources/js/app.js` and additive CSS), not in `app.blade.php`.

---

## 7. Deliberate non-changes

- No remote added; nothing pushed, deployed or committed by this work.
- No approval permission, officeholder, delegation, ADR acceptance or Phase 6 transition introduced.
- No contact details, social links, film URL, publication downloads or partner logos invented.
- Unrelated uncommitted work in the worktree was preserved.
