# PCDR Pro — Database Seeding Audit

> Read-only audit of `/root/PCDR/pcdr-pro` on `main`. Migrations are the source of truth for structure; Eloquent models are the source of truth for application-level behavior. This audit covers **31 migration files** and **141 migration-created tables** (136 explicit tables plus 5 loop-created media pivots). No schema changes are proposed for the demo dataset: demo ownership is recorded in the existing private `settings` table.
>
> In the table inventory below, **“Demo: yes” means the table receives internally tracked demo rows**, **“reference” means it is populated by existing non-demo seeders**, and **“no” means it is not populated by the dataset**. These labels do **not** mean public content is visibly marked as demo data. Public-facing demo content is written as natural institutional copy; ownership is internal only.

## Complete table inventory

**Scope.** 31 migration files under `database/migrations` (one flat directory — **no nested migration directories exist**), 136 explicitly named tables plus **5 loop-created media pivots** = **141 migration-created tables**. All Eloquent models under `app/Models` (including `Concerns/` and `Phase5/`), all enums, casts, scopes, boot hooks, observers/events, and relevant providers/listeners were inspected. No files were changed; no state-changing Artisan/test commands were run.

**Notation used below**
- `col: type` = required (NOT NULL); `?col: type` = nullable; `=value` = default; `id` = unsigned bigint auto-increment PK.
- `string` = `varchar(255)` unless a length is shown. `bigint` = unsigned bigint.
- `U(...)` = unique constraint; `I(...)` = non-unique index; `FK col → table.col ON DELETE action`.
- `timestamps` means nullable `created_at`/`updated_at`.
- Model column: relationships, casts, scopes, boot/event behavior. Shared traits are described once near the end and referenced.
- “Demo” = actual rows created by the implemented dataset; “reference” = rows created by existing technical seeders; counts reflect the static seeder review, not a runtime query.

---

## A. Laravel framework/runtime tables

### `users`
- **Purpose:** authentication, account status, access, locale/theme, avatar, lockout.
- **Columns:** `id`; `name: string`; `email: string`; `?email_verified_at: timestamp`; `password: string`; `?remember_token: string(100)`; `?created_at`, `?updated_at`; `status: string(24)='active'`; `locale: string(8)='ar'`; `?theme: string(16)='system'`; `is_platform_owner: boolean=false`; `?avatar_media_id: bigint`; `?last_login_at: timestamp`; `?last_activity_at: timestamp`; `?previous_login_at: timestamp`; `failed_login_attempts: uint=0`; `?locked_until: timestamp`; `?deleted_at: timestamp`.
- **Keys:** `U(email)`; `I(status, deleted_at)`; `FK avatar_media_id → media.id ON DELETE SET NULL`. (`public_reference` is on `sessions`, not users.)
- **Model:** `App\Models\User` — `SoftDeletes`, `Notifiable`, `HasFactory`, `MustVerifyEmail`; casts `email_verified_at/last_login_at/previous_login_at/locked_until/last_activity_at`→datetime, `password`→hashed, `is_platform_owner`→bool, `failed_login_attempts`→integer, `avatar_media_id`→integer. Relations: `roles()` belongsToMany `Role` via `role_user` with pivot `assigned_by`, `expires_at`, timestamps, **and filters expired assignments**; `auditLogs()` hasMany `AuditLog` by `actor_id`; `avatar()` belongsTo `Media`; `emailChanges()` hasMany `UserEmailChange`. Helpers: `isActive()`, `isPlatformOwner()`, `avatarUrl()` (30-minute signed URL), `toAccessArray()`, custom verification/reset notifications. No scopes/boot hooks.
- **Demo:** yes — **8** (owner, administrator, editor, publisher, contact manager, support officer, complaints officer, suspended reviewer).

### `password_reset_tokens`
- **Purpose:** Laravel password-reset broker tokens.
- **Columns:** `email: string PK`; `token: string`; `?created_at: timestamp`.
- **Keys:** PK `email`. No FKs/indexes beyond PK.
- **Model:** none (framework-managed).
- **Demo:** no — **0**.

### `sessions`
- **Purpose:** database session storage plus public session reference for account session management.
- **Columns:** `id: string PK`; `?user_id: bigint` (**indexed, no FK constraint**); `?ip_address: string(45)`; `?user_agent: text`; `payload: longtext`; `last_activity: integer`; `?public_reference: string(32)`; `?created_at: timestamp`.
- **Keys:** `I(user_id)`; `I(last_activity)`; `U(public_reference)`.
- **Model:** none (Laravel session driver / `SessionInvalidationService` uses DB/session APIs).
- **Demo:** yes — **4** active sessions for account-security screens.

### `cache`
- **Purpose:** database cache store.
- **Columns:** `key: string PK`; `value: mediumtext`; `expiration: bigint`.
- **Keys:** `I(expiration)`.
- **Model:** none.
- **Demo:** no — **0**.

### `cache_locks`
- **Purpose:** database cache lock store.
- **Columns:** `key: string PK`; `owner: string`; `expiration: bigint`.
- **Keys:** `I(expiration)`.
- **Model:** none.
- **Demo:** no — **0**.

### `jobs`
- **Purpose:** queue jobs.
- **Columns:** `id`; `queue: string`; `payload: longtext`; `attempts: usmallint`; `?reserved_at: uint`; `available_at: uint`; `created_at: uint` (NOT NULL).
- **Keys:** `I(queue)`.
- **Model:** none.
- **Demo:** no — **0**.

### `job_batches`
- **Purpose:** queue batch tracking.
- **Columns:** `id: string PK`; `name: string`; `total_jobs: int`; `pending_jobs: int`; `failed_jobs: int`; `failed_job_ids: longtext`; `?options: mediumtext`; `?cancelled_at: int`; `created_at: int`; `?finished_at: int`.
- **Model:** none.
- **Demo:** no — **0**.

### `failed_jobs`
- **Purpose:** failed queue jobs.
- **Columns:** `id`; `uuid: string`; `connection: string`; `queue: string`; `payload: longtext`; `exception: longtext`; `failed_at: timestamp=now`.
- **Keys:** `U(uuid)`; `I(connection, queue, failed_at)`.
- **Model:** none.
- **Demo:** no — **0**.

---

## B. Access control, notifications, audit, settings

### `notifications`
- **Purpose:** Laravel database notifications (in-app channel).
- **Columns:** `id: ulid PK`; `type: string`; `notifiable_type: string` + `notifiable_id: bigint` (`morphs('notifiable')`); `data: text`; `?read_at: timestamp`; `?created_at`, `?updated_at`.
- **Keys:** `I(notifiable_type, notifiable_id)` from `morphs`. No FK (polymorphic).
- **Model:** **none in `app/Models`**; used by `Notifiable` on `User` and custom notification classes. **Polymorphic table.**
- **Demo:** yes — **10**.

### `roles`
- **Purpose:** RBAC role definitions.
- **Columns:** `id`; `key: string(120)`; `name: string(160)`; `?description: text`; `is_system: boolean=false`; `?created_at`, `?updated_at`; `?deleted_at: timestamp`.
- **Keys:** `U(key)`.
- **Model:** `App\Models\Role` — `SoftDeletes`; cast `is_system`→bool; relations `users()` belongsToMany via `role_user` (pivot `assigned_by`, `expires_at`, timestamps; **no expiry filter**, unlike `User::roles()`), `permissions()` belongsToMany via `permission_role`, `scopes()` hasMany `RoleScope`.
- **Demo:** reference — **5** roles from `CmsRolesSeeder` (no separate owner role).

### `permissions`
- **Purpose:** code-owned permission catalog synced by `pcdr:permissions:sync`.
- **Columns:** `id`; `key: string(160)`; `name: string(160)`; `module: string(80)`; `?description: text`; `is_protected: boolean=false`; `is_retired: boolean=false`; `?created_at`, `?updated_at`.
- **Keys:** `U(key)`; `I(module)`.
- **Model:** `App\Models\Permission` — casts `is_protected`, `is_retired`→bool; `roles()` belongsToMany via `permission_role`.
- **Demo:** reference — **74** catalog permissions from `pcdr:permissions:sync`.

### `role_user`
- **Purpose:** **pivot** assigning roles to users with optional expiry and assigner.
- **Columns:** `id`; `user_id: bigint`; `role_id: bigint`; `?assigned_by: bigint`; `?expires_at: timestamp`; `?created_at`, `?updated_at`.
- **Keys:** `U(user_id, role_id)`; `I(role_id, expires_at)`; `FK user_id → users.id CASCADE`; `FK role_id → roles.id CASCADE`; `FK assigned_by → users.id SET NULL`.
- **Model:** none (pivot accessed through `User::roles()` / `Role::users()`).
- **Demo:** yes — **9** role assignments.

### `permission_role`
- **Purpose:** **pivot** assigning permissions to roles.
- **Columns:** `id`; `permission_id: bigint`; `role_id: bigint`; `?created_at`, `?updated_at`.
- **Keys:** `U(permission_id, role_id)`; FKs to `permissions`/`roles` `CASCADE`.
- **Model:** none (pivot).
- **Demo:** reference — **116** role-permission assignments.

### `role_scopes`
- **Purpose:** role resource-scope definitions (all/self/assigned with hashed JSON value).
- **Columns:** `id`; `role_id: bigint`; `resource_key: string(120)`; `scope_type: string(40)`; `?scope_value: json`; `scope_value_hash: string(64)`; `?created_at`, `?updated_at`.
- **Keys:** `U(role_id, resource_key, scope_type, scope_value_hash)` named `role_scope_unique`; `I(resource_key, scope_type)`; `FK role_id → roles.id CASCADE`.
- **Model:** `App\Models\RoleScope` — cast `scope_value`→array; `role()` belongsTo `Role`; **booted `saving` hook** sets `scope_value_hash = ScopeValue::hash(scope_value ?? [])`; constants `TYPE_ALL/SELF/ASSIGNED`.
- **Demo:** yes — **6**.

### `audit_logs`
- **Purpose:** immutable audit event log with archival lifecycle metadata.
- **Columns:** `id`; `?actor_id: bigint`; `action: string(120)`; `subject_type: string(160)`; `?subject_id: string(160)`; `?before: json`; `?after: json`; `context: json`; `?request_id: string(64)`; `?archive_id: bigint`; `?archived_at: timestamp`; `created_at: timestamp` (NOT NULL). **No `updated_at`.**
- **Keys:** `I(subject_type, subject_id)`; `I(actor_id, created_at)`; `I(action)`; `I(created_at)`; `I(archive_id, created_at)`; `I(archived_at)`; `FK actor_id → users.id SET NULL`; `FK archive_id → audit_archives.id SET NULL`.
- **Model:** `App\Models\AuditLog` — `$timestamps = false`; casts before/after/context→array, dates→datetime; relations `actor()` and `archive()`. **Booted:** `updating` only allows `archive_id`/`archived_at` to change; `deleting` only allowed inside `withCleanupDeletion()` token scope. **Pseudo-polymorphic** subject (`subject_type`+`subject_id`), no DB FK.
- **Demo:** yes — **40** tracked audit rows (plus **1** reference audit row from permission sync).

