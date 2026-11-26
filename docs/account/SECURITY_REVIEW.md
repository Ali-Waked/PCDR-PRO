# Account Management Security Review

> **Status:** Design-level review of the Wave 2 account contract. It documents the intended controls and open items; it is **not** a verification or penetration-test result. See [TESTING_REPORT.md](TESTING_REPORT.md).

---

## 1. Principles

1. Session guard only — no secondary auth stack or token surface (`auth` + `account.active` everywhere; `signed` is additive).
2. Backend is the only trust boundary; UI checks are cosmetic.
3. Ownership is checked server-side by `user_id = Auth::id()`; references are opaque identifiers, not authorization.
4. Minimize sensitive data at write time; redact audit context; mask in the UI.
5. Fail closed on invalid signature, expired token, wrong owner, or unknown reference.

---

## 2. Trust boundaries

| Boundary | Crossing rule |
|---|---|
| Browser → profile routes | `web` middleware, CSRF on mutations, `auth`, `account.active`, throttling. |
| Email link → confirm endpoint | `signed` URL + `auth` + `account.active`; token consumed once. |
| Private avatar → browser | 30-minute signed download with ownership check; never a public storage path. |
| Services → database | Transactions for state + revocation + token rotation; Eloquent binding only. |
| Audit log → profile UI | Safe-field projection, masking, localized labels. |

---

## 3. Threat / control matrix

| Threat | Primary control | Supporting control |
|---|---|---|
| Stale sessions after password/email change | Revoke all other sessions in the same transaction; rotate remember token | Current session preserved for usability; audit events |
| Session hijacking via exposed IDs | Only `public_reference` (32 hex) leaves the server | HttpOnly/SameSite cookies, HTTPS production config, session GC |
| Cross-user session/avatar access | Ownership filter on `user_id` + reference/media ID | Generic 404, no existence oracle |
| Email takeover via link forwarding | Signed link + `auth` + hashed single-use token + 60-minute expiry | Notification to old address; cancelled/expired rows inert |
| Brute force on account endpoints | `account-email`, `account-sensitive`, `account-avatar` limiters | Validation and audit of failures |
| Malicious avatar upload | JPG/PNG/WebP, 2 MB, magic bytes + `getimagesize()`, ULID names, private disk | Restricted Media row, signed delivery, cleanup |
| Token/path leakage in logs | Redaction of before/after/context; no raw ID or path in audit | Safe Inertia props |
| CSRF on state changes | Laravel CSRF middleware + SameSite cookies | Inertia token handling |
| Privacy exposure of IP/UA | Masked IP and derived device label in profile UI | Full values confined to restricted audit store |
| XSS via bilingual user content | Vue escaping; no untrusted `v-html`; RTL-safe markup | CSP per existing platform policy |

---

## 4. Control inventory by flow

| Flow | Controls | Audit |
|---|---|---|
| Personal info | Allowlisted fields, Form Request, protected columns immutable | `account.profile.updated` |
| Email change | Pending state, hashed single-use token, 60 min, signed + auth link, old-address notice, other sessions revoked, remember token rotated | `account.email_change.requested/confirmed/cancelled` |
| Password | `current_password`, `Password::min(12)`, `confirmed`, other sessions revoked, remember token rotated | `account.password.changed` |
| Avatar | Private disk, `is_restricted`, type/size/content validation, ULID filename, signed 30-min download with ownership check, old-file cleanup, initials fallback | `account.avatar.uploaded/removed` |
| Sessions | Opaque `public_reference`, ownership checks, current-session preservation on bulk revoke | `account.session.revoked`, `account.sessions.revoked` |
| Activity | Redacted audit writes, masked IP, derived device, throttled `last_activity_at` (60s) | `auth.*` + `account.*` |

Route names, schema, and middleware are listed in [Account Management Architecture](ACCOUNT_MANAGEMENT_ARCHITECTURE.md) §3–§7.

---

## 5. Rate limiters

