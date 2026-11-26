# PCDR PRO — Authentication Experience Design Specification

**Date:** 2026-11-26
**Workspace:** `/root/PCDR/pcdr-pro`
**Status:** Implemented. Institutional design system for every existing authentication page.

---

## 1. Scope

The redesign covers **only the authentication pages that already exist** in the application:

| Page | Route | Inertia component |
|---|---|---|
| Platform login | `GET/POST {locale}/login` | `Auth/Login` |
| Forgot password | `GET/POST {locale}/forgot-password` | `Auth/ForgotPassword` |
| Reset password | `GET {locale}/reset-password/{token}`, `POST {locale}/reset-password` | `Auth/ResetPassword` |
| Email verification notice | `GET {locale}/verify-email` | `Auth/VerifyEmail` |
| CMS login | `GET/POST {locale}/cms/login` | `Cms/Login` |

**Not introduced:** public registration, two-factor authentication, or a Laravel `password.confirm` page. Route inspection confirmed none of these features exist. No authentication architecture was replaced.

---

## 2. Design intent

The experience is an institutional identity system, not a SaaS template. It is derived from the approved public homepage:

- Restrained navy field, teal/sand accents, generous whitespace.
- Official logo only, never redrawn or substituted (see `LOGO_ASSET_AUDIT.md`).
- One clear focal point per screen: the brand on the left, the task on the right.
- Motion that reveals rather than performs.

### Brand palette

| Token | Hex | Auth usage |
|---|---|---|
| Navy | `#3E5172` | Brand gradient, interactive accent, language-switch thumb |
| Teal | `#71BCB7` | Accent rule, halo, success mark, focus ring |
| Sand | `#DAC3A3` | Eyebrow and intro halo border |
| Wheat | `#D7C5A1` | Registered identity accent |
| White | `#FFFFFF` | Form surface and text on navy |

Auth tokens in `resources/css/dashboard.css` are aliases of the immutable palette in `resources/css/tokens.css`; darker navy stops are existing homepage gradient stops. No new hue was added.

### Typography

The shell inherits the approved application fonts — **IBM Plex Sans Arabic** for Arabic and **Inter** for English — from `resources/css/app.css`. No font was added. Arabic letter-spacing is neutralised for uppercase eyebrows and Latin-tracked headings; email fields are forced `dir="ltr"` so addresses remain readable.

---

## 3. Shared layout — `AuthLayout.vue`

Every auth page declares `defineOptions({ layout: AuthLayout })`, so all pages share:

1. `div.pc-auth` with `:dir="direction"` from `HandleInertiaRequests` (AR → `rtl`, EN → `ltr`).
2. Skip link to `#pc-auth-main`.
3. `AdminToastProvider` for consistent notifications.
4. Institutional brand panel:
   - official static logo (56 × 56), organization `site.name`;
   - `ui.dashboard_secure_workspace` eyebrow with teal rule;
   - `ui.dashboard_auth_title` statement and localized `site.slogan`;
   - one restrained decorative geometry ring;
   - footer identity line.
5. Form panel with the single shared `LanguageSwitcher` (unchanged selector contract).
6. `main#pc-auth-main` containing the page slot, made `inert` only while the login intro is visible.

The layout adds an `is-ready` class on mount so entrance animations never hide content before Vue hydration. There is exactly one `<h1>` per page, in `AuthHeader`, inside the form panel.

### Desktop composition

`grid-template-columns: minmax(0, 1.05fr) minmax(420px, 0.95fr)` — brand left, form right. The form column is centred and capped at 430px.

### Tablet and mobile

At ≤820px the grid becomes one column and the brand panel becomes a compact header. At ≤560px the panel loses wide padding, the form gap tightens, and `.pc-auth__links` stacks with a full-width submit button. All controls are at least 44px tall (48px inputs and primary buttons on wider screens). Verified overflow at 1440, 820 and 390px is zero.

---

## 4. Reusable components

