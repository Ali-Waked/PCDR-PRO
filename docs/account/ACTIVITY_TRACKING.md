# Activity Tracking

> **Status:** Contract-level documentation. Described behavior is the agreed target; verification is pending in [TESTING_REPORT.md](TESTING_REPORT.md).

---

## 1. Scope and data sources

Activity tracking combines three sources; it does not create a new activity log store.

| Source | Used for | Nature |
|---|---|---|
| `audit_logs` (via `AuditService`) | Activity History entries and security events | Existing append-only audit table with its own retention policy. |
| `users.last_activity_at` | "Last active" in the dashboard widget | New, single overwritten timestamp. |
| `users.last_login_at` | "Last login" in the dashboard widget | Existing field, unchanged. |
| `sessions` | Active session count, start time, last activity | Existing table with `public_reference` and `created_at` additions. |

---

## 2. Audit event catalog

`AuditService::record()` writes entries with actor, action, subject, redacted before/after, and request context. Event names use the `auth.`, `account.`, `profile.`, `user.` prefixes.

| Action | Trigger | Category |
|---|---|---|
| `auth.login` | Successful login | Existing |
| `auth.logout` | Explicit logout | Existing |
| `auth.login_failed` | Failed login attempt | Existing |
| `auth.email_verified` | Email verification completed | Existing |
| `account.profile.updated` | Personal information changed | New |
| `account.email_change.requested` | Pending email change created | New |
| `account.email_change.confirmed` | Signed confirmation consumed | New |
| `account.email_change.cancelled` | Pending change cancelled | New |
| `account.password.changed` | Password updated | New |
| `account.avatar.uploaded` | Profile photo stored | New |
| `account.avatar.removed` | Profile photo detached/deleted | New |
| `account.session.revoked` | One session revoked | New |
| `account.sessions.revoked` | Other sessions revoked in bulk | New |

Reserved prefixes `profile.` and `user.` follow the same convention for future profile/user-scoped events; no unlisted action is emitted by this contract.

---

## 3. Recording rules

```php
// Contract-level example (illustrative)
$audit->record(
    action: 'account.session.revoked',
    subjectType: 'session',
    subjectId: $session->public_reference,
    before: ['device' => $deviceLabel, 'ip' => $maskedIp],
    after: null,
    extra: ['route' => 'account.sessions.destroy'],
);
```

Rules:

1. **Redaction:** all `before`, `after`, and `extra` values pass through the existing `AuditRedactor`. No passwords, password hashes, email-change tokens, remember tokens, raw session IDs, or private storage paths are recorded.
2. **Safe field selection:** only values needed to explain the event are stored; no full request bodies or full session payloads.
3. **Actor and request context:** `actor_id` is `Auth::id()`; context includes route, locale, request ID, and user agent, subject to redaction.
4. **Ordering:** audit entries are written only after a successful state change, so failed or rolled-back operations do not pollute history.

---

## 4. Throttled last-activity middleware

| Property | Contract |
|---|---|
| Value written | `users.last_activity_at` |
| Cadence | At most once per **60 seconds** per user (throttled write). |
| Audience | Authenticated users with active accounts. |
| Side effects | No audit row per request; only the timestamp updates. |
| Failure mode | A failed write must not break the request. |

This keeps the dashboard widget's "last activity" meaningful without generating a high-volume log per page view.

---

## 5. User-facing projection and masking

The Activity History component reads safe fields from `audit_logs` only. It must never expose raw audit context wholesale.

| Exposed | Transform | Excluded |
|---|---|---|
| Localized action label | Map action keys to `lang/{en,ar}` strings. | Internal action codes where a label exists. |
| Device | Derive from stored user agent (browser + platform); fallback for unknown agents. | Full raw user agent string. |
| IP address | Mask (e.g. `192.0.2.***`). | Full IPv4/IPv6 address. |
| Timestamp | Format in request locale/timezone. | Internal request IDs, payloads. |
| Target | Human-readable subject where safe. | Raw session IDs, tokens, hashes, file paths, secrets. |

Full IPs and full user agents may remain in the restricted `audit_logs` store under the existing audit policy; masking applies to the user-facing profile surface. See [Security Review](SECURITY_REVIEW.md) §7.

---

## 6. Dashboard Account Activity widget

The widget on the existing dashboard uses real values — no placeholders:

| Widget element | Source |
|---|---|
| Last activity | `users.last_activity_at` (throttled, §4). |
| Last login | `users.last_login_at` (existing). |
| Active sessions | Count of `sessions` rows for `Auth::id()`; display uses `public_reference` only. |
| Security status | Derived from account state (email verified, active status, recent sensitive events). |
| Links | Deep links into profile sections, including the `#security` anchor. |

---

## 7. Retention and privacy

| Data | Retention behavior |
|---|---|
| `audit_logs` | Follows the application's existing audit retention/archive policy; no separate indefinite activity store is added. |
| `users.last_activity_at` | Single overwritten timestamp; no history retained. |
| `sessions` | Framework session GC; revoked sessions deleted immediately ([Session Management](SESSION_MANAGEMENT.md) §7). |
| `user_email_changes` | 60-minute expiry; consumed or cancelled rows deleted/expired rows pruned. |
| Masking | User-facing IP masking and UA-derived device labels are mandatory. |
| Exports | This contract adds no user-facing audit export; any export remains governed by existing audit permissions. |

Sensitive values are minimized at write time (§3) rather than relying only on display-time filtering.

---

## 8. Localization

- Activity action labels, device fallbacks, and widget copy are defined in both `lang/en` and `lang/ar`.
- Timestamps are formatted per request locale; RTL layout is used for `ar` and LTR for `en`.
- Audit event **keys** remain stable language-neutral identifiers; only labels are localized.

---

## 9. Cross-links

- [Account Management Architecture](ACCOUNT_MANAGEMENT_ARCHITECTURE.md) — audit catalog and middleware
- [Profile Management](PROFILE_MANAGEMENT.md) — events emitted by profile flows
- [Session Management](SESSION_MANAGEMENT.md) — session data and revocation
- [Security Review](SECURITY_REVIEW.md) — redaction and privacy controls
- [Testing Report](TESTING_REPORT.md)