### `settings`
- **Purpose:** administrator-managed key/value settings (non-public website config lives in `website_settings`).
- **Columns:** `id`; `group: string(80)`; `key: string(160)`; `type: string(32)`; `?value: text`; `is_public: boolean=false`; `is_localized: boolean=false`; `is_secret_reference: boolean=false`; `?created_at`, `?updated_at`.
- **Keys:** `U(group, key)`.
- **Model:** `App\Models\Setting` — `$guarded=[]`; casts three booleans; `translations()` hasMany `SettingTranslation`.
- **Demo:** yes — **4** application settings plus **2,490** private ownership markers in the same `settings` table (see the seeding guide).

### `setting_translations`
- **Purpose:** localized values for `settings`.
- **Columns:** `id`; `setting_id: bigint`; `locale: string(8)`; `?value: text`; `?created_at`, `?updated_at`.
- **Keys:** `U(setting_id, locale)`; `FK setting_id → settings.id CASCADE`.
- **Model:** `App\Models\SettingTranslation` — `$guarded=[]`; `setting()` belongsTo `Setting`.
- **Demo:** yes — **8** localized values.

---

## C. Media & website settings (2026_11_23_000100)

### `media`
- **Purpose:** central media library, public and restricted files.
- **Columns:** `id`; `uuid: ulid`; `disk: string`; `path: string`; `type: string(24)`; `mime_type: string(128)`; `extension: string(16)`; `size_bytes: uint`; `?width: uint`; `?height: uint`; `checksum: string(64)`; `original_name: string`; `is_restricted: boolean=false`; `?uploaded_by: bigint`; `?deleted_at: timestamp`; `?created_at`, `?updated_at`.
- **Keys:** `U(uuid)`; `I(type, is_restricted)`; `I(mime_type)`; `FK uploaded_by → users.id SET NULL`.
- **Model:** `App\Models\Media` — `SoftDeletes`, `HasFactory`, `HasTranslations`; casts `type`→`MediaType`, booleans/integers; scopes `images()`, `documents()`, `public()`, `restricted()`; relations `translations()` (trait), `uploader()` belongsTo `User`; helpers `isImage()`, `url()` (signed route for private disk), `humanSize()`.
- **Demo:** yes — **17** locally sourced, licensed JPEG images (no restricted documents).

### `media_translations`
- **Purpose:** alt text/caption per media locale.
- **Columns:** `id`; `media_id: bigint`; `locale: string(8)`; `?alt_text: string`; `?caption: text`; `?created_at`, `?updated_at`.
- **Keys:** `U(media_id, locale)`; `FK media_id → media.id CASCADE`.
- **Model:** `App\Models\MediaTranslation` — `media()` belongsTo `Media`.
- **Demo:** yes — **34** alt-text/caption pairs.

### `website_settings`
- **Purpose:** public website configuration (site identity, contact, social links, SEO, etc.).
- **Columns:** `id`; `group: string(48)`; `key: string(96)`; `type: string(32)='string'`; `is_localized: boolean=false`; `is_public: boolean=false`; `?value: text`; `?created_at`, `?updated_at`.
- **Keys:** `U(key)`; `I(group)`.
- **Model:** `App\Models\WebsiteSetting` — `HasTranslations`; casts booleans; scope `public()`; `resolvedValue()` uses translated value with fallback to base value.
- **Demo:** yes — **24** reference rows from `SiteSettingsSeeder`; **1** supplementary demo-owned row (`contact.office_hours`) created only when absent.

### `website_setting_translations`
- **Purpose:** localized website setting values.
- **Columns:** `id`; `website_setting_id: bigint`; `locale: string(8)`; `?value: text`; `?created_at`, `?updated_at`.
- **Keys:** `U(website_setting_id, locale)`; `FK website_setting_id → website_settings.id CASCADE`.
- **Model:** `App\Models\WebsiteSettingTranslation` — `setting()` belongsTo `WebsiteSetting`.
- **Demo:** yes — **8** reference translations plus **2** demo-owned translations for `contact.office_hours`.

---

## D. CMS pages, pillars, revisions (2026_11_23_000200)

### `pages`
- **Purpose:** localized CMS pages with hierarchy and workflow.
- **Columns:** `id`; `?parent_id: bigint`; `status: string(32)='draft'`; `?published_at: timestamp`; `?author_id: bigint`; `?reviewer_id: bigint`; `display_order: uint=0`; `is_system: boolean=false`; `?deleted_at: timestamp`; `?created_at`, `?updated_at`.
- **Keys:** `I(status, published_at)`; FKs `parent_id → pages.id SET NULL`, `author_id/reviewer_id → users.id SET NULL`.
- **Model:** `App\Models\Page` — `SoftDeletes`, `HasTranslations`, `Publishable`; casts status→`ContentStatus`, `published_at`→datetime, booleans/integers; relations `parent()`, `children()`, `author()`, `media()` belongsToMany via `page_media`, `revisions()` **MorphMany** to `ContentRevision`. No `reviewer()` relation (mismatch: column exists).
- **Demo:** yes — **4** (about, transparency, privacy, one child page).

### `page_translations`
- **Purpose:** page translation records with slugs and SEO.
- **Columns:** `id`; `page_id: bigint`; `locale: string(8)`; `slug: string`; `title: string`; `?summary: text`; `?body: longtext`; `?seo_title: string`; `?seo_description: text`; `?canonical_url: string`; `?og_image: string`; `robots: string(32)='index,follow'`; `?created_at`, `?updated_at`.
- **Keys:** `U(page_id, locale)`; `U(locale, slug)`; `FK page_id → pages.id CASCADE`.
- **Model:** `App\Models\PageTranslation` — `page()` belongsTo `Page`.
- **Demo:** yes — **8**.

### `strategic_pillars`
- **Purpose:** strategic pillars/reference taxonomy for programs, projects, articles, publications.
- **Columns:** `id`; `code: string(4)`; `status: string(32)='draft'`; `?published_at: timestamp`; `?featured_image_id: bigint`; `display_order: uint=0`; `is_featured: boolean=false`; `?deleted_at: timestamp`; `?created_at`, `?updated_at`.
- **Keys:** `U(code)`; `I(status, published_at)`; `FK featured_image_id → media.id SET NULL`.
- **Model:** `App\Models\StrategicPillar` — `SoftDeletes`, `HasTranslations`, `Publishable`; casts status→`ContentStatus`, date/bool/int; scopes `search()` (code + translated fields, escaped LIKE) and `workflowStatus()`; relations `featuredImage()`, `media()` via `pillar_media`, `programs()`, `projects()`, `articles()`, `publications()`; helpers `objectiveLines()`, `targetGroupLines()`.
- **Demo:** reference — **11** pillars from the website seeders; demo attaches media but creates no pillars.

### `strategic_pillar_translations`
- **Purpose:** pillar localized copy/SEO.
- **Columns:** `id`; `strategic_pillar_id: bigint`; `locale: string(8)`; `slug: string`; `title: string`; `?short_title: string`; `?description: text`; `?objectives: text`; `?target_groups: text`; `?seo_title: string`; `?seo_description: text`; `?created_at`, `?updated_at`.
- **Keys:** `U(strategic_pillar_id, locale)`; `U(locale, slug)`; FK → `strategic_pillars.id CASCADE`.
- **Model:** `App\Models\StrategicPillarTranslation` — `pillar()` belongsTo.
- **Demo:** reference — **22** pillar translations from the website seeders.

### `content_revisions`
- **Purpose:** revision snapshots for polymorphic content entities.
- **Columns:** `id`; `revisionable_type: string`; `revisionable_id: bigint`; `locale: string(8)`; `?title: string`; `?status: string(32)`; `payload: json`; `?actor_id: bigint`; `?note: text`; `?created_at: timestamp`.
- **Keys:** `I(revisionable_type, revisionable_id)`; FK `actor_id → users.id SET NULL`. **Polymorphic, no FK to subject.**
- **Model:** `App\Models\ContentRevision` — `$timestamps=false`; casts `payload`→array, `created_at`→datetime; `revisionable()` **MorphTo**, `actor()` belongsTo `User`. Written from `Cms/ContentController`, `Concerns/ManagesContent`, `TransitionContentStatus`.
- **Demo:** yes — **20**.

---

## E. Editorial content (2026_11_23_000300)

### `programs`
- **Purpose:** programs under pillars; later extended with budgets, codes, managers.
- **Columns:** `id`; `?code: string(64)`; `?strategic_pillar_id: bigint`; `?program_manager_id: bigint`; `?responsible_unit: string(255)`; `?category: string(64)`; `?proposed_budget: decimal(18,2)`; `?approved_budget: decimal(18,2)`; `?budget_currency: string(3)`; `status: string(32)='draft'`; `?published_at: timestamp`; `?start_date: date`; `?end_date: date`; `?actual_start_date: date`; `?actual_end_date: date`; `?featured_image_id: bigint`; `display_order: uint=0`; `?deleted_at: timestamp`; `?created_at`, `?updated_at`.
- **Keys:** `U(code)`; `I(status, published_at)`; FKs pillar/media/users `SET NULL`.
- **Model:** `App\Models\Program` — `SoftDeletes`, `HasTranslations`, `Publishable`; casts status→`ContentStatus`, start/end dates, published_at datetime; relations `pillar()`, `projects()`, `featuredImage()`. **Mismatch:** phase-4 fields (`code`, manager, unit, category, budgets, currency, actual dates) are **not in `$fillable` and have no casts**.
- **Demo:** yes — **6**.

### `program_translations`
- **Purpose:** program localized fields/SEO.
- **Columns:** `id`; `program_id`; `locale`; `slug`; `title`; `?summary`, `?description`, `?objectives`, `?target_groups`, `?geographic_coverage`, `?seo_title: string`, `?seo_description: text`; timestamps.
- **Keys:** `U(program_id, locale)`; `U(locale, slug)`; FK program CASCADE.
- **Model:** `App\Models\ProgramTranslation` — `program()` belongsTo.
- **Demo:** yes — **12**.

