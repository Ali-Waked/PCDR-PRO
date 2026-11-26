# Logo Intro Test Report

## Execution status

The implementing agent did **not** start a server, run `npm run build`, run PHPUnit, run Playwright, or capture screenshots. The project owner explicitly reserved running and verification for port 8070. No visual verification is claimed here.

Source-level regression assertions were added to `tests/Feature/LoginExperienceTest.php`:

- The intro owns its `--intro-*` tokens and contains no `--auth-*` aliases in the `.auth-intro` block.
- The once-per-tab guard is initialized before first paint and the session flag is written from `finish()`.
- Reduced-motion handling and the optional tagline are present.
- `BrandIntro` is asserted absent from forgot-password, reset-password, verify-email, and CMS login pages.
- The existing official logo hash assertions remain unchanged.

## Recommended verification on port 8070

Run from `/root/PCDR/pcdr-pro`:

```bash
npm run build
php artisan test --compact tests/Feature/LoginExperienceTest.php
php artisan test --compact tests/Feature/AuthenticationTest.php
BASE_URL=http://127.0.0.1:8070 npm run test:e2e -- tests/e2e/login-intro.spec.mjs
```

Useful selectors:

- `body > .auth-intro[data-auth-intro]`
- `.auth-intro__glyph img[src="/assets/pcdr-homepage/logo/pcdr-logo-animated.svg"]`
- `.auth-intro__identity strong`
- `#pc-auth-main[inert]`
- `input[name="email"]`
- `form.pc-stack button[type="submit"]`

## QA matrix

| Check | Method | Expected result |
| --- | --- | --- |
| Initial `/login` entry | Browser, fresh tab | Intro visible on first paint; no login flash. |
| Official logo | Browser + network tab | SVG request returns `200`; rendered image is the unchanged official asset. |
| Animation timing | Browser recording | Warm neutral field, logo reveal, name/tagline, then smooth fade by ~3s. |
| No replay after bad password | Browser | Submit wrong credentials; return to `/ar/login`; no `.auth-intro`. |
| No replay after validation errors | Browser | Submit empty/invalid form; no intro replay. |
| Refresh behavior | Browser | Refresh after completion; intro does not replay. Refresh before completion may replay. |
| Inertia navigation | Browser | Locale switch or auth-page navigation does not replay the intro unnecessarily. |
| Successful login | Browser | No added delay between submit and dashboard redirect. |
| Reduced motion | Browser emulation | No overlay; form immediately interactive and email focused. |
| Mobile layout | Browser at ~390×844 | Logo and text fit; no horizontal overflow. |
| Desktop layout | Browser at ~1440×900 | Centered composition; no clipping. |
| Short viewport | Browser at ~900×560 | Reduced glyph size via `max-height: 620px` rule. |
| Console health | Browser devtools | No new JS errors; SVG asset has no 404. |
| JS disabled | Browser with JS off | No overlay is injected; Inertia shell limitation documented. |

## Remaining limitations

- Browser visual confirmation, contrast measurement, and motion smoothness cannot be asserted until the owner runs the app on port 8070.
- The official SVG's embedded ambient loops are preserved but intentionally not awaited; only the wrapper reveal is synchronized to the 3s handoff.
- JavaScript-disabled login depends on an Inertia server-rendered fallback that this project does not provide globally; this change does not introduce one.
- `npm run build` is required before the owner's browser verification if the server serves hashed production assets.
