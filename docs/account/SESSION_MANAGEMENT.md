# Session Management

> **Status:** Contract-level documentation. Described behavior is the agreed target; verification is pending in [TESTING_REPORT.md](TESTING_REPORT.md).

---

## 1. Scope

Account sessions use the Laravel 13.43 **session guard only** (`web` guard) with the `database` session driver. There is no Sanctum, no API token guard, and no second authentication surface. Users can review active sessions and revoke them from the Connected Devices section of the profile page.

---

## 2. Storage and identity

| Field | Source | Exposure |
|---|---|---|
| `sessions.id` | Laravel session ID (primary key) | **Server-only.** Never in props, URLs, audit context, notifications, or logs. |
| `sessions.user_id` | Authenticated user FK | Used for ownership queries; not serialized. |
| `sessions.public_reference` | New: 32 hex chars, `unique`, `nullable` | The only session identifier sent to the frontend; used in route parameters. |
| `sessions.ip_address` | Request IP | Stored as-is by Laravel; masked before display in Activity History. |
| `sessions.user_agent` | Request UA | Stored as-is; converted to a device label for display. |
| `sessions.payload` | Laravel session payload | Server-only. |
| `sessions.last_activity` | Framework activity timestamp | Safe to display as activity time. |
| `sessions.created_at` | New: timestamp | Displayed as session start time. |

The **current session** is resolved server-side (for example, by comparing the request's session identity with the stored row or a server-only marker in the payload). Only the opaque `public_reference`, device label, masked IP, timestamps, and an `is_current` flag are passed to Inertia.

Example safe prop shape (illustrative):

```json
{
  "id": "9f3c1a7b2d4e6f8091a2b3c4d5e6f708",
  "device": "Chrome on macOS",
  "ip_masked": "192.0.2.***",
  "started_at": "2026-11-26T09:14:00Z",
  "last_activity_at": "2026-11-26T10:02:00Z",
  "is_current": true
}
```

---

## 3. Endpoints

| Action | Method / URI | Route name | Guardrails |
|---|---|---|---|
| List | `GET /{locale}/dashboard/profile/sessions` | `account.sessions.index` | `auth` + `account.active`; query scoped to `Auth::id()`. |
| Revoke one | `DELETE /{locale}/dashboard/profile/sessions/{session:public_reference}` | `account.sessions.destroy` | Ownership check by `user_id` + `public_reference`; `account-sensitive` throttle. |
| Revoke others | `DELETE /{locale}/dashboard/profile/sessions` | `account.sessions.destroy-others` | Deletes all sessions of the user except the current one; `account-sensitive` throttle. |

Unknown or foreign `public_reference` values produce a generic not-found response without revealing whether the session exists. The UI offers "sign out this device" for other sessions; the current session is flagged and is explicitly preserved by `destroy-others`.

---

## 4. Revocation semantics

| Trigger | Sessions affected | Remember token | Audit event |
|---|---|---|---|
| Explicit single revocation | One owned session selected by `public_reference` | Unchanged | `account.session.revoked` |
| `destroy-others` | All user sessions except current | Unchanged | `account.sessions.revoked` |
| Password change | All other sessions; current preserved | Rotated | `account.password.changed` |
| Email confirmation | All other sessions; current preserved | Rotated | `account.email_change.confirmed` |
| Logout | Current session | Framework default | `auth.logout` |

Revocation deletes the session row, so the former `public_reference` becomes immediately invalid. Revocation and remember-token rotation on password/email changes happen in the same database transaction as the primary update.

---

## 5. Device and activity display

| Display field | Derivation |
|---|---|
| Device | Derived from `user_agent` (browser + platform label); unknown agents fall back to a generic label. |
| IP address | Masked representation of `sessions.ip_address`; full IP is never sent to the profile page. |
| Started / last seen | `sessions.created_at` and `sessions.last_activity`; last-seen is also reflected by `users.last_activity_at`. |
| Current marker | Server-side comparison; never trusts a client-provided flag. |

See [Activity Tracking](ACTIVITY_TRACKING.md) §5 for masking and device-derivation rules.

---

## 6. Security rules

1. **No raw IDs.** `sessions.id` stays server-side; only `public_reference` crosses the boundary.
2. **Ownership first.** Every session lookup filters by `user_id = Auth::id()` before using the reference.
3. **No guest access.** `auth` and `account.active` apply to all session endpoints; revoked/inactive accounts cannot enumerate devices.
4. **CSRF and throttling.** State-changing DELETEs use Laravel CSRF protection and `account-sensitive` rate limiting.
5. **Cookie hardening.** Session cookies rely on framework defaults (HttpOnly, SameSite, encrypted payload as configured); secure cookies are expected in production HTTPS deployments.
6. **Audit hygiene.** Revocation events contain the public reference and safe context only — never the raw session ID, payload, or token.

The complete control inventory is in [Security Review](SECURITY_REVIEW.md) §3–§6.

---

## 7. Retention

- Active sessions follow the configured framework session lifetime and garbage collection (`config/session.php`, `php artisan session:gc`/scheduler cleanup as configured).
- Revoked sessions are deleted immediately; no historical session table is introduced.
- `public_reference` exists only for the lifetime of its session row.
- `users.last_activity_at` is a single overwritten timestamp, not an activity log; historical events remain in `audit_logs` under the existing retention policy ([Activity Tracking](ACTIVITY_TRACKING.md) §7).

---

## 8. Cross-links

- [Account Management Architecture](ACCOUNT_MANAGEMENT_ARCHITECTURE.md) — schema, routes, middleware, rate limiters
- [Profile Management](PROFILE_MANAGEMENT.md) — password and email-change revocation triggers
- [Activity Tracking](ACTIVITY_TRACKING.md) — device labels, masking, activity data
- [Security Review](SECURITY_REVIEW.md)
- [Testing Report](TESTING_REPORT.md)
