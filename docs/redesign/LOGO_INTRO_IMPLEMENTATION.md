# Logo Intro Implementation

## Files changed

- `resources/js/Components/Auth/BrandIntro.vue`
- `resources/css/dashboard.css`
- `tests/Feature/LoginExperienceTest.php`
- `docs/redesign/LOGO_INTRO_INVESTIGATION.md`
- `docs/redesign/LOGO_INTRO_IMPLEMENTATION.md`
- `docs/redesign/LOGO_INTRO_TEST_REPORT.md`

No files in `/root/PCDR/pcdr` were modified. The official SVG was not copied, edited, or regenerated because the pcdr-pro copies are already byte-identical to the original.

## Lifecycle fix

`BrandIntro.vue` now:

- Decides whether to play during component setup, before first paint, so the login form cannot flash above the overlay.
- Uses a module-level `introStarted` flag to suppress replay across Inertia remounts within the same SPA runtime.
- Keeps `sessionStorage.pcdr.auth.intro.v1` as the once-per-tab guard after reload.
- Writes the session flag only in `finish()`, after the reveal has actually completed (or immediately for reduced-motion/skip paths).
- Guards `finish()` with `finished` so timers and navigation cannot emit twice.
- Sets `inert` on `#pc-auth-main` only while the intro is active, then restores it before focusing the email field.
- Clears all timers on unmount and restores `inert` if the component is removed early.
- Renders the tagline only when `site.slogan` resolves, avoiding a raw translation-key fallback.

## CSS token fix

The teleported overlay no longer consumes `.pc-auth`-scoped aliases. `.auth-intro` now defines its own `--intro-*` tokens from canonical `:root` PCDR palette values:

- `--intro-canvas: var(--pcdr-white)`
- `--intro-canvas-warm: var(--pcdr-sand-100)`
- `--intro-canvas-edge: color-mix(in srgb, var(--pcdr-wheat) 42%, var(--pcdr-white))`
- `--intro-navy: var(--pcdr-navy-700)`
- `--intro-teal-wash: color-mix(in srgb, var(--pcdr-teal) 12%, transparent)`
- `--intro-navy-line: color-mix(in srgb, var(--pcdr-navy-700) 10%, transparent)`
- `--intro-wheat-line: color-mix(in srgb, var(--pcdr-wheat) 55%, transparent)`
- `--intro-ink-soft: color-mix(in srgb, var(--pcdr-navy-700) 76%, transparent)`

This makes the fullscreen background and text colors resolve regardless of DOM teleporting or dashboard theme remapping.

## Selected background and rationale

The background is a restrained warm institutional field:

```css
background:
    radial-gradient(circle at 50% 38%, var(--intro-teal-wash), transparent 44%),
    linear-gradient(160deg, var(--intro-canvas) 0%, var(--intro-canvas-warm) 56%, var(--intro-canvas-edge) 100%);
```

A subtle wheat halo and a hairline navy inset frame add depth without decorative noise.

This direction was chosen because the official logo's ring, hands, and cross are navy. A deep navy fullscreen field would suppress those paths. White-to-sand provides high contrast for the navy artwork while allowing the sand sunrise, wheat, and teal drop to remain visible. The teal wash is kept at 12% so it reads as ambient light rather than a glow.

## Animation sequence

| Time | Action |
| --- | --- |
| 0–320ms | Warm canvas fades in. |
| 180–1080ms | Official SVG fades in with a subtle `0.965 → 1` scale. |
| 300–2100ms | Wheat halo settles behind the logo. |
| 1060–1580ms | Organization name and optional tagline rise 8px into place. |
| 1580–2450ms | Composition holds; no unnecessary waiting beyond the 3s ceiling. |
| 2450–3000ms | Overlay fades and scales `1 → 1.015`; `finish()` removes it and unlocks the form. |

Total duration: **3000ms**.

The official SVG's internal ambient loops continue inside the `<img>` but are intentionally not awaited. Waiting for all loops would make the intro theatrical and violate the 2–3s requirement; the wrapper reveal completes before handoff.

## Motion accessibility

- `prefers-reduced-motion: reduce` skips the overlay entirely, marks the intro played, unlocks the form, and focuses the email field.
- The reduced-motion CSS block now also covers `.auth-intro` nodes in case the preference changes while the overlay exists.
- The overlay is `aria-hidden="true"`; it is presentational and does not trap announcements.
- No animation delay is added to authentication requests or redirects.

## Login preservation

- No changes were made to `AuthLayout.vue`, `Login.vue`, password recovery, reset, verification, or CMS login pages.
- The login form is hidden from interaction only via `inert` while the intro is active; no CSS hides it permanently.
- If JavaScript never mounts the Vue app, no overlay is created. This project's Inertia shell still requires JavaScript to render any page; adding a global server-rendered `<noscript>` login form would be outside this task's scope and would touch every page.

## Verification note

Per the project owner's environment rules, no server, build, browser, screenshot, or test command was run by the implementing agent. Source-level regression assertions were added; browser checks are delegated to the owner and documented in `LOGO_INTRO_TEST_REPORT.md`.