| Limiter | Threshold | Scope |
|---|---|---|
| `account-email` | 3 / minute | Email request, cancel, signed confirm. |
| `account-sensitive` | 5 / minute | Profile update, password update, session revocations. |
| `account-avatar` | 10 / minute and 30 / hour | Avatar store/destroy (and download where enabled). |

Keys should combine authenticated identity and IP where applicable. Limits must be applied at the route layer, not only client-side.

---

## 6. Authorization matrix

| Endpoint class | Identity required | Ownership / signature rule |
|---|---|---|
| Show / update profile | `auth` + `account.active` | Only own record; `Auth::id()`. |
| Email request / cancel / confirm | `auth` + `account.active` (+ `signed` on confirm) | Token row belongs to authenticated user; single-use, unexpired. |
| Password update | `auth` + `account.active` | Current password verified for own account. |
| Session list / revoke | `auth` + `account.active` | `sessions.user_id = Auth::id()`; reference opaque. |
| Avatar store / destroy | `auth` + `account.active` | Media belongs to authenticated user. |
| Avatar download | `auth` + `account.active` + `signed` | Signature valid and unexpired (30 min); media owner equals authenticated user. |

Signed endpoints are not public: `signed` verifies the URL; `auth` establishes *who* is requesting it.

---

## 7. Audit, privacy, and retention

| Data class | Handling |
|---|---|
| Audit context | Redacted before storage; no secrets, raw session IDs, or private paths. |
| User-facing activity | Localized labels, masked IP, UA-derived device; safe fields only ([Activity Tracking](ACTIVITY_TRACKING.md) §5). |
| Session rows | Deleted on revocation; otherwise subject to framework GC. |
| Pending email changes | Expire at 60 minutes; consumed/cancelled rows removed; tokens hashed. |
| Avatar files | Private disk; old file removed when unused after detach; no public URL. |
| `users.last_activity_at` | Single overwritten timestamp; no history. |
| Audit retention | Existing audit retention/archive policy; no new indefinite store. |

---

## 8. Residual risks and open items

| Item | Assessment | Follow-up |
|---|---|---|
| MFA is not part of this contract | Elevated accounts rely on password + session controls | Evaluate separately in the platform security roadmap. |
| Signed links can be forwarded while a session is active | `auth` + single-use token + short expiry limit impact | Keep expiry at 60 min / 30 min; monitor audit events. |
| Full IP/UA retained in restricted audit logs | Necessary for security investigations | Confirm retention window with the existing audit policy owner. |
| Session cookie flags depend on deployment config | Framework defaults are HttpOnly/SameSite; Secure requires HTTPS | Confirm production `SESSION_SECURE_COOKIE` and lifetime settings. |
| Rate limiter keys | Contract defines thresholds, not key composition | Confirm user+IP keying during implementation review. |
| Avatar scanning | Content checks are structural, not antivirus | Add scanning only if infrastructure supports it. |
| `last_activity_at` write | Best-effort, throttled | Must never fail user requests; confirm error handling in review. |

---

## 9. Pre-release security checklist (pending verification)

| Check | Status |
|---|---|
| All profile routes reject guests and inactive accounts | Pending verification |
| Signed endpoints reject `auth`-less and tampered/expired URLs | Pending verification |
| Email confirmation revokes other sessions and rotates remember token | Pending verification |
| Password change requires current password and preserves current session | Pending verification |
| Avatar validation rejects wrong type/size/forged magic bytes | Pending verification |
| Session/avatar IDOR checks return generic not-found | Pending verification |
| Rate limiters trigger at documented thresholds | Pending verification |
| Audit entries contain no secrets or raw session IDs | Pending verification |
| Activity History masks IP and derives device label | Pending verification |

Commands and evidence belong in [TESTING_REPORT.md](TESTING_REPORT.md); do not mark these checks complete without recorded output.

---

## 10. Cross-links

- [Account Management Architecture](ACCOUNT_MANAGEMENT_ARCHITECTURE.md)
- [Profile Management](PROFILE_MANAGEMENT.md)
- [Session Management](SESSION_MANAGEMENT.md)
- [Activity Tracking](ACTIVITY_TRACKING.md)
- [Testing Report](TESTING_REPORT.md)