### `projects`
- **Purpose:** projects with separate delivery status (`status`) and publication workflow (`workflow_status`); phase-4 budget/manager fields.
- **Columns:** `id`; `?code: string(64)`; `?program_id`; `?strategic_pillar_id`; `?project_manager_id`; `?responsible_team: string(255)`; `?proposed_budget`, `?approved_budget: decimal(18,2)`; `?budget_currency: string(3)`; `status: string(32)='planned'`; `workflow_status: string(32)='draft'`; `?published_at: timestamp`; `?start_date`, `?end_date`, `?actual_start_date`, `?actual_end_date: date`; `?featured_image_id`; `display_order: uint=0`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`; `I(workflow_status, published_at)`; `I(status)`; FKs program/pillar/manager/media `SET NULL`.
- **Model:** `App\Models\Project` — `SoftDeletes`, `HasTranslations`; casts `status`→`ProjectStatus`, `workflow_status`→`ContentStatus`, dates; **custom** `published()`/`visibleToPublic()`/`isPublished()` based on `workflow_status` (not the shared Publishable trait); relations `program()`, `pillar()`, `featuredImage()`, `media()` via `project_media`; `statusLabel()`. **Mismatch:** phase-4 fields not fillable/cast.
- **Demo:** yes — **8**.

### `project_translations`
- **Purpose:** project localized fields/SEO.
- **Columns:** same shape as program translations: `id`, `project_id`, `locale`, `slug`, `title`, nullable summary/description/objectives/target_groups/geographic_coverage/seo_title/seo_description, timestamps.
- **Keys:** `U(project_id, locale)`, `U(locale, slug)`, FK project CASCADE.
- **Model:** `App\Models\ProjectTranslation` — `project()` belongsTo.
- **Demo:** yes — **16**.

### `article_categories`
- **Purpose:** news/article categories.
- **Columns:** `id`; `slug: string`; `display_order: uint=0`; timestamps.
- **Keys:** `U(slug)`.
- **Model:** `App\Models\ArticleCategory` — `HasTranslations`; `articles()` hasMany.
- **Demo:** yes — **4**.

### `article_category_translations`
- **Columns:** `id`, `article_category_id`, `locale`, `name: string`, `?description: text`, timestamps.
- **Keys:** `U(article_category_id, locale)`, FK category CASCADE.
- **Model:** `App\Models\ArticleCategoryTranslation` — `category()` belongsTo.
- **Demo:** yes — **8**.

### `articles`
- **Purpose:** news/activity articles.
- **Columns:** `id`; `?article_category_id`; `?strategic_pillar_id`; `?author_id`; `status: string(32)='draft'`; `?published_at: timestamp`; `?event_date: date`; `is_featured: boolean=false`; `?cover_media_id`; `display_order: uint=0`; `?deleted_at`; timestamps.
- **Keys:** `I(status, published_at)`; FKs category/pillar/author/media `SET NULL`.
- **Model:** `App\Models\Article` — `SoftDeletes`, `HasTranslations`, `Publishable`; casts status→`ContentStatus`, published_at/event_date, `is_featured`; relations `category()`, `pillar()`, `author()`, `cover()`, `tags()` via `article_tag`, `media()` via `article_media`, `revisions()` **MorphMany**.
- **Demo:** yes — **12**.

### `article_translations`
- **Columns:** `id`, `article_id`, `locale`, `slug`, `title`, `?excerpt`, `?body`, `?seo_title`, `?seo_description`, timestamps.
- **Keys:** `U(article_id, locale)`, `U(locale, slug)`, FK article CASCADE.
- **Model:** `App\Models\ArticleTranslation` — `article()` belongsTo.
- **Demo:** yes — **24**.

### `tags`
- **Columns:** `id`; `slug: string`; timestamps.
- **Keys:** `U(slug)`.
- **Model:** `App\Models\Tag` — `HasTranslations`; `articles()` belongsToMany via `article_tag`.
- **Demo:** yes — **8**.

### `tag_translations`
- **Columns:** `id`, `tag_id`, `locale`, `name: string`, timestamps.
- **Keys:** `U(tag_id, locale)`, FK tag CASCADE.
- **Model:** `App\Models\TagTranslation` — `tag()` belongsTo.
- **Demo:** yes — **16**.

### `article_tag`
- **Purpose:** **pivot** article↔tag.
- **Columns:** `article_id: bigint`, `tag_id: bigint`.
- **Keys:** composite `PK(article_id, tag_id)`; FKs both CASCADE. No timestamps/surrogate id.
- **Model:** none (pivot).
- **Demo:** yes — **20**.

### `publications`
- **Purpose:** reports/publications with restricted-document flag.
- **Columns:** `id`; `category: string(48)`; `status: string(32)='draft'`; `?published_at: timestamp`; `?publication_date: date`; `?strategic_pillar_id`; `?document_media_id`; `?cover_media_id`; `?version: string(32)`; `is_restricted: boolean=false`; `display_order: uint=0`; `?deleted_at`; timestamps.
- **Keys:** `I(status, published_at)`, `I(category)`; FKs pillar/media `SET NULL`.
- **Model:** `App\Models\Publication` — `SoftDeletes`, `HasTranslations`, `Publishable`; casts status→`ContentStatus`, dates, bool; scope `category()`; relations `pillar()`, `document()`, `cover()`, `media()` via `publication_media`; `categoryLabel()`.
- **Demo:** yes — **8**.

### `publication_translations`
- **Columns:** `id`, `publication_id`, `locale`, `slug`, `title`, `?description`, `?seo_title`, `?seo_description`, timestamps.
- **Keys:** `U(publication_id, locale)`, `U(locale, slug)`, FK publication CASCADE.
- **Model:** `App\Models\PublicationTranslation` — `publication()` belongsTo.
- **Demo:** yes — **16**.

### `page_media`, `pillar_media`, `project_media`, `article_media`, `publication_media`
- **Purpose:** five **loop-created pivots** attaching gallery/usage media to each content type.
- **Columns (each):** owner FK (`page_id`/`strategic_pillar_id`/`project_id`/`article_id`/`publication_id`), `media_id: bigint`, `usage: string(32)='gallery'`, `sort_order: uint=0`, timestamps.
- **Keys:** `U(owner, media_id, usage)`; owner FK CASCADE; `FK media_id → media.id RESTRICT`.
- **Model:** none dedicated; used via `belongsToMany(...)->withPivot('usage','sort_order')` on `Page`, `StrategicPillar`, `Project`, `Article`, `Publication`.
- **Demo:** yes — `page_media` **6**, `pillar_media` **6**, `project_media` **8**, `article_media` **12**, `publication_media` **8**.

---

## F. Navigation, homepage, inquiries (2026_11_23_000400 + homepage migrations)

### `navigation_menus`
- **Columns:** `id`; `key: string(48)`; `label: string`; timestamps.
- **Keys:** `U(key)`.
- **Model:** `App\Models\NavigationMenu` — `items()` hasMany `NavigationItem` ordered by `display_order`.
- **Demo:** reference — **2** menus (`primary`, `footer`) from homepage seeders.

### `navigation_items`
- **Purpose:** menu entries, internal route keys or external URLs, hierarchical.
- **Columns:** `id`; `navigation_menu_id`; `?parent_id`; `link_type: string(24)`; `link_value: string`; `is_enabled: boolean=true`; `display_order: uint=0`; `?icon: string(48)`; timestamps.
- **Keys:** `I(navigation_menu_id, is_enabled, display_order)`; FKs menu CASCADE, parent CASCADE.
- **Model:** `App\Models\NavigationItem` — `HasTranslations`; casts bool/int; relations `menu()`, `children()`; `resolvedUrl()` maps internal keys to localized routes and only permits `https://` external URLs.
- **Demo:** reference — **20** navigation items from homepage seeders.

### `navigation_item_translations`
- **Columns:** `id`, `navigation_item_id`, `locale`, `label: string`, timestamps.
- **Keys:** `U(navigation_item_id, locale)`, FK item CASCADE.
- **Model:** `App\Models\NavigationItemTranslation` — `item()` belongsTo.
- **Demo:** reference — **40** navigation item translations from homepage seeders.

### `homepage_sections`
- **Purpose:** configurable homepage sections.
- **Columns:** `id`; `key: string(64)`; `type: string(48)`; `status: string(32)='published'`; `?published_at: timestamp`; `is_enabled: boolean=true`; `display_order: uint=0`; timestamps.
- **Keys:** `U(key)`; `I(status, is_enabled, display_order)` (`homepage_sections_publication_index`).
- **Model:** `App\Models\HomepageSection` — `HasTranslations`, `Publishable`; casts status→`ContentStatus`, published_at datetime, bool/int; overrides `t()` to return `mixed` for JSON tab labels; `items()` hasMany ordered by `sort_order`.
- **Demo:** reference — **17** sections from the homepage seeders.

### `homepage_section_translations`
- **Purpose:** section copy including prototype fields.
- **Columns:** `id`; `homepage_section_id`; `locale`; `?eyebrow`, `?title: string`, `?body: text`, `?cta_label`, `?cta_link`; `?ticker_label: string`; `?tab_labels: json`; `?video_url`, `?video_caption: string`; timestamps.
- **Keys:** `U(homepage_section_id, locale)`; FK section CASCADE.
- **Model:** `App\Models\HomepageSectionTranslation` — casts `tab_labels`→array; `section()` belongsTo.
- **Demo:** reference — **34** section translations.

### `homepage_section_items`
- **Purpose:** section entries, either references to published entities or inline content; optional media.
- **Columns:** `id`; `homepage_section_id`; `item_type: string(32)`; `?item_id: bigint` (**no FK** — polymorphic reference); `?media_id: bigint`; `?item_key: string(64)`; `is_published: boolean=true`; `?numeric_value: decimal(20,4)`; `?tab: string(32)`; `is_featured: boolean=false`; `?motif_key: string(32)`; `sort_order: uint=0`; timestamps.
- **Keys:** `I(homepage_section_id, sort_order)`; `U(homepage_section_id, item_key)`; FK section CASCADE; `FK media_id → media.id RESTRICT`.
- **Model:** `App\Models\HomepageSectionItem` — `HasTranslations`; casts tab/motif strings, booleans, `numeric_value`→`decimal:4`, sort int; scope `published()`; relations `section()`, `media()`; helpers `isInline()`, `resolve()` (published `pillar`/`article`/`publication`/`page`/`project` lookup).
- **Demo:** **15** demo-owned items plus **149** reference items.

### `homepage_section_item_translations`
- **Purpose:** inline item labels/copy/values.
- **Columns:** `id`; `homepage_section_item_id`; `locale`; `?label`, `?title`, `?display_value`, `?url`, `?date_label`, `?cta_label`, `?unit: string`; `?body`, `?caption: text`; timestamps.
- **Keys:** `U(homepage_section_item_id, locale)`; FK item CASCADE.
- **Model:** `App\Models\HomepageSectionItemTranslation` — no casts/relations declared (only `$fillable`).
- **Demo:** **30** demo-owned item translations plus **298** reference translations.

### `contact_messages`
- **Purpose:** public contact submissions.
- **Columns:** `id`; `reference: ulid`; `category: string(48)`; `name`, `email: string`; `?subject: string`; `message: text`; `locale: string(8)`; `consent: boolean=false`; `?ip_hash: string(64)`; `?user_agent: string(255)`; `status: string(24)='new'`; `?assigned_to: bigint` (**indexed, no FK**); timestamps.
- **Keys:** `U(reference)`; `I(assigned_to)`; `I(category)` on `category`.
- **Model:** `App\Models\ContactMessage` — cast `consent`→bool; `assignee()` belongsTo `User`.
- **Demo:** yes — **6**.

