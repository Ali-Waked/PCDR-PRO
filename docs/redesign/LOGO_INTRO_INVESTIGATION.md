# Logo Intro Investigation

## Scope

Investigated the existing fullscreen intro shown before `/login` without changing code first. The review covered the login route, Vue page/layout/component lifecycle, CSS layering and tokens, SVG asset integrity, reduced-motion handling, Inertia behavior, and existing source tests. No server or browser was started; the project owner runs the app on port 8070.

## Subagent status

Native subagents were used as requested:

- **Frontend Debugging Agent** — completed; identified the mount/storage/timing/CSS-inheritance causes below.
- **Brand Asset Agent** — completed; audited every official SVG candidate and confirmed byte-identical copies.
- **QA Agent** — completed; produced the selector- and route-level verification matrix retained in `LOGO_INTRO_TEST_REPORT.md`.
- **Motion Design Agent** — aborted twice by the harness. The background and motion direction were therefore decided by the main agent from the verified SVG composition and PCDR identity palette.

## Current control flow

1. `/login` redirects to the localized login route (`routes/web.php:67`, localized routes at `routes/web.php:265-269`).
2. `AuthenticatedSessionController` renders `Auth/Login` (`app/Http/Controllers/Auth/AuthenticatedSessionController.php:26-29`).
3. `Auth/Login.vue` imports and renders `BrandIntro` (`resources/js/Pages/Auth/Login.vue:7,35`).
4. The built bundle also contains `pcdr.auth.intro.v1` and `pcdr-logo-animated.svg`, so the intro was not absent from the build.

## Root causes

### 1. The skip flag was written at mount, not at completion

`BrandIntro.vue` previously called `remember()` immediately after deciding to play (`BrandIntro.vue:19-25,54-61`). That set `sessionStorage.pcdr.auth.intro.v1 = 1` before the animation had finished.

Consequences:

- A refresh or later visit to `/login` in the same tab skipped the intro.
- An interrupted mount could still suppress the next intro.
- Reduced-motion and normal paths both wrote the same flag, with no distinction between “completed” and “started”.

### 2. Teleport broke CSS custom-property inheritance

The overlay uses `<Teleport to="body">` (`BrandIntro.vue:82`). Its actual DOM parent is `<body>`, not `.pc-auth`.

However the overlay stylesheet consumed aliases declared only on `.pc-auth`:

- `--auth-navy-900`, `--auth-navy-800`, `--auth-navy`, `--auth-on-brand` at `dashboard.css:2338-2352`
- dark-theme remaps only under `html:root .pc-auth` at `dashboard.css:6152-6163`
- `.auth-intro` used those aliases for its background and text at `dashboard.css:2659-2668,2713`

Because the teleported node does not inherit from `.pc-auth`, those variables were invalid at computed-value time. The fullscreen background and primary text could therefore render transparent or inherit the wrong color, which explains the “missing/incorrect” appearance even though the component was mounted.

### 3. JS lifetime truncated the official SVG animation

The component previously started the leave transition at `2450ms` and finished at `3000ms` (`BrandIntro.vue:13-14,66-70`), while the official SVG animation continues well past five seconds:

- ring reveal: `1.3s + 1.6s`
- hands/cross: `1.7s + 1s`
- sunrise: `2.4s + 1.8s`
- wheat/drop/ripples: `3.0s` onward, with infinite ambient loops

The wrapper was removed while the SVG was still mid-sequence, so the reveal never looked complete.

### 4. Inertia could reuse or remount the page component

The storage guard alone was not sufficient. Inertia may reuse `Auth/Login` on same-page visits, so `onMounted` may not run again; conversely, a remount before the completion flag was written could replay the intro. A runtime-level guard was needed in addition to session storage.

### 5. First-paint flash risk

`visible` was initially `false` and only became `true` inside `onMounted`. The login shell could therefore paint for one frame before the overlay was inserted.

## Official logo audit

All official candidates are byte-identical:

| Path | Role |
| --- | --- |
| `/root/PCDR/pcdr/docs/logo.svg` | Canonical original source, read-only |
| `/root/PCDR/pcdr/public/assets/pcdr-homepage/logo/pcdr-logo-animated.svg` | Canonical published runtime asset, read-only |
| `/root/PCDR/pcdr-pro/docs/logo.svg` | Existing source copy |
| `/root/PCDR/pcdr-pro/public/assets/pcdr-homepage/logo/pcdr-logo-animated.svg` | Existing runtime copy |

- Size: `68,974` bytes
- SHA-256: `7dc83babf9f49f13e49d381d4c928e616cb41bf1285a6ead3310488aca98a80b`
- `viewBox="0 0 1130 1130"` — square, no distortion required
- Embedded CSS targets `#ring`, `#cross`, `#hands`, `#sunrise`, `#wheat`, `#drop`, `.lf`, `.gz`, `.ray`, `.gr`, `.rip`
- Fill colors include navy `#3E5172`, sand `#DAC3A3`, wheat `#CDB38B`, and teal `#71BCB7`

No copy-in or redraw was necessary; the pcdr-pro copies already match the original byte-for-byte.

## Why path-level animation was not introduced

The official asset is loaded as an external `<img>`. Page CSS cannot target paths inside an externally loaded SVG document. Enabling path-level control would require either editing the official SVG or inlining its full markup and overriding its embedded animation timing—both would increase risk to an approved brand asset. Per the task guidance, the fix uses a refined opacity/scale reveal around the unchanged official SVG.

## Background decision

A deep navy field would hide the logo's navy ring and hand/cross paths. The strongest direction is therefore a **brand-derived warm neutral field**: white flowing into PCDR sand, with a restrained wheat edge and a faint teal wash. It keeps the navy artwork legible, gives the sand/wheat/teal details room to read, and avoids generic SaaS gradients, neon, particles, and glassmorphism.

See `LOGO_INTRO_IMPLEMENTATION.md` for the applied values.
