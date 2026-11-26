# PCDR PRO — Homepage Test Report

**Date:** 2026-11-26  
**Scope:** Approved homepage implementation, CMS integration, public API, content safety and pinned-file integrity.  
**Governance:** `NOT CLEARED`. This report records verification only. It does not approve ADR-001–017, close OQ-24–29, mark OQ-49 resolved, authorize Phase 6, or deploy.

---

## 1. Authoritative source verification

| Artefact | SHA-256 | Result |
|---|---|---|
| `parc-gaza-website.zip` | `1852e4153ea274bf14d46f16cb504e525bd65700050477811ec60a0956633c18` | Verified |
| `docs/redesign/source-homepage/index.html` | `32ec0224e632708f6941369ff60a50c9c16197d4de344591a5401a16ad6e84f4` | Verified |
| `docs/redesign/source-homepage/logo.jpg` | `1aedb3ecec106d3de83f1bd5e99338487fbe880b89831559c116786af5bdfa4e` | Verified |
| `public/assets/pcdr-homepage/logo/pcdr-prototype-logo.jpg` | `f70699bdd9b1c259b0dc230affe81dff1d93701e82b746ebf481ca9a12fea5db` | Extracted from embedded 360×360 image |

The supplied ZIP was the only design source. No substitute design, invented achievement, donor, partner, testimonial, certification, budget, social link, film URL or contact detail was introduced.

---

## 2. Pinned-file integrity

| File | Expected SHA-256 | Result |
|---|---|---|
| `resources/css/pcdr-home.css` | `d555d276d7abbce07004fc4f73c54e4a104a5e0f9bffa26ff47df71e299dc9c9` | Match |
| `resources/views/app.blade.php` | `2a7affa19d09acda482e3d3dbce2ef97d374c259eaaa677848145c24ba871765` | Match |
| `resources/js/Components/LanguageSwitcher.vue` | `29f9798faee179ba36da521bd69d0e28badc73e33dcc6f51420f9215f4471537` | Match |

The stale hash assertions in `PublicWebsiteTest` were corrected to the authoritative source hashes. Theme bootstrap lives in non-pinned JS/CSS only.

---

## 3. Automated test results

| Command / suite | Result | Notes |
|---|---|---|
| `node --test tests/homepage-content.test.mjs` | 6 passed, 0 failed | Composable getter/ref/array handling, tab grouping and allowed destinations |
| `vendor/bin/phpunit tests/Feature/PrototypeHomepageTest.php` | 7 passed, 7 failed → fixed → 7 passed | Final run: 7 tests, 110 assertions, passed |
| `vendor/bin/phpunit tests/Feature/RedesignHomepageApiTest.php tests/Feature/RedesignHomepageCmsTest.php` | 12 passed | 74 assertions |
| Homepage, legacy, public, API, CMS, theme and account set | 78 passed | 4431 assertions; includes `PublicWebsiteTest`, `RedesignHomepageFrontendTest`, `HomepageBackendIntegrationTest`, `ThemePreferenceTest`, `AccountProfileTest` |
| Auth/admin/authorization set | 48 passed | 578 assertions |
| `npm run build` | passed | 838 modules; only fontaine/chunk-size warnings |
| PHP syntax (`php -l`) | passed | All changed PHP files |
| Targeted Pint | passed | All changed PHP files in scope |

Two defects were found and fixed during verification:

1. Recursive Eloquent aliases on snake-case columns caused memory exhaustion during seeding. Alias accessors/mutators were removed and camelCase CMS keys are normalized in `Cms\HomepageController` before writes.
2. The seeder used `display` instead of the existing `display_value` column. It now writes `display_value`.

No test was skipped to obtain a passing result.

---

## 4. Content and behavior assertions covered

- Nine canonical sections are seeded in order: `hero`, `ticker`, `impact`, `programs`, `news`, `albums`, `about`, `stories`, `footer`.
- Disabled sections are hidden from the public homepage and public API but remain in CMS preview.
- Prototype component order, CSS namespace, anchors and absence of legacy shell markers are asserted.
- Planning figures remain planning targets; the caveat is preserved verbatim: `مستهدفات تخطيطية أولية لعام 2027، تخضع للمراجعة بعد تحديد التمويل المتاح والقدرات التنفيذية.`
- Unsafe links are rejected at validation and become `null` in serialized payloads; source `href="#"` placeholders remain inert.
- Theme values are whitelisted; `system` is stored as `null` and leaves `data-theme` unset at runtime.
- Profile theme updates do not require resubmitting `name` or `locale`.

---

## 5. HTTP and build smoke

```text
GET /ar                                  → 200
GET /en                                  → 200
GET /api/public/homepage?locale=ar       → 200
```

`npm run build` produced a fresh Vite manifest and assets. Browser-level verification is reported in `docs/reconstruction/TEST_REPORT.md`; it is not claimed in this homepage report.

---

## 6. Known limitations

- The publication workflow bypass remains: direct `published ↔ draft` transitions are still accepted because existing tests depend on that behavior.
- Hardcoded Vue fallback copy is source-derived scaffolding, not an approval of OQ-05/OQ-06.
- No verified media, social, film, donation, donor, partner, testimonial or contact destination was supplied; placeholders remain inert.
- Full-suite and Firefox browser outcomes are recorded in the reconstruction test report.
