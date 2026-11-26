# PCDR PRO — Seed Data Register

**Date:** 2026-11-26
**Scope:** Homepage seed content introduced or retained by the redesign.
**Governance:** `NOT CLEARED`. Seeding technical capability is not institutional approval of the wording, targets or placeholders as published fact.

---

## 1. Canonical seeder (retained)

`database/seeders/HomepageContentSeeder.php` remains the canonical bilingual source for the ten frozen section keys:

`hero`, `intro`, `areas`, `framework`, `programs`, `targets`, `community`, `partners`, `knowledge`, `cta`

Only one change was made: the Arabic caveat for 2027 planning figures was tightened to the verbatim source sentence:

> مستهدفات تخطيطية أولية لعام 2027، تخضع للمراجعة بعد تحديد التمويل المتاح والقدرات التنفيذية.

The English parallel explicitly says the figures are initial planning targets subject to review once funding and implementation capacity are determined. Non-empty administrator values are never overwritten.

---

## 2. Additive seeder (new)

`database/seeders/RedesignHomepageContentSeeder.php` is registered after `HomepageContentSeeder` in `DatabaseSeeder.php`. It adds **53 items** only under existing canonical keys and never creates a new section key.

| Group | Section | Count | Provenance | Status |
|---|---|---:|---|---|
| `slide-1…slide-5` | `hero` | 5 | Supplied strategic slogan plus verified strategic axes | Source-derived carousel copy; no image attached |
| `ticker-1…ticker-4` | `hero` | 4 | Verified strategic-plan title and phase/target wording | Headlines only; **no URL** is seeded because no verified destination exists |
| `target-orphans`, `target-disabilities`, `target-psychosocial`, `target-committees` | `targets` | 4 | Supplied 2027 planning figures | Initial targets, subject to the mandatory caveat |
| `objective-1…objective-11` | `intro` | 11 | Supplied strategic objectives | Source-derived copy |
| `value-1…value-10` | `intro` | 10 | Supplied institutional values | Source-derived copy |
| `axis-01…axis-11` | `programs` | 11 | Published strategic pillars | Source-derived programme axes |
| `album-1…album-5` | `community` | 5 | Supplied album slot captions | **Provisional placeholders**, no media |
| `story-1…story-3` | `community` | 3 | Programme-type descriptions only | **Privacy-gated placeholders**, no person, no media |

All additive items are `inline`, published, and have `media_id = null`.

---

## 3. Safety and idempotency rules

- `firstOrCreate` is keyed on `(section, item_key)`; re-seeding does not duplicate rows.
- Translation fields are filled only when both the seeded value is non-empty and the stored value is empty, so administrator customizations survive re-seeding.
- `HomepageSection` rows are created only with the canonical keys if missing; the additive seeder does not change section status, order or enabled state for existing sections.
- Planning figures carry `numeric_value` for count-up rendering but remain labelled as targets, never achievements.
- Placeholder bodies explicitly say that no verified photograph is available or that publication requires beneficiary/guardian consent.

Verified by `RedesignHomepageSeederTest.php` and `HomepageBackendIntegrationTest.php`.

---

## 4. Content deliberately not seeded

| Category | Reason for exclusion |
|---|---|
| Achieved statistics, beneficiaries reached, budget or expenditure figures | No verified source; planning values must not be presented as results. |
| Donors, funders, certifications, awards, testimonials | No verified register or approval. |
| Named partners or partner logos | No approved list; only generic partnership categories are retained. |
| Board members, officeholders, signatories | OQ-49 remains `PARTIALLY RESOLVED — PENDING FORMAL APPROVAL`; no appointments are recorded. |
| Tenders, jobs, complaints data | Owned by other modules/gates; outside homepage redesign scope. |
| Real success stories, photos, publications or film assets | Consent/privacy gates OQ-24–OQ-29 are `DECISION REQUIRED`. |
| Public contact emails, phone numbers, social links | Site settings remain null except the test-pinned `complaints@pal-arc.org`. No new address is introduced. |
| External news URLs | No verified destination; ticker items are deliberately non-clickable. |

---

## 5. Administrative guidance

Before any seeded placeholder becomes public-facing evidence, an authorized owner must supply:

1. A verified source for the fact, image or destination.
2. A recorded privacy/consent decision where a person is involved.
3. An updated CMS item with explicit media and URL values.
4. A register entry in this document recording the change and its approval trail.

This work performs none of those approvals.