### `volunteer_inquiries`
- **Purpose:** volunteer expressions of interest.
- **Columns:** `id`; `reference: ulid`; `name`, `email`; `?phone: string`; `area_of_interest: string(96)`; `availability: string(48)`; `?message: text`; `locale: string(8)`; `consent: boolean=false`; `?ip_hash: string(64)`; `status: string(24)='new'`; `?assigned_to: bigint` (indexed, no FK); timestamps.
- **Keys:** `U(reference)`, `I(assigned_to)`.
- **Model:** `App\Models\VolunteerInquiry` — `consent` bool; `assignee()` belongsTo `User`.
- **Demo:** yes — **4**.

### `partnership_inquiries`
- **Purpose:** partnership inquiries.
- **Columns:** `id`; `reference: ulid`; `organization`, `contact_name`, `email: string`; `?website: string`; `interest: string(96)`; `message: text`; `locale: string(8)`; `consent: boolean=false`; `?ip_hash: string(64)`; `status: string(24)='new'`; `?assigned_to: bigint` (indexed, no FK); timestamps.
- **Keys:** `U(reference)`, `I(assigned_to)`.
- **Model:** `App\Models\PartnershipInquiry` — `consent` bool; `assignee()` belongsTo `User`.
- **Demo:** yes — **4**.

---

## G. Audit archive lifecycle (2026_11_24_055404)

### `audit_archives`
- **Purpose:** monthly audit-log archive manifests/verification state.
- **Columns:** `id`; `period_key: string(7)`; `name: string(120)`; `period_start: timestamp`; `period_end: timestamp` (exclusive); `cutoff_at: timestamp`; `record_count: uint=0`; `file_path: string(255)`; `?file_size: bigint`; `?checksums: json`; `?manifest: json`; `status: string(20)='pending'`; `schema_version: string(20)='1.0'`; `?error: text`; `attempts: usmallint=1`; `?initiated_by: bigint`; `?verified_at`, `?pruned_at`, `?last_cleanup_at: timestamp`; timestamps.
- **Keys:** `U(period_key)`; `I(status, period_start)`; `FK initiated_by → users.id SET NULL`.
- **Model:** `App\Models\AuditArchive` — `HasFactory`; casts dates, JSON, integers; relations `logs()` hasMany `AuditLog`, `cleanupRuns()` hasMany `AuditCleanupRun`, `initiator()` belongsTo `User`; constants `PENDING/PROCESSING/VERIFIED/FAILED/PRUNED`; helpers `isVerified()`, `periodLabel()`.
- **Demo:** yes — **3** (using `AuditArchiveFactory`, including failed/pruned variants).

### `audit_cleanup_runs`
- **Purpose:** dry-run/execute cleanup audit trail.
- **Columns:** `id`; `?audit_archive_id: bigint`; `status: string(20)='processing'`; `mode: string(20)='execute'`; `?period_start`, `?period_end: timestamp`; `?requested_by: bigint`; `eligible_count`, `deleted_count`, `skipped_hold_count`, `skipped_unverified_count: uint=0`; `?estimated_bytes: bigint`; `?report: json`; `?error: text`; `?started_at`, `?finished_at: timestamp`; timestamps.
- **Keys:** `I(audit_archive_id, status)`; FKs archive/users `SET NULL`.
- **Model:** `App\Models\AuditCleanupRun` — `HasFactory`; casts dates/JSON/integers; relations `archive()`, `requestedBy()`; constants `PROCESSING/COMPLETED/FAILED`, `DRY_RUN/EXECUTE`.
- **Demo:** yes — **4**.

### `audit_log_holds`
- **Purpose:** temporary/legal holds preventing audit cleanup.
- **Columns:** `id`; `label: string(160)`; `reason: text`; `scope_type: string(40)`; `?subject_type: string(160)`; `?subject_id: string(160)`; `?actor_id: bigint`; `?period_start`, `?period_end`, `?expires_at`, `?released_at: timestamp`; `?created_by`, `?released_by: bigint`; timestamps.
- **Keys:** `I(scope_type, expires_at)`; FKs actor/created_by/released_by → users `SET NULL`.
- **Model:** `App\Models\AuditLogHold` — `HasFactory`; casts dates/integers; `creator()` belongsTo (`created_by`); constants `GLOBAL/SUBJECT/ACTOR/PERIOD`. **Mismatch:** no relations for `actor_id` or `released_by`; no boot hook. Pseudo-polymorphic subject columns, no FK.
- **Demo:** yes — **3**.

---

## H. Phase 4 operational foundation (2026_11_24_000500)

> Many of these tables are served by the config-bound `App\Models\OperationalRecord` (`config/operations.php` defines 30 resources: locations, interventions, activities, distribution_plans/rounds/records, warehouses, inventory_items, volunteers, volunteer_teams, volunteer_assignments, volunteer_trainings, committees, committee_meetings, community_feedback, partners, partner_agreements, partner_contacts, grant_opportunities/proposals/awards, results_frameworks, reporting_periods, indicators, indicator_measurements, evaluations, corrective_actions, assessments, data_collection_instruments, operational_reports). Dedicated models exist only for inventory balances/movements, translations, document links and domain transitions.

### `operational_translations`
- **Purpose:** shared localized text for operational resources using a `resource` discriminator.
- **Columns:** `id`; `resource: string(64)`; `owner_id: bigint`; `locale: string(8)`; nullable `title`, `name`, `summary`, `description`, `objectives`, `target_groups`, `methodology`, `findings`, `recommendations`, `lessons_learned`, `conditions`, `deliverables`, `reporting_requirements`, `notes` (text/longtext as declared); timestamps.
- **Keys:** `U(resource, owner_id, locale)`; `I(resource, locale)`. **Pseudo-polymorphic, no FK.**
- **Model:** `App\Models\OperationalTranslation` — casts `owner_id`→integer; written through `OperationalTranslationSaver`; consumed via `HasOperationalTranslations` trait on `OperationalRecord`.
- **Demo:** yes — **40**.

### `domain_transitions`
- **Purpose:** append-only status history for operational resources.
- **Columns:** `id`; `resource: string(64)`; `owner_id: bigint`; `status_field: string(32)='status'`; `?from_status: string(64)`; `to_status: string(64)`; `?actor_id`; `?reason: text`; `?evidence_media_id`; `created_at: timestamp` (NOT NULL).
- **Keys:** `I(resource, owner_id)`, `I(resource, created_at)`; FKs actor/users and media `SET NULL`.
- **Model:** `App\Models\DomainTransition` — `$timestamps=false`; casts owner/actor ints and created_at datetime; `actor()` belongsTo; **booted hooks block `updating` and `deleting`** (append-only).
- **Demo:** yes — **59** append-only transitions.

### `document_links`
- **Purpose:** restricted document attachments for operational resources.
- **Columns:** `id`; `resource: string(64)`; `owner_id: bigint`; `media_id: bigint`; `usage: string(48)='document'`; `?uploaded_by: bigint`; `created_at: timestamp`.
- **Keys:** `U(resource, owner_id, media_id, usage)`; `I(resource, owner_id)`; `FK media_id → media.id RESTRICT`; `FK uploaded_by → users.id SET NULL`.
- **Model:** `App\Models\DocumentLink` — `$timestamps=false`; casts ints/date; relations `media()`, `uploader()`. Referenced by `Phase5\DocumentReview.document_link_id`.
- **Demo:** yes — **20**.

### `inventory_items`
- **Purpose:** item catalog for inventory ledger.
- **Columns:** `id`; `code: string(64)`; `category: string(64)`; `unit: string(32)`; `?sku: string(64)`; `min_stock_threshold: decimal(18,3)=0`; `status: string(32)='active'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `U(sku)`, `I(status, category)`.
- **Model:** `OperationalRecord::forResource('inventory_items')` (generic); no dedicated class.
- **Demo:** yes — **10**.

### `locations`
- **Purpose:** hierarchical geography/locations.
- **Columns:** `id`; `?parent_id`; `code: string(64)`; `location_type: string(48)`; `status: string(32)='active'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(status, location_type)`, `I(parent_id)`; FK parent → locations `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **8**.

### `interventions`
- **Purpose:** program/project interventions with planned/actual dates and reach.
- **Columns:** `id`; `code: string(64)`; `?program_id`, `?project_id`, `?location_id`; `category: string(64)`; `?planned_start/end`, `?actual_start/end: date`; `?planned_reach: bigint`; `verified_reach: bigint=0`; `?responsible_user_id`; `status: string(32)='draft'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(status, category)`, `I(program_id, project_id)`; FKs `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **6**.

### `activities`
- **Purpose:** activity/milestone execution tracking.
- **Columns:** `id`; `code`; `?project_id`, `?intervention_id`, `?location_id`; `activity_type: string(32)`; `?planned_start/end`, `?actual_start/end: date`; `progress_percent: utinyint=0`; `?owner_id`; `status: string(32)='draft'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(status, activity_type)`, `I(project_id, planned_start)`; FKs `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **10**.

### `distribution_plans`
- **Purpose:** assistance distribution plans.
- **Columns:** `id`; `code`; `?project_id`, `?intervention_id`, `?location_id`; `assistance_type: string(64)`; `?planned_date: date`; `?responsible_user_id`; `status: string(32)='planned'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(status, assistance_type)`; FKs `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **4**.

### `distribution_rounds`
- **Purpose:** scheduled rounds under a distribution plan.
- **Columns:** `id`; `plan_id`; `code`; `?scheduled_at: datetime`; `?location_id`, `?responsible_user_id`; `status: string(32)='planned'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(plan_id, status)`; FK plan CASCADE, others `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **8**.

### `distribution_records`
- **Purpose:** per-item quantities recorded/verified per distribution round.
- **Columns:** `id`; `round_id`; `code`; `?inventory_item_id`; `planned_quantity: decimal(18,3)`; `?recorded_quantity`, `?verified_quantity: decimal(18,3)`; `unit: string(32)`; `?evidence_media_id`; `?reconciliation_notes: text`; `status: string(32)='planned'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(round_id, status)`; FK round CASCADE, item/media `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **16**.

### `warehouses`
- **Purpose:** warehouse registry.
- **Columns:** `id`; `code`; `?location_id`, `?responsible_user_id`; `?capacity_note: string(500)`; `status: string(32)='active'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(status)`; FKs `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **3**.

### `inventory_balances`
- **Purpose:** current stock per warehouse/item.
- **Columns:** `id`; `warehouse_id`; `inventory_item_id`; `quantity`, `reserved_quantity`, `damaged_quantity`, `expired_quantity: decimal(18,3)=0`; `updated_at: timestamp` (NOT NULL). **No `created_at`.**
- **Keys:** `U(warehouse_id, inventory_item_id)`; FKs both CASCADE.
- **Model:** `App\Models\InventoryBalance` — `$timestamps=false`; decimal/datetime casts; no relationships declared.
- **Demo:** yes — **30**.