| Component | Responsibility |
|---|---|
| `Components/Auth/BrandIntro.vue` | Full-screen animated official-logo intro (login only), replay lifecycle, reduced-motion skip |
| `Components/Auth/AuthHeader.vue` | Single `h1` + optional subtitle for every auth page |
| `Components/Auth/PasswordInput.vue` | Labelled password field with accessible show/hide toggle |
| `Components/Auth/AuthAlert.vue` | Semantic `role="status"` / `role="alert"` message surface |
| `Components/Auth/AuthSuccessState.vue` | Success confirmation with mark, title, message, continuation link |
| `Components/Ui/TextField.vue` | Existing email/text field, reused for all email inputs |
| `Components/Ui/CheckboxField.vue` | Existing remember-me control, unchanged |
| `Components/Ui/Button.vue` | Existing button with `processing` state and disabled-while-submitting |

No duplicate auth component was created without a reason: `TextField`, `CheckboxField` and `Button` were already correct and were reused.

---

## 5. Page-level behavior

### Login

- Full-screen intro, then welcome heading, email, password with toggle, remember me, forgot-password link, and primary submit.
- Flash success messages render through `AuthAlert`.
- `useForm` post to `localePath('/login')` with `preserveScroll`; input names (`email`, `password`, `remember`) and `form button[type=submit]` are unchanged for E2E helpers.
- Validation and authentication errors remain Laravel-driven and render under the relevant field.

### Forgot password

- Initial state: official brand, heading, explanatory copy, email, send button, back-to-login link.
- After submission: `AuthSuccessState` with `auth.check_email_title` and the flash message.
- The backend now returns the same `__(Password::RESET_LINK_SENT)` for existing and unknown emails (only broker throttling returns an error), so the UI cannot disclose account existence.

### Reset password

- Email (prefilled from the signed link), new password, confirmation, both with independent visibility toggles.
- Laravel broker token and email are still posted to `localePath('/reset-password')`; no custom token handling was added.
- Errors are bound per field; success redirects to login with a flash message.

### Email verification

- Official brand, verification heading and explanation, resend button with `processing` state, logout link, and flash status through `AuthAlert`.
- Signed URL verification remains entirely server-side.

### CMS login

- Aligned with the same shell, header, email/password fields, password toggle, alert and button while preserving `localPath('cms/login')`, `bootstrapHint`, and its existing translations (`useI18n` for CMS keys, `useLocale` for shared auth keys).

---

## 6. Accessibility

- Skip link and semantic `main` landmark.
- Every input has a real `<label>`; errors set `aria-invalid` and `aria-describedby`.
- Password toggle has `aria-label` and `aria-pressed`, and is a `button[type="button"]`.
- Alerts use `role="status"` or `role="alert"`; success state uses `role="status"`.
- The intro is `aria-hidden="true"`; the form panel is `inert` during the intro, preventing keyboard access to a covered control, and focus moves to `input[name="email"]` when the intro finishes.
- Visible focus styles on inputs, toggles, links and buttons; ≥44px touch targets.
- Reduced motion is respected both in CSS and in the intro's mount logic.
- No information is conveyed by animation alone — the same text is available without motion.

---

## 7. Localization

All visible strings come from translations: `lang/{ar,en}/ui.php` for shared auth/interface strings, `lang/{ar,en}/website.php` for CMS labels, and `site.name` / `site.slogan` from shared props. New keys added in both locales:

- `ui.auth.show_password`
- `ui.auth.hide_password`
- `ui.auth.check_email_title`

No hardcoded interface copy was introduced in any auth page.

---

## 8. What was deliberately not changed

- Routes, middleware, `throttle:login` / `throttle:password-reset`, guest and signed-URL middleware.
- `AuthenticatedSessionController`, `LoginRequest`, `EmailVerificationController`, Laravel password broker, session/remember behaviour, CSRF, redirect semantics, audit events.
- E2E selectors: `input[name="email"]`, `input[name="password"]`, `form button[type="submit"]`.
- The global language switcher and `tests/global-ux.test.mjs` contract (`<LanguageSwitcher />`, no `pathForLocale` in the auth layout, `:root,.pc-app,.pc-auth` token scope and a reduced-motion query in `dashboard.css`).
