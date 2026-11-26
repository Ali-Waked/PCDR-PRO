# PCDR PRO — Reconstruction Test Report

**Date:** 2026-11-26  
**Scope:** Full Laravel suite, homepage CMS/API contracts, theme persistence, dashboard shell, route/legacy audit, production build and browser smoke.  
**Governance:** `NOT CLEARED`. This report records verification only. It does not approve ADR-001–017, close OQ-24–29, mark OQ-49 resolved, authorize Phase 6, or deploy.

---

## 1. Verification method

The complete PHP suite was executed in smaller file batches because repeated harness server restarts aborted long-running single-process runs. Every `tests/Unit` and `tests/Feature` test file was included exactly once across the batches. No test was skipped, excluded by group, or modified to hide a failure.

Pinned files were restored to the authoritative source hashes before each batch. Two transient defects caused by the restart cycle were identified and corrected:

1. `PublicWebsiteTest` briefly reverted its `app.blade.php` hash assertion to the theme-modified value; it was restored to the authoritative source hash.
2. `useTheme.js` briefly reverted to the earlier `data-theme = resolved` implementation; it was restored so that `system` deletes `data-theme` and leaves CSS media queries authoritative.

---

## 2. Full PHP suite (chunked execution)

| Batch | Tests | Passed | Assertions | Result |
|---|---:|---:|---:|---|
| Unit + feature batch 1 | 66 | 66 | 1,677 | Passed |
| Feature batch 2 | 56 | 56 | 7,223 | Passed |
| Feature batch 3 | 55 | 55 | 832 | Passed |
| Feature batch 4 | 46 | 46 | 908 | Passed |
| Feature batch 5 | 41 | 41 | 919 | Passed |
| Feature batch 6 | 50 | 50 | 1,008 | Passed |
| Feature batch 7 | 26 | 26 | 183 | Passed |
| `PublicWebsiteTest` | 30 | 30 | 3,535 | Passed |
| Feature batch 8 | 17 | 17 | 569 | Passed |
| `ThemePreferenceTest` | 9 | 9 | 69 | Passed |
| Feature batch 9 | 36 | 36 | 693 | Passed |
| **Total** | **432** | **432** | **17,616** | **Passed** |

No PHPUnit failure remained after the two restart-induced reverts were corrected. A single-process `vendor/bin/phpunit` run could not be retained to completion because the harness restart killed the process and discarded its output; the chunked run provides equivalent coverage of every test file.

---

## 3. Targeted verification

| Check | Result |
|---|---|
| `node --test tests/homepage-content.test.mjs` | 6/6 passed |
| `PrototypeHomepageTest` | 7/7 passed in earlier targeted run |
| `RedesignHomepageApiTest` + `RedesignHomepageCmsTest` | 12/12 passed in earlier targeted run |
| Homepage/legacy/public/theme/account set | 78/78, 4,431 assertions passed |
| Auth/admin/authorization set | 48/48 passed |
| Account profile set | 7/7 passed |
| `npm run build` | Passed after the final theme-runtime correction; 838 modules |
| Targeted Pint on all intended changed PHP files | Passed |
| `git diff --check` | Clean |
| HTTP smoke `/ar`, `/en`, `/api/public/homepage?locale=ar` | 200 / 200 / 200 |

Build warnings were limited to the optional `fontaine` package notice and the existing large-chunk advisory. No build error was recorded.

---

## 4. Pinned-file integrity

| File | SHA-256 | Result |
|---|---|---|
| `resources/css/pcdr-home.css` | `d555d276d7abbce07004fc4f73c54e4a104a5e0f9bffa26ff47df71e299dc9c9` | Match |
| `resources/views/app.blade.php` | `2a7affa19d09acda482e3d3dbce2ef97d374c259eaaa677848145c24ba871765` | Match |
| `resources/js/Components/LanguageSwitcher.vue` | `29f9798faee179ba36da521bd69d0e28badc73e33dcc6f51420f9215f4471537` | Match |

The theme bootstrap is implemented only in non-pinned JS/CSS. An intermediate local commit had modified `app.blade.php`; the corrective work restores the pinned byte-for-byte source and updates the tests accordingly.

---

## 5. Source authenticity

| Artefact | SHA-256 | Result |
|---|---|---|
| `parc-gaza-website.zip` | `1852e4153ea274bf14d46f16cb504e525bd65700050477811ec60a0956633c18` | Verified |
| `docs/redesign/source-homepage/index.html` | `32ec0224e632708f6941369ff60a50c9c16197d4de344591a5401a16ad6e84f4` | Verified |
| `docs/redesign/source-homepage/logo.jpg` | `1aedb3ecec106d3de83f1bd5e99338487fbe880b89831559c116786af5bdfa4e` | Verified |
| `public/assets/pcdr-homepage/logo/pcdr-prototype-logo.jpg` | `f70699bdd9b1c259b0dc230affe81dff1d93701e82b746ebf481ca9a12fea5db` | Extracted from embedded 360×360 source image |

No invented media, social link, film URL, donor, partner, testimonial, certification, budget or contact detail was introduced.

---

## 6. Firefox browser smoke

Browser/e2e execution is no longer performed by this work session. The user runs the application server on port `8070` and has explicitly reserved browser, screenshot, Playwright and server execution.

A partial Firefox run was captured before that restriction:

| Spec / check | Result |
|---|---|
| `tests/e2e/footer-identical.spec.mjs` | 3/3 passed |
| `public-header-nav.spec.mjs` — pill/burger/menu layout at tested scales | 6/6 passed; one needed a retry |
| `public-header-nav.spec.mjs` — inner pages keep measured header geometry | Passed |
| `public-header-nav.spec.mjs` — active state on trigger and panel item | Failed once: `.ps-dropdown__item[aria-current="page"]` count was `0` |

The header nav spec did not complete. The 390/768/1440 locale matrix, anchors, tabs, carousel, ticker, theme attributes, and console/page-error audit were not run. No complete browser success is claimed; those checks remain user-run. The active-page-state failure above is recorded as an open browser-level issue for follow-up.

---

## 7. Known limitations

- The publication workflow bypass remains: direct `published ↔ draft` transitions are accepted because existing tests depend on that behavior.
- Hardcoded Vue fallback copy is source-derived prototype scaffolding, not approval of OQ-05/OQ-06.
- A pre-existing role-delegation risk remains: a non-owner with `users.assign_roles` may grant system admin roles. It was not worsened.
- `/partners` and `/partnerships` remain duplicate aliases pending an explicit canonical-path decision.
- Deeper page-by-page dashboard normalization remains residual work; the shared shell, token layer and theme apply globally.
- No governance status was promoted, no Phase 6 action was taken, and nothing was pushed or deployed.