### `inventory_movements`
- **Purpose:** immutable inventory ledger with reversals and idempotency.
- **Columns:** `id`; `warehouse_id`; `?destination_warehouse_id`; `inventory_item_id`; `movement_type: string(32)`; `direction: string(16)`; `quantity`, `before_quantity`, `after_quantity: decimal(18,3)`; `?source_type: string(64)`, `?source_id: bigint`, `?source_reference: string(128)`; `?reason: string(1000)`; `?actor_id`; `?reversal_of`; `?idempotency_key: string(128)`; `?occurred_at: timestamp`; `created_at: timestamp`.
- **Keys:** `U(idempotency_key)`; `I(warehouse_id, inventory_item_id)`, `I(movement_type, created_at)`, `I(source_type, source_id)`; FKs warehouses/items RESTRICT, actor/reversal `SET NULL`.
- **Model:** `App\Models\InventoryMovement` — `$timestamps=false`; decimal/datetime/int casts; **booted hooks block updates/deletes**. Pseudo-polymorphic source columns.
- **Demo:** yes — **40**.

### `volunteers`
- **Purpose:** internal-reference-only volunteer records.
- **Columns:** `id`; `internal_reference: string(64)`; `?linked_user_id`, `?location_id`; `?skills: text`; `?availability: string(255)`; `status: string(32)='potential'`; `?deleted_at`; timestamps.
- **Keys:** `U(internal_reference)`; `I(status)`; FKs `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **12**.

### `volunteer_teams`
- **Columns:** `id`; `code`; `team_type: string(48)`; `?leader_volunteer_id`, `?location_id`; `?skills: text`; `status: string(32)='proposed'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(status, team_type)`; FKs `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **4**.

### `volunteer_team_members`
- **Purpose:** **pivot** team↔volunteer with role/status/join date.
- **Columns:** `id`; `team_id`; `volunteer_id`; `?member_role: string(120)`; `status: string(32)='active'`; `?joined_at: date`; timestamps.
- **Keys:** `U(team_id, volunteer_id)`; FKs both CASCADE.
- **Model:** **none** (pivot).
- **Demo:** yes — **12**.

### `volunteer_assignments`
- **Columns:** `id`; `?team_id`, `?volunteer_id`, `?activity_id`, `?distribution_round_id`; `?scheduled_at: datetime`; `?hours: decimal(6,2)`; `status: string(32)='planned'`; `?deleted_at`; timestamps.
- **Keys:** `I(status, scheduled_at)`; FKs `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **10**.

### `volunteer_trainings`
- **Columns:** `id`; `code`; `?provider: string(255)`; `?training_date: date`; `?hours: decimal(6,2)`; `?evidence_media_id`; `status: string(32)='planned'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(status)`; FK media `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **4**.

### `volunteer_training`
- **Purpose:** **pivot** volunteer↔training with completion.
- **Columns:** `id`; `volunteer_id`; `training_id`; `?completion_date: date`; `status: string(32)='planned'`; timestamps.
- **Keys:** `U(volunteer_id, training_id)`; FKs both CASCADE.
- **Model:** **none** (pivot).
- **Demo:** yes — **8**.

### `committees`
- **Columns:** `id`; `code`; `committee_type: string(32)`; `?location_id`; `status: string(32)='proposed'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(status, committee_type)`; FK location `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **3**.

### `committee_members`
- **Columns:** `id`; `committee_id`; `?volunteer_id`; `?internal_reference: string(64)`; `?member_role: string(120)`; `status: string(32)='active'`; `?joined_at: date`; timestamps.
- **Keys:** `I(committee_id, status)`; FKs committee CASCADE, volunteer `SET NULL`.
- **Model:** **none** (bridge table; not in config resources).
- **Demo:** yes — **9**.

### `committee_meetings`
- **Columns:** `id`; `committee_id`; `code`; `?meeting_date: date`; `?minutes: text`; `?decisions: text`; `?evidence_media_id`; `status: string(32)='scheduled'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(committee_id, meeting_date)`; FKs committee CASCADE, media `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **6**.

### `community_feedback`
- **Columns:** `id`; `?committee_id`; `category: string(64)`; `summary: text`; `?source_reference: string(64)`; `status: string(32)='new'`; `?deleted_at`; timestamps.
- **Keys:** `I(status, category)`; FK committee `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **8**.

### `partners`
- **Purpose:** internal operational partner registry (distinct from public `directory_partners`).
- **Columns:** `id`; `code`; `partner_type: string(32)`; `organization_type: string(64)`; `?country: string(100)`; `?location_id`; `?website`, `?public_contact: string(255)`; `?internal_notes`, `?strategic_alignment: text`; `relationship_status: string(32)='potential'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(relationship_status, partner_type)`; FK location `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **8**.

### `partner_project`
- **Purpose:** **pivot** partner↔project.
- **Columns:** `id`; `partner_id`; `project_id`; `?relationship_type: string(64)`; timestamps.
- **Keys:** `U(partner_id, project_id)`; FKs CASCADE.
- **Model:** **none** (pivot).
- **Demo:** yes — **10**.

### `partner_agreements`
- **Columns:** `id`; `partner_id`; `reference: string(64)`; `?start_date`, `?end_date: date`; `?document_media_id`; `status: string(32)='draft'`; `?deleted_at`; timestamps.
- **Keys:** `U(reference)`, `I(partner_id, status)`; FKs partner CASCADE, media `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **6**.

### `partner_contacts`
- **Columns:** `id`; `partner_id`; `internal_reference: string(64)`; `name: string`; `?email: string`; `?phone: string(64)`; `?role: string(120)`; `status: string(32)='active'`; `?deleted_at`; timestamps.
- **Keys:** `U(internal_reference)`, `I(partner_id, status)`; FK partner CASCADE.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **12** (internal references only, no real identities).

### `grant_opportunities`
- **Columns:** `id`; `reference: string(64)`; `?funder_partner_id`; `category: string(64)`; `?geographic_eligibility`, `?sector_eligibility: string(500)`; `?min_amount`, `?max_amount: decimal(18,2)`; `currency: string(3)`; `?opens_at`, `?closes_at: date`; `?source_url: string(500)`; `?owner_id`; `status: string(32)='open'`; `?deleted_at`; timestamps.
- **Keys:** `U(reference)`, `I(status, closes_at)`; FKs partner/users `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **5**.

### `grant_proposals`
- **Columns:** `id`; `code`; `?opportunity_id`, `?program_id`, `?project_id`; `?budget_amount: decimal(18,2)`; `currency: string(3)`; `?deadline`, `?submitted_at: date`; `?submission_evidence_media_id`; `?responsible_user_id`; `status: string(32)='identified'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(status, deadline)`; FKs `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **6**.

### `grant_awards`
- **Columns:** `id`; `reference`; `?donor_partner_id`, `?proposal_id`, `?project_id`; `amount: decimal(18,2)`; `currency: string(3)`; `?start_date`, `?end_date: date`; `?payment_schedule_note: text`; `?agreement_media_id`, `?evidence_media_id`; `status: string(32)='draft'`; `?deleted_at`; timestamps.
- **Keys:** `U(reference)`, `I(status, donor_partner_id)`; FKs `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **4**.

### `results_frameworks`
- **Columns:** `id`; `code`; `?program_id`, `?project_id`; `?theory_of_change: longtext`; `status: string(32)='draft'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(status, program_id)`; FKs `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **4**.

### `reporting_periods`
- **Columns:** `id`; `code`; `start_date: date`; `end_date: date`; `status: string(32)='open'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(status, start_date)`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **4**.

### `indicators`
- **Columns:** `id`; `code`; `?framework_id`, `?program_id`, `?project_id`, `?intervention_id`; `indicator_type: string(32)`; `unit: string(64)`; `?baseline_value`, `?target_value: decimal(18,4)`; `calculation_rule: text`; `disaggregation_enabled: boolean=false`; `status: string(32)='draft'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(status, indicator_type)`; FKs `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **12**.

### `indicator_measurements`
- **Columns:** `id`; `indicator_id`; `?period_id`; `?reported_value`, `?verified_value`, `?approved_value: decimal(18,4)`; `?data_source: string(500)`; `?evidence_media_id`, `?verifier_id`; `?verification_notes: text`; `?submitted_at`, `?verified_at: date`; `status: string(32)='draft'`; `?deleted_at`; timestamps.
- **Keys:** `I(indicator_id, period_id)`, `I(status, verified_at)`; FK indicator CASCADE, others `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **24**.

### `evaluations`
- **Columns:** `id`; `code`; `?project_id`; `evaluation_type: string(64)`; `?evidence_media_id`; `status: string(32)='planned'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(status, evaluation_type)`; FKs `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **4**.

### `corrective_actions`
- **Columns:** `id`; `code`; `?evaluation_id`; `?owner_id`; `?due_date: date`; `status: string(32)='open'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(status, due_date)`; FKs `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **6**.

### `assessments`
- **Columns:** `id`; `code`; `assessment_type: string(32)`; `need_category: string(64)`; `methodology_type: string(64)`; `target_population_definition: text`; `?location_id`, `?program_id`, `?project_id`, `?strategic_pillar_id`, `?evidence_media_id`; `status: string(32)='draft'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(status, assessment_type, need_category)`; FKs `SET NULL`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **4**.

### `data_collection_instruments`
- **Columns:** `id`; `code`; `instrument_type: string(64)`; `?version: string(32)`; `sensitive_data: boolean=false`; `status: string(32)='draft'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(status, instrument_type)`.
- **Model:** `OperationalRecord` generic.
- **Demo:** yes — **6**.

### `assessment_instrument`
- **Purpose:** **pivot** assessment↔instrument.
- **Columns:** `id`; `assessment_id`; `instrument_id`; timestamps.
- **Keys:** `U(assessment_id, instrument_id)`; FKs CASCADE.
- **Model:** **none** (pivot).
- **Demo:** yes — **8**.

### `operational_reports`
- **Columns:** `id`; `code`; `category: string(64)`; `?date_from`, `?date_to: date`; `?program_id`, `?project_id`, `?location_id`; `?status_filter: string(64)`; `?generated_at: timestamp`; `?generated_by`; `?summary: json`; `format: string(16)='html'`; `status: string(32)='draft'`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`, `I(category, generated_at)`; FKs `SET NULL`.
- **Model:** `OperationalRecord` generic, with `summary` merged into casts as array in `configureResource('operational_reports')`.
- **Demo:** yes — **6**.

---

## I. Website engagement (2026_11_26_000100)

### `email_templates`
- **Purpose:** email template definitions keyed by purpose.
- **Columns:** `id`; `key: string(96)`; `is_active: boolean=true`; `sort_order: uint=0`; timestamps.
- **Keys:** `U(key)`.
- **Model:** `App\Models\EmailTemplate` — casts bool/int; `translations()` hasMany; `translationFor()` with locale fallback chain.
- **Demo:** reference — **8** templates from `EmailTemplatesSeeder`.

### `email_template_translations`
- **Columns:** `id`; `email_template_id`; `locale: string(8)`; `name: string(160)`; `?description: string(500)`; `subject: string(255)`; `body: text`; timestamps.
- **Keys:** `U(email_template_id, locale)`; FK template CASCADE.
- **Model:** `App\Models\EmailTemplateTranslation` — `template()` belongsTo.
- **Demo:** reference — **16** email template translations.

