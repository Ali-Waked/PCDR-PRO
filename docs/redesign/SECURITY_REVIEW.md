# PCDR PRO — Security Review

**Date:** 2026-11-26
**Scope:** Homepage publication workflow, public JSON surface and scoped redesign frontend.
**Result:** No new high-severity finding identified in redesign-owned code. Governance remains `NOT CLEARED`; this review is not an approval instrument.

---

## 1. Public read surface

`GET /api/public/homepage?locale=ar|en` (`api.public.homepage`):

- **Unauthenticated by design**, read-only, and rate-limited with `throttle:60,1`.
- `locale` is validated against `config('website.locales')`; invalid values fail with the application's existing JSON error handling for `api/*` paths.
- The response field is `dir` (`rtl`/`ltr`), not `direction`, and is derived from the validated locale.
- Only sections that are **enabled** and **published** are serialized (`HomepageService::sections($locale)`). Items are additionally filtered on `is_published`.
- Drafts, disabled sections and disabled/unpublished items are unreachable through this endpoint. CMS preview is a separate authenticated route.
- No admin-only fields, user data, audit records or secrets are included. Organization name/tagline/logo come from existing site settings and config.
- `Cache-Control: public, max-age=60` is set; homepage cache variants are explicitly flushed after every relevant CMS mutation.
- Restricted media (`is_restricted`) is never serialized. Media on the private disk is represented only by the signed `public.media.download` route.

Verified by `RedesignHomepageApiTest.php`, including rate-limit and media-field assertions.

---

## 2. CMS write surface

- All state-changing routes reuse existing permissions only: `homepage.view` and `homepage.update`. No authority, approval or governance permission is introduced or granted.
- `authorize()` checks `auth()->check()` plus `CmsAccess::allows()` on index/preview; routes additionally use the existing `cms:*` middleware.
- Four dedicated FormRequests validate:
  - section status against `ContentStatus::values()`;
  - item media IDs against **public image** media only;
  - CTA and item links as internal paths (`/` but not `//`) or `https://` URLs;
  - reorder payload shape and status/toggle flags.
- Legacy omission-based deletion is removed. An item is deleted only with explicit `remove: true` and an ID that belongs to the section being saved.
- Updates run in a DB transaction; successful mutations are audited (`homepage.updated`, `homepage.reordered`, `homepage.status_updated`, `homepage.visibility_updated`).
- `DeleteMedia` now checks `homepage_section_items.media_id` and throws `MediaInUseException` instead of silently nulling references.
- CMS preview returns drafts only to users with `homepage.view`; it is never exposed through the public endpoint.
- The CMS UI respects `can.view` / `can.update` props and does not render unauthorized controls as actionable.

Verified by `RedesignHomepageCmsTest.php` and `RedesignHomepageApiTest.php`.

---

## 3. Frontend and injection review

- No `v-html`, `innerHTML` or equivalent raw-HTML sink was added in `RedesignHome`, `useHomepageContent.js`, or the rewritten CMS homepage editor.
- Vue interpolation escapes text. Existing `SafeRichText.vue` remains the only rich-text rendering path for legacy fields.
- All new CSS is scoped under `.pcdr-pro-home`; a feature test rejects unscoped selectors. The byte-pinned `pcdr-home.css`, `app.blade.php` and `LanguageSwitcher.vue` were not edited.
- New components each have a single template root, preserving the Inertia/Vue convention.
- Ticker copy uses `aria-live` while visible and respects reduced-motion settings; carousel controls expose play/pause, keyboard navigation and progress state.
- No unverified link target is introduced. Ticker seed items deliberately carry no URL after review; unresolved items render as text rather than clickable inventions.
- The visible skip link remains the only `href="#..."` anchor in `Home.vue`.

Verified by `RedesignHomepageFrontendTest.php`, `npm run build`, and `HomepageBackendIntegrationTest.php`.

---

## 4. Content and privacy guardrails

- Planning figures are labelled as initial 2027 targets with the verbatim Arabic caveat; they are never presented as achieved results.
- No statistic, donor, budget, certification, testimonial, partner, board member, tender, job or contact detail is invented.
- Five album items and three story items are explicit placeholders with no media and no personal identifiers; stories remain gated on consent/privacy decisions OQ-24–OQ-29.
- Public contact fields and social links remain null except the pre-existing test-pinned `complaints@pal-arc.org`.
- The unreferenced media object under `storage/app/public/media` is not attached or published.

---

## 5. Residual risks and non-approvals

1. **Governance:** OQ-49 is `PARTIALLY RESOLVED — PENDING FORMAL APPROVAL`; ADR-001–017 are `PROPOSED`; OQ-24–29 require decisions. No approval right is exercised here.
2. **Placeholder publication:** If an administrator seeds or publishes placeholder copy without review, users could see provisional text as institutional fact. The UI warning and registers mitigate but do not replace an approval process.
3. **Pre-existing test failures:** `SiteHeader.vue`, `LanguageSwitcher.vue`, account notification code and translation keys have known failures preserved from the original workspace. They are documented in `TESTING_REPORT.md` and were not altered.
4. **Deployment:** No production deployment, remote, push, Phase 6 authorization or governance state change was performed.
5. **Visual approval:** No human has approved the supplied design's institutional use; this review covers technical safety only.

---

## 6. Review conclusion

Redesign-owned code preserves the existing authorization model, narrows the public data surface, validates all new write inputs, protects media references, and introduces no raw-content sink or fabricated public detail. The remaining risk is governance/content approval, which stays explicitly with the human decision owners.
