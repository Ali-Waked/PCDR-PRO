# Profile Management

> **Status:** Contract-level documentation. Described behavior is the agreed target; verification is pending in [TESTING_REPORT.md](TESTING_REPORT.md).

---

## 1. Scope

Profile management covers four self-service flows on the `Account/Profile/Show` page: personal information, email address change, password change, and profile photo. Session and activity views are documented in [Session Management](SESSION_MANAGEMENT.md) and [Activity Tracking](ACTIVITY_TRACKING.md).

| Flow | Primary table | Audit action(s) | Limiter |
|---|---|---|---|
| Personal information | `users` | `account.profile.updated` | `account-sensitive` |
| Email change | `user_email_changes` | `account.email_change.requested` / `confirmed` / `cancelled` | `account-email` |
| Password change | `users` | `account.password.changed` | `account-sensitive` |
| Profile photo | `users.avatar_media_id`, `media` | `account.avatar.uploaded` / `removed` | `account-avatar` |

All endpoints require `auth` + `account.active`; data is always scoped to `Auth::id()`. See [Account Management Architecture](ACCOUNT_MANAGEMENT_ARCHITECTURE.md) §4–§7 for the full route and middleware map.

---

## 2. Page sections

| Section | Content | Mutation |
|---|---|---|
| Overview | Account status, email verification state, member-since date, shortcuts. | None |
| Personal Information | Name and locale; email shown with pending-change notice. | `account.profile.update` |
| Profile Photo | Current image preview or initials fallback; upload/remove controls. | `account.avatar.store` / `destroy` |
| **Security** (`#security`) | Password change and email change controls; stable anchor for widget links. | `account.password.update`, `account.email.*` |
| Connected Devices | Active sessions keyed by `public_reference`. | `account.sessions.*` |
| Activity History | Safe, localized audit entries from `AuditService`. | None |

The page reuses the existing dashboard layout and `Ui/` components and renders identically in AR (RTL) and EN (LTR).

---

## 3. Personal information

- Editable fields are an explicit allowlist (e.g. `name`, `locale`). Protected columns such as `status`, `is_platform_owner`, `email`, `password`, `remember_token`, and `avatar_media_id` cannot be set through this endpoint.
- Validation runs through a Form Request and uses the existing bilingual validation messages.
- A successful update records `account.profile.updated` with safe before/after values only.
- Email is **never** changed directly here; it always follows §4.

---

## 4. Email change

### 4.1 State model

```text
  request (POST /email)
active(old) ───────────────► pending (old email still active)
      ▲                         │
      │                         ├── confirm (GET /email/confirm/{token}, signed + auth)
      │                         │      ──► active(new)
      │                         │      ──► revoke other sessions, rotate remember token
      │                         │
      └──── cancel (DELETE /email) ◄┘
                                  └── expiry (>60 min) ──► active(old), row inert
```

### 4.2 Rules

| Rule | Contract |
|---|---|
| Pending storage | `user_email_changes` row with `new_email`, `token_hash`, `expires_at = created_at + 60 minutes`. |
| Token | Hashed at rest, single-use; the raw token exists only in the signed link. |
| Link | `signed` URL, 60-minute consistency with token expiry; endpoint still requires `auth` and `account.active`. |
| Old address | Remains the active login email until confirmation; a notification is sent to the **old** address when a change is requested. |
| Other sessions | Revoked **on confirmation**; the current session is preserved. |
| Remember token | Rotated on confirmation. |
| Conflicts | New email must be unique; one pending change per user (a new request supersedes or is rejected per service rule). |
| Cancellation | Deletes/invalidates the pending row; old email remains active. |
| Expiry | Expired tokens are inert and pruned; no state change occurs. |

### 4.3 Endpoint summary

| Action | Method / suffix | Result |
|---|---|---|
| Request | `POST /email` | Creates pending row, notifies old address, records `account.email_change.requested`. |
| Cancel | `DELETE /email` | Removes pending row, records `account.email_change.cancelled`. |
| Confirm | `GET /email/confirm/{token}` (`signed`) | Consumes token, switches email, revokes other sessions, rotates remember token, records `account.email_change.confirmed`. |

All three are rate-limited by `account-email` (3/minute). Mail is localized to the recipient's `locale` with a code-owned fallback; no token value is written to audit logs.

---

## 5. Password change

| Control | Contract |
|---|---|
| Current password | `current_password` required and verified against the authenticated user. |
| New password | `Password::min(12)` and `confirmed` (matching confirmation field). |
| Sessions | Other sessions revoked; **current session preserved**. |
| Remember token | Rotated. |
| Audit | `account.password.changed` with no password or hash in before/after/context. |
| Throttle | `account-sensitive` (5/minute). |
| UI | Lives under the Security section (`#security`) on the profile page. |

Password reset (forgot-password) is a separate existing flow and is not replaced by this endpoint.

---

## 6. Profile photo

| Control | Contract |
|---|---|
| Storage | Private disk only; `Media` row marked `is_restricted = true`; `users.avatar_media_id` points at that row. |
| Accepted types | JPG, PNG, WebP. |
| Size | Maximum 2 MB. |
| Content checks | Extension/MIME validation **plus** magic-byte inspection and `getimagesize()` verification. |
| Filename | Generated with ULID; user-supplied names are never used for storage paths. |
| Serving | 30-minute `signed` download route with ownership check; no direct public storage URL. |
| Replacement | Old file is detached and deleted when no other record references it. |
| Removal | `account.avatar.destroy` detaches the Media row, cleans up the unused file, records `account.avatar.removed`. |
| Fallback | Initials are rendered when `avatar_media_id` is null or the file is unavailable. |
| Throttle | `account-avatar`: 10/minute and 30/hour. |

Contract-level validation profile (illustrative):

```php
'avatar' => [
    'required',
    'file',
    'mimes:jpg,jpeg,png,webp',
    'max:2048', // 2 MB
],
// server-side: magic-byte signature check + getimagesize() verification
// storage: private disk, ULID filename, Media is_restricted = true
```

Upload events record `account.avatar.uploaded` without embedding the storage path or raw file contents in audit context. See [Security Review](SECURITY_REVIEW.md) §3–§5 for the upload threat model.

---

## 7. Audit and privacy

Every profile mutation emits one of the `account.*` actions listed in [Account Management Architecture](ACCOUNT_MANAGEMENT_ARCHITECTURE.md) §6 and [Activity Tracking](ACTIVITY_TRACKING.md) §2. Audit context is redacted: no passwords, tokens, raw session IDs, or private file paths are stored. The user-facing Activity History exposes only safe, localized, masked fields.

---

## 8. Localization

- All labels, help text, errors, and security mail keys exist in `lang/en` and `lang/ar`.
- The Security section is reachable through the stable `#security` anchor used by the dashboard widget; the anchor works in both RTL and LTR layouts.
- Dates and times follow the request locale; validation messages use the existing bilingual `lang/*/validation.php` resources.

---

## 9. Cross-links

- [Account Management Architecture](ACCOUNT_MANAGEMENT_ARCHITECTURE.md)
- [Session Management](SESSION_MANAGEMENT.md) — revocation semantics and current-session handling
- [Activity Tracking](ACTIVITY_TRACKING.md) — audit projection and masking
- [Security Review](SECURITY_REVIEW.md)
- [Testing Report](TESTING_REPORT.md)