### `inquiry_notes`
- **Purpose:** internal notes on inquiries using `inquiry_type`+`inquiry_id` discriminator.
- **Columns:** `id`; `inquiry_type: string(32)`; `inquiry_id: bigint`; `?user_id: bigint` (**no FK constraint**); `body: text`; timestamps.
- **Keys:** `I(inquiry_type, inquiry_id)`. **Polymorphic, no FK to inquiry.**
- **Model:** `App\Models\InquiryNote` — `author()` belongsTo `User`.
- **Demo:** yes — **10**.

### `inquiry_replies`
- **Purpose:** outbound inquiry replies and delivery state.
- **Columns:** `id`; `inquiry_type: string(32)`; `inquiry_id: bigint`; `?reference: string(64)`; `recipient: string(255)`; `?sender_user_id`, `?email_template_id` (**no FKs**); `subject: string(255)`; `body: text`; `locale: string(8)`; `status: string(24)='pending'`; `?error: text`; `?sent_at: timestamp`; timestamps.
- **Keys:** `I(inquiry_type, inquiry_id)`. Polymorphic.
- **Model:** `App\Models\InquiryReply` — casts `sent_at`→datetime; `sender()`, `template()` belongsTo.
- **Demo:** yes — **8**.

### `email_delivery_logs`
- **Purpose:** idempotent outbound email delivery audit.
- **Columns:** `id`; `idempotency_key: string(128)`; `purpose: string(48)`; `?template_key: string(96)`; `?inquiry_type: string(32)`; `?inquiry_id: bigint`; `?inquiry_reference: string(64)`; `recipient_hash: string(64)`; `locale: string(8)`; `status: string(24)`; `?provider: string(64)`; `?provider_message_id: string(255)`; `?error_code: text`; `attempts: usmallint=1`; `?sender_user_id` (no FK); `?queued_at`, `?sent_at`, `?failed_at: timestamp`; timestamps.
- **Keys:** `U(idempotency_key)`.
- **Model:** `App\Models\EmailDeliveryLog` — casts attempts/int and dates; `sender()` belongsTo `User`.
- **Demo:** yes — **20**.

---

## J. Complaints (2026_11_26_152001)

### `complaints`
- **Purpose:** public complaints with safe contact fields and workflow.
- **Columns:** `id`; `reference: string(32)`; `category: string(48)`; `subject: string`; `description: text`; `locale: string(5)='ar'`; `?related_project`, `?location: string`; `status: string(32)='new'`; `priority: string(16)='normal'`; `is_anonymous: boolean=false`; `?complainant_name`, `?preferred_contact_method: string(24)`, `?email`, `?phone`, `?preferred_locale: string(5)`; `?assigned_to`; `?resolved_at: timestamp`; `?resolution_details: text`; `?ip_hash: string(64)`; `?user_agent: string(500)`; `?deleted_at`; timestamps.
- **Keys:** `U(reference)`; `I(category)`, `I(status)`, `I(priority)`, `I(status, priority)`, `I(created_at, status)`; FK `assigned_to → users.id SET NULL`.
- **Model:** `App\Models\Complaint` — `SoftDeletes`, `HasFactory`; casts category→`ComplaintCategory`, contact method→`ComplaintContactMethod`, status→`ComplaintStatus`, priority→`ComplaintPriority`, `is_anonymous` bool, `resolved_at` datetime; `$hidden` protects complainant/contact/resolution/IP/UA fields; relations `assignee()`, `notes()`, `statusHistories()`, `attachments()`; scope `assignedTo()`; helper `isAnonymous()`.
- **Demo:** yes — **12** (mix of anonymous, in-progress, resolved).

### `complaint_status_histories`
- **Columns:** `id`; `complaint_id`; `?from_status`, `to_status: string(32)`; `?changed_by`; `?note: text`; timestamps.
- **Keys:** `I(complaint_id, created_at)`; FKs complaint CASCADE, user `SET NULL`.
- **Model:** `App\Models\ComplaintStatusHistory` — `complaint()`, `changedBy()` belongsTo.
- **Demo:** yes — **24**.

### `complaint_notes`
- **Columns:** `id`; `complaint_id`; `user_id`; `body: text`; `is_internal: boolean=true`; timestamps.
- **Keys:** `I(complaint_id, created_at)`; FKs complaint CASCADE, user CASCADE.
- **Model:** `App\Models\ComplaintNote` — cast `is_internal`→bool; `complaint()`, `author()`.
- **Demo:** yes — **18**.

### `complaint_attachments`
- **Columns:** `id`; `complaint_id`; `media_id`; `?label: string`; timestamps.
- **Keys:** `U(complaint_id, media_id)`; FKs complaint CASCADE, media CASCADE (**note: RESTRICT in most other media pivots**).
- **Model:** `App\Models\ComplaintAttachment` — `complaint()`, `media()`.
- **Demo:** yes — **10**.

### `complaint_reference_sequences`
- **Purpose:** per-year reference counter.
- **Columns:** `year: usmallint PK`; `next_value: bigint=1`; timestamps.
- **Keys:** PK `year`.
- **Model:** `App\Models\ComplaintReferenceSequence` — custom PK `year`, `$incrementing=false`, `$keyType='int'`; casts year/next_value integers.
- **Demo:** yes — **1** row for current year.

---

## K. Public partner directory (2026_11_27_000100)

### `directory_partner_categories`
- **Columns:** `id`; `slug: string`; `is_active: boolean=true`; `display_order: uint=0`; `?deleted_at`; timestamps.
- **Keys:** `U(slug)`.
- **Model:** `App\Models\DirectoryPartnerCategory` — `SoftDeletes`, `HasTranslations`; bool/int casts; scopes `active()`, `ordered()`; `partners()` hasMany.
- **Demo:** yes — **4**.

### `directory_partner_category_translations`
- **Columns:** `id`; `directory_partner_category_id`; `locale`; `name: string`; `?description: text`; timestamps.
- **Keys:** `U(category_id, locale)`; FK category CASCADE.
- **Model:** `App\Models\DirectoryPartnerCategoryTranslation` — `category()` belongsTo.
- **Demo:** yes — **8**.

### `directory_partners`
- **Columns:** `id`; `?directory_partner_category_id`; `?logo_media_id`; `?website_url: string(500)`; `?partnership_year: usmallint`; `status: string(32)='draft'`; `?published_at: timestamp`; `display_order: uint=0`; `?deleted_at`; timestamps.
- **Keys:** `I(status, published_at)`, `I(category_id, display_order)`; FKs category/media `SET NULL`.
- **Model:** `App\Models\DirectoryPartner` — `SoftDeletes`, `HasTranslations`, `Publishable`; casts year/published_at/display_order; scope `ordered()`; `category()` and `logo()` belongsTo.
- **Demo:** yes — **8**.

### `directory_partner_translations`
- **Columns:** `id`; `directory_partner_id`; `locale`; `slug`; `name: string`; `?description: text`; timestamps.
- **Keys:** `U(partner_id, locale)`, `U(locale, slug)`; FK partner CASCADE.
- **Model:** `App\Models\DirectoryPartnerTranslation` — `partner()` belongsTo.
- **Demo:** yes — **16**.

---

## L. Account email changes (2026_11_27_010000)

### `user_email_changes`
- **Purpose:** tokenized email-change requests.
- **Columns:** `id`; `user_id`; `current_email: string`; `new_email: string`; `token_hash: string`; `expires_at: timestamp`; `?consumed_at: timestamp`; timestamps.
- **Keys:** `U(token_hash)`; FK user CASCADE.
- **Model:** `App\Models\UserEmailChange` — casts dates; `user()` belongsTo.
- **Demo:** yes — **2** (pending + consumed).

---

## M. Phase 5 operating layer (2026_11_25_000100)

### `phase5_translations`
- **Purpose:** shared localized name/description for Phase-5 resources by discriminator.
- **Columns:** `id`; `resource: string(64)`; `owner_id: bigint`; `locale: string(8)`; `?name: string(255)`; `?description: text`; timestamps.
- **Keys:** `U(resource, owner_id, locale)`, `I(resource, locale)`. Pseudo-polymorphic, no FK.
- **Model:** **none** — accessed via `DB::table('phase5_translations')` in `WorkflowService`.
- **Demo:** yes — **10**.

### `workflow_definitions`
- **Columns:** `id`; `key: string(96)`; `category: string(48)`; `status: string(32)='draft'`; `trigger_event: string(96)='manual'`; `?owner_id`, `?created_by`; `?deleted_at`; timestamps.
- **Keys:** `U(key)`, `I(status, category)`; FKs users `SET NULL`.
- **Model:** `App\Models\Phase5\WorkflowDefinition` — `SoftDeletes`; int casts; relations `versions()`, `instances()`, `owner()`, `creator()`.
- **Demo:** yes — **4**.

### `workflow_versions`
- **Columns:** `id`; `definition_id`; `version: uint`; `config: text`; `?published_at: timestamp`; `?created_by`; timestamps.
- **Keys:** `U(definition_id, version)`; FK definition CASCADE, creator `SET NULL`.
- **Model:** `App\Models\Phase5\WorkflowVersion` — casts `config`→array, published_at datetime; relations `definition()`, `instances()`, `creator()`.
- **Demo:** yes — **6**.

### `workflow_instances`
- **Columns:** `id`; `reference: string(48)`; `definition_id`; `version_id`; `?subject_type: string(96)`, `?subject_id: bigint`; `status: string(32)='draft'`; `current_step_order: uint=0`; `?initiator_id`; `?due_at: timestamp`; `?metadata: text`; timestamps.
- **Keys:** `U(reference)`, `I(status, created_at)`, `I(subject_type, subject_id)`; FKs definition/version RESTRICT, initiator `SET NULL`. Pseudo-polymorphic subject.
- **Model:** `App\Models\Phase5\WorkflowInstance` — casts metadata→array, dates/ints; relations `definition()`, `version()`, `initiator()`, `steps()`, `transitions()`.
- **Demo:** yes — **8**.

### `workflow_steps`
- **Columns:** `id`; `instance_id`; `step_key: string(96)`; `label: string(255)`; `ordering: uint`; `assignment_type: string(32)='unassigned'`; `?assignee_id`; `?role_key: string(96)`; `status: string(32)='pending'`; `?due_at`, `?started_at`, `?completed_at: timestamp`; `?result: text`; timestamps.
- **Keys:** `U(instance_id, step_key)`, `I(instance_id, status)`, `I(assignee_id, status)`; FKs instance CASCADE, assignee `SET NULL`.
- **Model:** `App\Models\Phase5\WorkflowStep` — casts ints/dates; `instance()`, `assignee()`.
- **Demo:** yes — **24**.

