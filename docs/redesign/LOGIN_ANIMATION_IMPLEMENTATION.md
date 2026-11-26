# PCDR PRO — Login Animation Implementation

**Date:** 2026-11-26
**Component:** `resources/js/Components/Auth/BrandIntro.vue`
**Styles:** `resources/css/dashboard.css` (authentication block)
**Asset:** `/assets/pcdr-homepage/logo/pcdr-logo-animated.svg` (SHA-256 `7dc83babf9f49f13e49d381d4c928e616cb41bf1285a6ead3310488aca98a80b`)

---

## 1. Approach

The intro is a client-side overlay rendered only by `Auth/Login.vue`. It is additive: it never gates a route, session, middleware, or server response. The login form is present in the same Inertia payload and is exposed the moment the overlay is skipped or removed.

The official animated SVG is displayed with a plain `<img>`; its original embedded keyframes (`ringIn`, `grow`, `sway`, `fadeUp`, `rise`, `pulse`, `dropIn`, `bob`) and SVG paths are unchanged. No path was invented and no additional logo element was drawn.

`Teleport to="body"` is used for the overlay so `position: fixed` is relative to the viewport. Without it, the `transform` on the animating `.pc-auth__panel` ancestor created a containing block and confined the overlay to the form column (verified: 430 × 322px). After the fix, the measured overlay is exactly the viewport (1440 × 900 / 820 × 1180 / 390 × 844).

---

## 2. Timeline

| Time | Effect |
|---|---|
| 0.0–0.4s | `auth-intro-bg` fades the navy field in. |
| 0.4s | `auth-intro-halo` begins a 2.4s restrained scale/opacity reveal of the sand ring. |
| 0.4–1.3s | `auth-intro-logo` reveals the official SVG with a soft clip-path wipe, opacity and 0.94 → 1 scale. |
| 1.25–1.77s | `auth-intro-identity` raises the organization name and localized slogan into view. |
| 1.8–2.45s | Logo remains fully visible (its native SVG micro-animation continues). |
| 2.45s | `LEAVE_AT`: `is-leaving` applies `auth-intro-leave` — 550ms opacity fade, slight 1 → 1.02 scale, blur to 6px. |
| 3.0s | `FINISH_AT`: overlay unmounts, `inert` is removed, `finish` emits; focus moves to `input[name="email"]`. |

Total sequence: **3.0 seconds**, inside the requested 2–3s target. Easing uses the project tokens `--motion-ease-decelerate` and `--motion-ease-standard`. There is no bounce, spin, particle system, heavy 3D, or external animation library.

---

## 3. Lifecycle and replay control

```
sessionStorage key: pcdr.auth.intro.v1
LEAVE_AT = 2450ms
FINISH_AT = 3000ms
```

- On mount, reduced motion or an existing session key causes an immediate `finish()` — the intro never renders.
- Otherwise the key is written, `#pc-auth-main` is made `inert`, and the timed sequence starts.
- Timers are cleared in `onBeforeUnmount`; `inert` is always restored in both `finish()` and unmount cleanup, including early teardown.
- `sessionStorage` access is wrapped in `try/catch` so private browsing or blocked storage cannot break the page.
- Ordinary validation errors do not replay the intro because only `Auth/Login.vue` mounts it, and a session visit suppresses later replays. Verified in browser: login → forgot password → back → reload produced `intro=0`.

---

## 4. Reduced motion

Two independent guards are in place:

1. JavaScript: `window.matchMedia('(prefers-reduced-motion: reduce)').matches` skips the overlay entirely and focuses the form immediately.
2. CSS: `@media (prefers-reduced-motion: reduce)` reduces auth animation/transition durations to `0.01ms`, forces `.is-ready` content opacity to 1 and transform to none, and the global `resources/css/motion.css` guard covers the teleported overlay.

Browser verification with `reducedMotion: 'reduce'` at 1440px: AR `dir=rtl intro=0 form=1`, EN `dir=ltr intro=0 form=1`.

---

## 5. JavaScript-failure behavior

The overlay is not a server-side gate: there is no middleware, redirect, cookie, or session flag that can leave a user stranded. `visible` starts as `false`, so if Vue mounts but the intro logic fails before `onMounted` completes, no overlay is shown and the form is usable. If JavaScript is unavailable entirely, the application's pre-existing Inertia client-rendering limitation applies; the redesign introduces no additional JS dependency for reaching the form.

---

## 6. Shared auth entrance

All auth pages use the short `.pc-auth.is-ready` entrance (`auth-rise`, 200–320ms, ≤210ms delays) on the brand logo, statement, footer and form panel. This is deliberately separate from the login intro and plays once on page readiness. It is suppressed and forced visible under reduced motion.

---

## 7. Verification summary

| Check | Result |
|---|---|
| Overlay covers viewport at 1440×900, 820×1180, 390×844 | pass |
| Official SVG request succeeds and loads (`naturalWidth > 0`) | pass |
| `inert` applied during intro, removed afterwards | pass |
| Focus moves to `input[name=email]` after finish | pass |
| No replay on back/forward or refresh within session | pass |
| Reduced-motion skip in AR and EN | pass |
| Browser console/page errors during intro and settled views | none |
| `npm run build` | pass (fontaine/chunk warnings only) |
