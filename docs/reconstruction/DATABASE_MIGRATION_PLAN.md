# PCDR PRO — Database Migration Plan

**Date:** 2026-11-26  
**Status:** Additive migrations implemented and covered by `RefreshDatabase` feature tests.  
**Governance:** `NOT CLEARED`. This plan does not approve ADR-001–017, close OQ-24–29 or OQ-49, authorize Phase 6, or deploy.

---

## 1. Principles

1. No destructive change to existing tables, columns or rows.
2. Existing auth, RBAC, governance, audit and content data are preserved.
3. Every new column is nullable or carries an inert default.
4. Legacy homepage sections are disabled, not deleted.
5. Related uncommitted work is preserved, including the older theme migration already present in the worktree.
6. Cache invalidation accompanies every content mutation.

---

## 2. Migration inventory

| Migration | Purpose | Status |
|---|---|---|
| `2026_11_26_050331_add_theme_to_users_table.php` | Older untracked migration adding nullable `users.theme` after `locale` | Preserved user work; not modified |
| `2026_11_26_090000_enhance_homepage_prototype_content.php` | Additive prototype fields for homepage sections/items/translations | Implemented for this work |
| `2026_11_26_100000_add_theme_to_users_table.php` | Guarded migration ensuring nullable `users.theme` with `system` default | Implemented for this work |

The two theme migrations are reconciled by guarded ordering rather than deletion. `050331` creates the column if absent; `100000` detects the existing column and normalizes its nullable/default definition. Both `down()` methods drop the column only if it exists, so forward migration and reverse rollback are deterministic. Neither was deleted by this work.

---

## 3. Homepage schema additions

### `homepage_section_translations`

| Column | Type | Default | Used for |
|---|---|---|---|
| `ticker_label` | string | `null` | Ticker label |
| `tab_labels` | JSON | `null` | News/About tab labels |
| `video_url` | string | `null` | Safe video URL |
| `video_caption` | string | `null` | Video caption |

### `homepage_section_items`

| Column | Type | Default | Used for |
|---|---|---|---|
| `tab` | string(32) | `null` | Tab grouping |
| `is_featured` | boolean | `false` | Featured item flag |
| `motif_key` | string(32) | `null` | Decorative motif selector |

### `homepage_section_item_translations`

| Column | Type | Default | Used for |
|---|---|---|---|
| `date_label` | string | `null` | Display-only date |
| `cta_label` | string | `null` | Display-only CTA text |
| `caption` | text | `null` | Localized caption |
| `unit` | string(64) | `null` | Localized unit suffix |

No existing column is changed or removed. `down()` drops the four item-translation columns, the three item columns and the four section-translation columns in that order.

---

## 4. Theme schema addition

`users.theme` is a nullable string of length 16. Application validation limits values to `light`, `dark` and `system`; `system` is stored as `null`. No auth or RBAC column is changed.

Validation is in `UpdateProfileRequest`; writes are in `AccountProfileService`; the shared prop is in `HandleInertiaRequests`. Missing `theme` leaves the existing value unchanged.

---

## 5. Seeder behavior

`HomepagePrototypeContentSeeder`:

- seeds the nine canonical sections in prototype order;
- disables legacy-only sections (`intro`, `areas`, `framework`, `targets`, `community`, `partners`, `knowledge`, `cta`) instead of deleting them;
- uses `display_value` for display strings;
- does not overwrite existing translations;
- flushes all homepage cache variants after seeding;
- preserves the planning caveat verbatim: `مستهدفات تخطيطية أولية لعام 2027، تخضع للمراجعة بعد تحديد التمويل المتاح والقدرات التنفيذية.`

The seeder is idempotent and is exercised by `PrototypeHomepageTest`.

---

## 6. Cache invalidation

`HomepageService` caches locale-specific combinations of:

- enabled-only vs all sections;
- published-only vs all-status sections.

`flush()` iterates all configured locales and both boolean dimensions, clearing every variant. All homepage mutations—update, reorder, status, toggle, store, destroy and seeding—flush the cache.

---

## 7. Rollback plan

1. Stop new homepage writes and theme profile writes.
2. Run migrations in reverse order under maintenance conditions.
3. `100000` down drops `users.theme` only if present.
4. `090000` down drops the eleven additive homepage columns.
5. `050331` down is a guarded no-op if the column was already removed.
6. Flush application and view caches.
7. Re-run feature tests against a fresh database.

Because no existing column or row is removed, rollback does not require data reconstruction beyond the additive fields. Theme preferences are lost on rollback by design; they are optional user preferences, not governance or content data.

---

## 8. Pre-commit checks

- `php -l` on every changed PHP file.
- `vendor/bin/pint --dirty --format agent`.
- `vendor/bin/phpunit` feature suite, including `RefreshDatabase` coverage.
- `node --test tests/homepage-content.test.mjs`.
- `npm run build`.
- Verify pinned hashes:
  - `resources/css/pcdr-home.css` → `d555d276d7abbce07004fc4f73c54e4a104a5e0f9bffa26ff47df71e299dc9c9`
  - `resources/views/app.blade.php` → `2a7affa19d09acda482e3d3dbce2ef97d374c259eaaa677848145c24ba871765`
  - `resources/js/Components/LanguageSwitcher.vue` → `29f9798faee179ba36da521bd69d0e28badc73e33dcc6f51420f9215f4471537`
- `git diff --check`.
- Stage only intended files; never `git add .`; never push.

---

## 9. Residual decisions

- Keep both guarded theme migrations in chronological order; do not collapse or delete preserved user work without an explicit decision.
- No approval permission, officeholder, delegation, ADR acceptance or Phase 6 transition may be added through migrations.
- `/partners` vs `/partnerships` canonicalization is a route/SEO decision, not a database migration.