### `workflow_transitions`
- **Columns:** `id`; `instance_id`; `?from_status`, `to_status: string(32)`; `action: string(96)`; `?actor_id`; `?reason`, `?context: text`; `created_at: timestamp`.
- **Keys:** `I(instance_id, created_at)`; FKs instance CASCADE, actor `SET NULL`.
- **Model:** `App\Models\Phase5\WorkflowTransition` — `$timestamps=false`; casts `context`→array, created_at datetime; `instance()`, `actor()`; **booted hooks block updates/deletes** (append-only).
- **Demo:** yes — **16**.

### `tasks`
- **Columns:** `id`; `code: string(48)`; `title: string(255)`; `?description: text`; `status: string(32)='to_do'`; `priority: string(16)='normal'`; `?start_at`, `?due_at: timestamp`; `?owner_id`, `?creator_id`; `?subject_type: string(96)`, `?subject_id: bigint`; `?workflow_instance_id`; `?parent_id`; `progress_percent: utinyint=0`; `?completed_at`, `?archived_at: timestamp`; `?deleted_at`; timestamps.
- **Keys:** `U(code)`; indexes `status+due_at`, `owner_id+status`, `creator_id+status`, `subject_type+subject_id`, `workflow_instance_id`; FKs workflow instance/parent `SET NULL`, users `SET NULL`. Pseudo-polymorphic subject.
- **Model:** `App\Models\Phase5\Task` — `SoftDeletes`; int/datetime casts; relations `owner()`, `creator()`, `workflowInstance()`, `checklist()`, `dependencies()`, `collaborators()`, `assignmentHistory()`.
- **Demo:** yes — **20**.

### `task_collaborators`
- **Columns:** `id`; `task_id`; `user_id`; timestamps.
- **Keys:** `U(task_id, user_id)`; FKs CASCADE.
- **Model:** `App\Models\Phase5\TaskCollaborator` — `task()`, `user()`.
- **Demo:** yes — **40** collaborator rows.

### `task_assignment_history`
- **Columns:** `id`; `task_id`; `?from_user_id`, `?to_user_id`, `?actor_id`; `created_at: timestamp`.
- **Keys:** `I(task_id, created_at)`; FKs task CASCADE, users `SET NULL`.
- **Model:** `App\Models\Phase5\TaskAssignmentHistory` — `$timestamps=false`, explicit table; casts ints/datetime; relations `task()`, `previousOwner()`, `newOwner()`, `actor()`.
- **Demo:** yes — **12**.

### `task_checklist_items`
- **Columns:** `id`; `task_id`; `title: string(255)`; `position: uint=0`; `is_done: boolean=false`; `?completed_by`; `?completed_at: timestamp`; timestamps.
- **Keys:** `I(task_id, position)`; FKs task CASCADE, user `SET NULL`.
- **Model:** `App\Models\Phase5\TaskChecklistItem` — casts bool/int/datetime; `task()`, `completer()`.
- **Demo:** yes — **40**.

### `task_dependencies`
- **Columns:** `id`; `task_id`; `depends_on_task_id`; `type: string(32)='finish_to_start'`; `created_at: timestamp`.
- **Keys:** `U(task_id, depends_on_task_id)`; FKs task CASCADE (both sides).
- **Model:** `App\Models\Phase5\TaskDependency` — `$timestamps=false`; casts ints/datetime; `task()`, `dependsOn()`.
- **Demo:** yes — **12**.

### `discussion_threads`
- **Columns:** `id`; `resource_type: string(96)`; `resource_id: bigint`; `?created_by`; timestamps.
- **Keys:** `U(resource_type, resource_id)`; FK creator `SET NULL`. Pseudo-polymorphic resource.
- **Model:** `App\Models\Phase5\DiscussionThread` — casts ints; `creator()`, `comments()`, `followers()` (hasMany `ResourceFollower` filtered by `resource_type`).
- **Demo:** yes — **8**.

### `discussion_comments`
- **Columns:** `id`; `thread_id`; `?parent_id`; `?author_id`; `body: text`; `?edited_at`, `?deleted_at: timestamp`; timestamps.
- **Keys:** `I(thread_id, created_at)`; FKs thread CASCADE, parent `SET NULL`, author `SET NULL`.
- **Model:** `App\Models\Phase5\DiscussionComment` — **SoftDeletes** (uses `deleted_at`); casts ints/datetime; relations `thread()`, `parent()`, `replies()`, `author()`, `mentions()`.
- **Demo:** yes — **24**.

### `comment_mentions`
- **Columns:** `id`; `comment_id`; `mentioned_user_id`; `?notified_at: timestamp`; timestamps.
- **Keys:** `U(comment_id, mentioned_user_id)`; FKs CASCADE.
- **Model:** `App\Models\Phase5\CommentMention` — casts ints/datetime; `comment()`, `mentionedUser()`.
- **Demo:** yes — **10**.

### `resource_followers`
- **Columns:** `id`; `resource_type: string(96)`; `resource_id: bigint`; `user_id`; timestamps.
- **Keys:** `U(resource_type, resource_id, user_id)`; FK user CASCADE. Pseudo-polymorphic.
- **Model:** `App\Models\Phase5\ResourceFollower` — casts ints; `user()`.
- **Demo:** yes — **16**.

### `accountability_cases`
- **Purpose:** safe accountability case records (no personal narrative fields).
- **Columns:** `id`; `reference: string(48)`; `category: string(64)`; `channel: string(32)='internal'`; `status: string(32)='received'`; `summary: text`; `?preferred_contact_method: string(32)`; `language_preference: string(8)='ar'`; `is_sensitive: boolean=false`; `?assigned_to`; `?due_at`, `?closed_at: timestamp`; `?deleted_at`; timestamps.
- **Keys:** `U(reference)`, `I(status, created_at)`, `I(assigned_to, status)`; FK assignee `SET NULL`.
- **Model:** `App\Models\Phase5\AccountabilityCase` — `SoftDeletes`, `HasFactory`; casts bool/dates; `events()`, `assignee()`.
- **Demo:** yes — **6**.

### `accountability_events`
- **Columns:** `id`; `case_id`; `action: string(96)`; `?from_status`, `?to_status: string(32)`; `?actor_id`; `?note: text`; `is_sensitive: boolean=false`; `created_at: timestamp`.
- **Keys:** `I(case_id, created_at)`; FKs case CASCADE, actor `SET NULL`.
- **Model:** `App\Models\Phase5\AccountabilityEvent` — `$timestamps=false`; casts bool/ints/datetime; `case()`, `actor()`; **booted hooks block updates/deletes**.
- **Demo:** yes — **12**.

### `referrals`
- **Purpose:** non-identifying referral between two authorized operational references.
- **Columns:** `id`; `reference: string(48)`; `source_type`, `destination_type: string(96)`; `source_id`, `destination_id: bigint`; `reason: text`; `status: string(32)='draft'`; `?assigned_to`, `?created_by`; `?due_at`, `?response_at: timestamp`; `?outcome`, `?closure_reason: text`; `?deleted_at`; timestamps.
- **Keys:** `U(reference)`, `I(status, due_at)`, `I(source_type, source_id)`, `I(assigned_to, status)`; FKs users `SET NULL`. Double pseudo-polymorphic relation.
- **Model:** `App\Models\Phase5\Referral` — `SoftDeletes`, `HasFactory`; casts ints/dates; `events()`, `assignee()`, `creator()`.
- **Demo:** yes — **6**.

### `referral_events`
- **Columns:** `id`; `referral_id`; `action: string(96)`; `?from_status`, `?to_status: string(32)`; `?actor_id`; `?note: text`; `created_at: timestamp`.
- **Keys:** `I(referral_id, created_at)`; FKs referral CASCADE, actor `SET NULL`.
- **Model:** `App\Models\Phase5\ReferralEvent` — `$timestamps=false`; casts ints/datetime; `referral()`, `actor()`; **booted hooks block updates/deletes**.
- **Demo:** yes — **12**.

### `notification_preferences`
- **Columns:** `id`; `user_id`; `category: string(48)`; `channel: string(32)`; `enabled: boolean=true`; `digest: string(16)='instant'`; `?quiet_start`, `?quiet_end: string(5)`; timestamps.
- **Keys:** `U(user_id, category, channel)`; FK user CASCADE.
- **Model:** `App\Models\Phase5\NotificationPreference` — casts `enabled`→bool; `user()`.
- **Demo:** yes — **20**.

### `notification_deliveries`
- **Columns:** `id`; `?notification_id: foreignUlid`; `?user_id`; `channel: string(32)`; `provider: string(96)='none'`; `status: string(32)='pending'`; `?provider_reference: string(255)`; `attempts: utinyint=0`; `?error_code: string(96)`; `?queued_at`, `?sent_at`, `?delivered_at: timestamp`; timestamps.
- **Keys:** `I(user_id, status)`, `I(status, created_at)`; FKs notifications/users `SET NULL`.
- **Model:** `App\Models\Phase5\NotificationDelivery` — casts attempts/int and dates; `user()` only. **Mismatch:** no relation to `notifications` despite `notification_id` FK.
- **Demo:** yes — **30**.

### `automation_definitions`
- **Columns:** `id`; `key: string(96)`; `category: string(48)='generic'`; `trigger_event: string(96)`; `enabled: boolean=false`; `conditions: text`; `actions: text`; `?owner_id`; `version: uint=1`; `retry_limit: utinyint=3`; `timeout_seconds: usmallint=20`; `?last_executed_at: timestamp`; `?deleted_at`; timestamps.
- **Keys:** `U(key)`, `I(enabled, trigger_event)`; FK owner `SET NULL`.
- **Model:** `App\Models\Phase5\AutomationDefinition` — `SoftDeletes`; casts `conditions`/`actions`→array, enabled bool, ints/datetime; `owner()`.
- **Demo:** yes — **4**.

### `automation_executions`
- **Columns:** `id`; `?definition_id`; `definition_version: uint`; `trigger_event: string(96)`; `?subject_type: string(96)`, `?subject_id: bigint`; `idempotency_key: string(128)`; `status: string(32)='pending'`; `attempts: utinyint=0`; `?last_error`, `?context: text`; `?started_at`, `?finished_at: timestamp`; timestamps.
- **Keys:** `U(idempotency_key)`, `I(status, created_at)`, `I(definition_id, created_at)`; FK definition `SET NULL`. Pseudo-polymorphic subject.
- **Model:** `App\Models\Phase5\AutomationExecution` — casts ints, `context`→array, dates; status constants (`pending/queued/running/succeeded/failed/dead_letter`). No `definition()` relation declared.
- **Demo:** yes — **12**.

### `document_reviews`
- **Columns:** `id`; `reference: string(48)`; `media_id`; `?document_link_id`; `?subject_type: string(96)`, `?subject_id: bigint`; `title: string(255)`; `?description: text`; `status: string(32)='draft'`; `version: uint=1`; `?reviewer_id`, `?submitter_id`; `?due_at`, `?reviewed_at: timestamp`; `?outcome_note: text`; `?deleted_at`; timestamps.
- **Keys:** `U(reference)`, `I(status, reviewer_id)`, `I(subject_type, subject_id)`; FK media RESTRICT, document_link/users `SET NULL`. Pseudo-polymorphic subject.
- **Model:** `App\Models\Phase5\DocumentReview` — `SoftDeletes`, `HasFactory`; casts ints/dates; relations `media()`, `documentLink()`, `events()`, `reviewer()`, `submitter()`.
- **Demo:** yes — **6**.

