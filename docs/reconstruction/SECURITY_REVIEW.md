# PCDR PRO — Security Review

**Date:** 2026-11-26  
**Scope:** Approved homepage integration, homepage CMS editor, theme preference persistence, dashboard design-system/theme rebuild, route and legacy audit.  
**Governance:** `NOT CLEARED`. This review does not approve ADR-001–017, close OQ-24–29 or OQ-49, authorize Phase 6, or deploy.

---

## 1. Method and boundary

The review inspected the actual Laravel/Vue implementation in `/root/PCDR/pcdr-pro`, including routes, middleware, controllers, FormRequests, services, models, resources, CSS/JS and tests. `/root/PCDR/pcdr` was treated as read-only. No remote was added; nothing was pushed or deployed.

Existing authentication, RBAC, governance, audit logging, data and unrelated uncommitted work were preserved. No new public URL, media URL, social URL, donor/partner identity, testimonial, certification, budget or contact detail was invented.

---

## 2. Authentication and authorization

| Layer | Finding |
|---|---|
| Web middleware | Existing locale, website locale, hidden-module gate, shared Inertia data, activity tracking and security headers remain registered |
| Authentication | Existing login, password reset, email verification and CMS login routes unchanged |
| Account activation | `account.active` remains on dashboard/admin/CMS/account routes |
| Permissions | `RequirePermission` and `EnsureCmsPermission` remain the authorization boundary |
| Policies | Existing audit, settings and content policies remain in place |
| Hidden modules | `operations` and `phase5` remain hidden via `EnsureDashboardFeatureAvailable` and `config/dashboard_features.php` |
| CSRF | Existing CSRF protection preserved; only `webhooks/inbound/*` remains exempt for HMAC machine requests |

New homepage routes require `homepage.update` for create/delete/reorder/status/toggle/update and `homepage.view` for index/preview. The public homepage API is read-only and throttled (`60,1`). It exposes only published, enabled sections.

No authentication or authorization route was removed or weakened by this work.

---

## 3. Input validation

### Homepage writes

`StoreHomepageSectionRequest`, `UpdateHomepageRequest` and `DeleteHomepageSectionRequest` validate section identity, status, publication date, ordering, translations and item structure. Media selection is restricted to public images from the existing media library.

Safe-link validation accepts only:

- `#anchor` matching `/^#[A-Za-z0-9_:-]+$/`;
- a single-slash internal path not starting with `//`;
- an `https://` URL.

Rejected values include backslashes, encoded backslashes, whitespace, control characters, newlines, leading/trailing whitespace, protocol-relative URLs, `http://`, `javascript:`, `data:`, `mailto:` and any other scheme.

### Serialization boundary

`HomepageService::safeLink()` mirrors the rule set on read. Unsafe `url`, `ctaLink` and `videoUrl` values become `null` in both the Inertia payload and the API. The Vue composable mirrors the rules locally but is explicitly defence in depth, not the security boundary.

Source `href="#"` placeholders remain inert and are not promoted to links.

### Content authenticity

No raw HTML field was introduced. No unverified external URL is stored. Planning figures remain planning targets and carry the verbatim caveat: `مستهدفات تخطيطية أولية لعام 2027، تخضع للمراجعة بعد تحديد التمويل المتاح والقدرات التنفيذية.`

---

## 4. Data preservation

- Homepage schema additions are nullable/inert; no existing column is changed or removed.
- Legacy homepage sections are disabled, not deleted.
- `users.theme` is additive and nullable.
- No auth, RBAC, governance, officeholder, delegation or approval table was altered.
- Unrelated uncommitted work was preserved.
- Two compatible theme migrations exist; neither was deleted.

---

## 5. Theme security

| Control | Finding |
|---|---|
| Allowlist | `light`, `dark`, `system` only |
| Storage | `system` stored as `null` |
| Endpoint | `PUT /{locale}/dashboard/profile`, behind existing `auth`/`account.active` |
| Partial update | `theme` is `sometimes`; `name` and `locale` are `sometimes|required` |
| Server authority | Authenticated theme comes from the user record; local storage is only a guest/local fallback |
| Pinned shell | `resources/views/app.blade.php` remains byte-identical; no inline theme script was added to it |
| Runtime | `app.js` whitelists props before applying; `system` leaves `data-theme` unset so CSS media queries remain authoritative |
| Persistence | localStorage failures are caught and ignored |

No theme value is used as a file path, route, class interpolation or database key.

---

## 6. Media and public asset handling

Media remains behind the existing library and `HomepageService::serializeMedia()`. Restricted media are omitted from homepage payloads. Public private-disk media continue to use the existing signed download route. The extracted logo is a local static asset with a verified hash; the stale standalone logo was not substituted.

No external asset URL was fabricated.

---

## 7. Residual risks

| Risk | Severity | Status | Mitigation / note |
|---|---|---|---|
| Publication workflow bypass: direct `published ↔ draft` updates are accepted | Medium | Open, pre-existing | Existing tests depend on the behavior; no governance-approved transition was added |
| Non-owner with `users.assign_roles` may grant system admin roles | High | Open, pre-existing | Unchanged and not worsened; requires separate governance/RBAC decision |
| Duplicate theme migrations | Low | Accepted | Guarded chronological migrations are compatible; both retained to preserve existing work |
| Hardcoded prototype fallback copy | Low | Open | Source-derived scaffolding, not approval of OQ-05/OQ-06 |
| Unverified media/social/video/donor/partner/contact URLs | Low | Controlled | Remain `null` or inert; no invented destinations |
| Page-local dashboard literals remain | Low | Accepted | Shared shell/tokens/theme apply globally; deeper normalization is residual work |

---

## 8. Test evidence

Passing before this review:

- `PrototypeHomepageTest` — 7/7;
- `RedesignHomepageApiTest` + `RedesignHomepageCmsTest` — 12/12;
- homepage/legacy/public/theme/account set — 78/78;
- auth/admin/authorization set — 48/48;
- `node --test tests/homepage-content.test.mjs` — 6/6;
- `npm run build` — passed;
- targeted Pint and PHP syntax checks — passed.

Full-suite and Firefox browser results are recorded in `docs/reconstruction/TEST_REPORT.md` and are not claimed here.

---

## 9. Conclusion

The implemented work preserves the existing security architecture and adds narrow, validated surfaces. No new privilege transition, governance action or Phase 6 behavior was introduced. The residual risks above are recorded honestly; the two authorization-related risks are pre-existing and require explicit decisions outside this implementation.