### `document_review_events`
- **Columns:** `id`; `review_id`; `action: string(96)`; `?from_status`, `?to_status: string(32)`; `?actor_id`; `?note: text`; `revision: uint=1`; `created_at: timestamp`.
- **Keys:** `I(review_id, created_at)`; FKs review CASCADE, actor `SET NULL`.
- **Model:** `App\Models\Phase5\DocumentReviewEvent` — `$timestamps=false`; casts ints/datetime; `review()`, `actor()`; **booted hooks block updates/deletes**.
- **Demo:** yes — **12**.

### `integration_registries`
- **Columns:** `id`; `key: string(96)`; `name: string(255)`; `provider: string(96)`; `category: string(48)`; `?metadata: text`; `enabled: boolean=false`; `health_status: string(32)='unknown'`; `?last_success_at`, `?last_failure_at: timestamp`; `?deleted_at`; timestamps.
- **Keys:** `U(key)`, `I(enabled, category)`.
- **Model:** `App\Models\Phase5\IntegrationRegistry` — casts `metadata`→array, enabled bool, dates; `getRouteKeyName()='key'`; `$hidden=['metadata','deleted_at']`. **Mismatch:** table has `deleted_at` but the model does **not** use `SoftDeletes`.
- **Demo:** yes — **4**.

### `webhook_endpoints`
- **Columns:** `id`; `integration_id`; `name: string(255)`; `url: text`; `events: text`; `secret_encrypted: text`; `active: boolean=false`; `?created_by`; `?last_success_at`, `?last_failure_at: timestamp`; timestamps.
- **Keys:** `I(integration_id, active)`; FKs integration CASCADE, creator `SET NULL`.
- **Model:** `App\Models\Phase5\WebhookEndpoint` — casts `events`→array, `secret_encrypted`→encrypted, active bool, dates; `$hidden=['secret_encrypted']`; `integration()`, `creator()`.
- **Demo:** yes — **4** (no real secrets).

### `webhook_deliveries`
- **Columns:** `id`; `endpoint_id`; `event: string(96)`; `payload_version: utinyint=1`; `payload_hash: string(128)`; `status: string(32)='pending'`; `attempts: utinyint=0`; `?response_code: usmallint`; `?error_code: string(96)`; `?next_attempt_at`, `?delivered_at: timestamp`; `created_at: timestamp`.
- **Keys:** `I(endpoint_id, status)`, `I(status, next_attempt_at)`; FK endpoint CASCADE.
- **Model:** `App\Models\Phase5\WebhookDelivery` — `$timestamps=false`; casts ints/dates; `endpoint()`; status constants (`pending/sending/delivered/failed/dead_letter/disabled`).
- **Demo:** yes — **12**.

### `incoming_webhook_events`
- **Columns:** `id`; `integration_id`; `event: string(96)`; `nonce: string(128)`; `payload_hash: string(128)`; `processed: boolean=false`; `received_at: timestamp`; `created_at: timestamp`.
- **Keys:** `U(nonce)`; FK integration CASCADE.
- **Model:** `App\Models\Phase5\IncomingWebhookEvent` — `$timestamps=false`; casts bool/dates. No `integration()` relation declared.
- **Demo:** yes — **6**.

---

## N. Cross-cutting behavior

### Shared traits / concerns
- **`HasTranslations`** (Page, Pillar, Program, Project, Article, Category, Tag, Publication, NavigationItem, HomepageSection/Item, Media, WebsiteSetting, DirectoryPartner/Category): `translations()` HasMany using each model’s `translationModel()`/`translationForeignKey()`; `translate()` fallback chain requested locale → `website.default_locale` → `website.fallback_locale` → lowest id; helpers `t()`, `availableLocales()`, `hasLocale()`.
- **`Publishable`** (Page, Pillar, Program, Article, Publication, HomepageSection, DirectoryPartner): `published()`/`visibleToPublic()` scopes require `status=published` and `published_at` null or past; `isPublished()`, `statusLabel()`. `Project` intentionally implements its own version using `workflow_status`.
- **`HasOperationalTranslations`** (OperationalRecord): `translations()` hasMany `OperationalTranslation` filtered by `resource` discriminator; same locale fallback chain.
- **Enums:** `ContentStatus` (draft/pending_review/approved/published/archived, config-driven transitions), `ProjectStatus`, `MediaType`, `ComplaintCategory`, `ComplaintStatus` (config-driven transitions/final states), `ComplaintPriority`, `ComplaintContactMethod`.
- **Booted model hooks:** `RoleScope::saving` (hash scope value); `AuditLog::updating`/`deleting` (archive-only edits; token-gated deletes); `DomainTransition`, `InventoryMovement`, `WorkflowTransition`, `AccountabilityEvent`, `ReferralEvent`, `DocumentReviewEvent` (append-only; updates/deletes blocked).
- **Events/listeners:** `App\Events\Phase5Event` is dispatched by Phase-5 services/commands; `App\Listeners\Phase5\HandlePhase5Event` would fan out to notifications, automations, webhooks. `AppServiceProvider` registers only `MessageSending` (email-logo attacher), policies, gates and rate limiters. **No observer classes exist anywhere in `app/`.**

---

## O. Explicit call-outs

### Tables with no Eloquent model
`password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `role_user`, `permission_role`, `article_tag`, `page_media`, `pillar_media`, `project_media`, `article_media`, `publication_media`, `volunteer_team_members`, `volunteer_training`, `committee_members`, `partner_project`, `assessment_instrument`, `phase5_translations` — plus the 5 dynamically created media pivots listed by name above. Most Phase-4 resource tables have **no dedicated model**, only the config-bound `OperationalRecord`.

### Pivot tables
`role_user`, `permission_role`, `article_tag`, `page_media`, `pillar_media`, `project_media`, `article_media`, `publication_media`, `partner_project`, `volunteer_team_members`, `volunteer_training`, `committee_members`, `assessment_instrument`. Only `role_user`/`permission_role` are traversed via Eloquent `belongsToMany`; the media pivots are traversed via owner models with pivots; the Phase-4 pivots are unmodelled.

### Soft-deleted tables
`users`, `roles`, `media`, `pages`, `strategic_pillars`, `programs`, `projects`, `articles`, `publications`, `directory_partner_categories`, `directory_partners`, `complaints`, `workflow_definitions`, `tasks`, `discussion_comments`, `accountability_cases`, `referrals`, `automation_definitions`, `document_reviews`, `integration_registries` (column exists; model lacks trait — see mismatch), `inventory_items`, `locations`, `interventions`, `activities`, `distribution_plans`, `distribution_rounds`, `distribution_records`, `warehouses`, `volunteers`, `volunteer_teams`, `volunteer_assignments`, `volunteer_trainings`, `committees`, `committee_meetings`, `community_feedback`, `partners`, `partner_agreements`, `partner_contacts`, `grant_opportunities`, `grant_proposals`, `grant_awards`, `results_frameworks`, `reporting_periods`, `indicators`, `indicator_measurements`, `evaluations`, `corrective_actions`, `assessments`, `data_collection_instruments`, `operational_reports`.

### Polymorphic / discriminator-based relations
- True Laravel morphs: `notifications.notifiable_*`; `content_revisions.revisionable_*` (MorphTo on model).
- Discriminator + bigint id (no FK): `homepage_section_items.item_type/item_id`, `inquiry_notes`/`inquiry_replies.inquiry_type/inquiry_id`, `audit_logs.subject_type/subject_id`, `audit_log_holds.subject_type/subject_id`, `operational_translations`, `domain_transitions`, `document_links`, `inventory_movements.source_type/source_id`, `phase5_translations`, `workflow_instances.subject_*`, `tasks.subject_*`, `discussion_threads.resource_*`, `resource_followers.resource_*`, `referrals.source_*/destination_*`, `automation_executions.subject_*`, `document_reviews.subject_*`.

### Migration ↔ model mismatches / gaps
1. **`HandlePhase5Event` is never registered** as a listener (`Event::listen`/`$listen` absent outside `AppServiceProvider`'s mail hook), so Phase-5 event fan-out to notification deliveries, automation executions and webhook deliveries appears unwired despite dispatching services.
2. **`integration_registries` has `deleted_at` but `IntegrationRegistry` does not use `SoftDeletes`** (it only hides the column).
3. **Duplicate theme migrations:** `2026_11_26_050331` and `2026_11_26_100000` both add `users.theme`; guarded by `hasColumn`, final state is nullable `string(16)` default `'system'`. Redundant; the second `->change()` may be fragile on some drivers.
4. **Phase-4 columns added to `programs`/`projects` are absent from model `$fillable` and casts** (`code`, `program_manager_id`/`project_manager_id`, responsible unit/team, category, budgets, currency, actual dates).
5. **`User::roles()` filters expired pivots; `Role::users()` does not** — inconsistent view of the same `role_user` rows.
6. `assigned_to`/`user_id`/`sender_user_id` on inquiries, `sessions.user_id`, and `email_delivery_logs.sender_user_id` are `foreignId` **without `constrained()`** — Eloquent relations exist but the database does not enforce them.
7. `Page` has `reviewer_id` in the table but **no `reviewer()` relationship**; `AuditLogHold` lacks relations for `actor_id`/`released_by`; `AutomationExecution` lacks `definition()`; `IncomingWebhookEvent` lacks `integration()`; `NotificationDelivery` lacks a `notifications` relation.
8. `ComplaintAttachment.media_id` uses `CASCADE` while the other media pivots use `RESTRICT`; `DeleteMedia` behavior should be checked to ensure intended protection.
9. `HomepageSectionItem.item_key` is nullable but part of a unique constraint; multiple NULL keys are permitted on MySQL/SQLite.
10. `InventoryBalance.updated_at` is NOT NULL with no default, while the model sets `$timestamps=false`; seeds must supply it explicitly.
11. `Integrity of migrations order`: Phase-4 migration explicitly creates `inventory_items` before `distribution_records` to satisfy MySQL DDL ordering — correct, but relies on a single file; splitting it would break fresh installs.

### Demo-data summary
Existing seeders already provide technical/reference data: permissions, 5 CMS roles, ~40 website settings, 2 navigation menus with ~20 items, ~6 strategic pillars, homepage sections/items from three seeders, and 9 email templates. **No factories/seeders exist for the bulk of Phase-4, Phase-5, complaints, inquiry, audit-lifecycle or directory data**; the proposed counts in each table block are intended for a realistic preview database. Framework tables (`cache`, `jobs`, `failed_jobs`, password resets) should stay empty; sessions/notifications can be lightly populated for UI demonstration.
</subagent>