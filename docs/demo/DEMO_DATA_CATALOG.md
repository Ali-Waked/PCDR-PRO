# Demo data catalog

This catalog records the dataset created by `database/seeders/Demo/DemoDatabaseSeeder.php` on a freshly migrated database. Counts are derived from static reading of the seeders. **Demo-owned** rows are registered in the private manifest and removed by cleanup; **reference** rows come from the existing non-demo seeders and are preserved by cleanup.

The audit covers 141 migration-created tables. Tables not listed below receive **0 demo-owned and 0 reference rows** under this seeder (for example `password_reset_tokens`, `cache`, `cache_locks`, `jobs`, `job_batches` and `failed_jobs`). No new tables or columns were introduced for the demo dataset.

## Framework, access control and settings

| Table | Demo-owned | Reference | Notes |
| --- | ---: | ---: | --- |
| `users` | 8 | 0 | Eight fictional accounts; one platform owner, one suspended. |
| `role_user` | 9 | 0 | Only existing CMS role keys. |
| `role_scopes` | 6 | 0 | `all`, `self`, `assigned` with hashed canonical values. |
| `sessions` | 4 | 0 | Account-security samples. |
| `notifications` | 10 | 0 | Fixed ULIDs reused by delivery records. |
| `settings` | 4 + 2,490 markers | 0 | Four private/public demo settings; the remaining rows are internal manifest bookkeeping in the same table. |
| `setting_translations` | 8 | 0 | Two locales for four internal settings. |
| `permissions` | 0 | 74 | `pcdr:permissions:sync`. |
| `roles` | 0 | 5 | `CmsRolesSeeder`. |
| `permission_role` | 0 | 116 | Role-permission assignments. |
| `audit_logs` | 40 | 1 | Append-only; inserted once with fixed timestamps. |
| `audit_archives` | 3 | 0 | Archive lifecycle records. |
| `audit_cleanup_runs` | 4 | 0 | Retention run reports. |
| `audit_log_holds` | 3 | 0 | Legal-hold records. |

## Media, website settings and navigation

| Table | Demo-owned | Reference | Notes |
| --- | ---: | ---: | --- |
| `media` | 17 | 0 | Licensed local JPEGs copied to deterministic public-disk paths. |
| `media_translations` | 34 | 0 | Arabic and English alt text/captions. |
| `media` files | 17 | 0 | Checksum-tracked; source files live in `storage/app/public/media/demo/images/`. |
| `website_settings` | 1 | 24 | Demo adds `contact.office_hours` only when absent. |
| `website_setting_translations` | 2 | 8 | Translations for the demo-owned office-hours setting. |
| `navigation_menus` | 0 | 2 | Homepage seeders. |
| `navigation_items` | 0 | 20 | Homepage seeders. |
| `navigation_item_translations` | 0 | 40 | Homepage seeders. |
| `strategic_pillars` | 0 | 11 | Reference taxonomy; demo attaches media but creates no pillars. |
| `strategic_pillar_translations` | 0 | 22 | Homepage seeders. |
| `email_templates` | 0 | 8 | `EmailTemplatesSeeder`. |
| `email_template_translations` | 0 | 16 | `EmailTemplatesSeeder`. |

## Editorial content

| Table | Demo-owned | Reference | Notes |
| --- | ---: | ---: | --- |
| `pages` | 4 | 0 | About, transparency, privacy and one child page. |
| `page_translations` | 8 | 0 | Arabic and English. |
| `programs` | 6 | 0 | Program planning records. |
| `program_translations` | 12 | 0 | Arabic and English. |
| `projects` | 8 | 0 | Linked to programs, pillars, managers and media. |
| `project_translations` | 16 | 0 | Arabic and English. |
| `article_categories` | 4 | 0 | Field updates, food security, livelihoods, community. |
| `article_category_translations` | 8 | 0 | Arabic and English. |
| `articles` | 12 | 0 | Localized editorial items with authors and reviewers. |
| `article_translations` | 24 | 0 | Arabic and English. |
| `tags` | 8 | 0 | Topic taxonomy. |
| `tag_translations` | 16 | 0 | Arabic and English. |
| `article_tag` | 20 | 0 | Tracked pivot pairs. |
| `publications` | 8 | 0 | Plans, policies, briefs and reviews. |
| `publication_translations` | 16 | 0 | Arabic and English. |
| `content_revisions` | 20 | 0 | Polymorphic revision records for pages/projects/articles. |
| `page_media` | 6 | 0 | Tracked media pivot. |
| `pillar_media` | 6 | 0 | Tracked media pivot. |
| `project_media` | 8 | 0 | Tracked media pivot. |
| `article_media` | 12 | 0 | Tracked media pivot. |
| `publication_media` | 8 | 0 | Tracked media pivot. |
| `homepage_sections` | 0 | 17 | Three homepage seeders. |
| `homepage_section_translations` | 0 | 34 | Homepage seeders. |
| `homepage_section_items` | 15 | 149 | Demo items are tracked; reference items are preserved. |
| `homepage_section_item_translations` | 30 | 298 | Arabic and English for each demo item. |

## Engagement, complaints and directory

| Table | Demo-owned | Reference | Notes |
| --- | ---: | ---: | --- |
| `contact_messages` | 6 | 0 | Fictional contact inbox. |
| `volunteer_inquiries` | 4 | 0 | Fictional interest forms. |
| `partnership_inquiries` | 4 | 0 | Fictional organization-free proposals. |
| `inquiry_notes` | 10 | 0 | Internal staff notes. |
| `inquiry_replies` | 8 | 0 | Outbound replies. |
| `email_delivery_logs` | 20 | 0 | Delivery status samples. |
| `complaints` | 12 | 0 | Mix of intake, in-progress and resolved cases. |
| `complaint_status_histories` | 24 | 0 | Status transitions. |
| `complaint_notes` | 18 | 0 | Internal notes. |
| `complaint_attachments` | 10 | 0 | Linked to demo media only. |
| `complaint_reference_sequences` | 1 | 0 | Current-year sequence. |
| `directory_partner_categories` | 4 | 0 | Internal taxonomy. |
| `directory_partner_category_translations` | 8 | 0 | Arabic and English. |
| `directory_partners` | 8 | 0 | Invented names and `.test` contacts only. |
| `directory_partner_translations` | 16 | 0 | Arabic and English. |
| `user_email_changes` | 2 | 0 | Pending and consumed requests. |

## Operations (hidden dashboard feature)

| Table | Demo-owned | Reference | Notes |
| --- | ---: | ---: | --- |
| `locations` | 8 | 0 | Operational geography. |
| `inventory_items` | 10 | 0 | Stock catalog. |
| `inventory_balances` | 30 | 0 | `updated_at` only; no `created_at` column. |
| `inventory_movements` | 40 | 0 | Append-only, inserted once and tracked. |
| `domain_transitions` | 59 | 0 | Append-only, inserted once and tracked. |
| `document_links` | 20 | 0 | Inserted once and tracked. |
| `interventions` | 6 | 0 | Program interventions. |
| `activities` | 10 | 0 | Delivery activities. |
| `distribution_plans` | 4 | 0 | Planning records. |
| `distribution_rounds` | 8 | 0 | Rounds linked to plans. |
| `distribution_records` | 16 | 0 | Round-level records. |
| `warehouses` | 3 | 0 | Storage locations. |
| `volunteers` | 12 | 0 | Fictional volunteer records. |
| `volunteer_teams` | 4 | 0 | Team definitions. |
| `volunteer_team_members` | 12 | 0 | Tracked pivot. |
| `volunteer_assignments` | 10 | 0 | Assignment records. |
| `volunteer_trainings` | 4 | 0 | Training records. |
| `volunteer_training` | 8 | 0 | Tracked pivot. |
| `committees` | 3 | 0 | Committee definitions. |
| `committee_members` | 9 | 0 | Tracked pivot. |
| `committee_meetings` | 6 | 0 | Meeting records. |
| `community_feedback` | 8 | 0 | Non-identifying feedback. |
| `partners` | 8 | 0 | Invented operational partners. |
| `partner_project` | 10 | 0 | Tracked pivot. |
| `partner_agreements` | 6 | 0 | Agreement records. |
| `partner_contacts` | 12 | 0 | Invented contacts only. |
| `grant_opportunities` | 5 | 0 | Funding opportunity records. |
| `grant_proposals` | 6 | 0 | Proposal records. |
| `grant_awards` | 4 | 0 | Award records. |
| `results_frameworks` | 4 | 0 | Framework headers. |
| `reporting_periods` | 4 | 0 | Period definitions. |
| `indicators` | 12 | 0 | Indicator catalog. |
| `indicator_measurements` | 24 | 0 | Measurement records. |
| `evaluations` | 4 | 0 | Evaluation records. |
| `corrective_actions` | 6 | 0 | Follow-up actions. |
| `assessments` | 4 | 0 | Assessment headers. |
| `data_collection_instruments` | 6 | 0 | Instruments. |
| `assessment_instrument` | 8 | 0 | Tracked pivot. |
| `operational_reports` | 6 | 0 | Report records. |
| `operational_translations` | 40 | 0 | Localized operational copy. |

## Phase 5 (hidden dashboard feature)

| Table | Demo-owned | Reference | Notes |
| --- | ---: | ---: | --- |
| `phase5_translations` | 10 | 0 | Localized workflow/task copy. |
| `workflow_definitions` | 4 | 0 | Definition headers. |
| `workflow_versions` | 6 | 0 | Version records. |
| `workflow_instances` | 8 | 0 | Instance records. |
| `workflow_steps` | 24 | 0 | Step records. |
| `workflow_transitions` | 16 | 0 | Append-only transition records. |
| `tasks` | 20 | 0 | Task records. |
| `task_collaborators` | 40 | 0 | Tracked pivot. |
| `task_assignment_history` | 12 | 0 | Append-only assignment history. |
| `task_checklist_items` | 40 | 0 | Checklist records. |
| `task_dependencies` | 12 | 0 | Append-only dependencies. |
| `discussion_threads` | 8 | 0 | Thread headers. |
| `discussion_comments` | 24 | 0 | Comment records. |
| `comment_mentions` | 10 | 0 | Tracked pivot. |
| `resource_followers` | 16 | 0 | Tracked pivot. |
| `accountability_cases` | 6 | 0 | Case headers. |
| `accountability_events` | 12 | 0 | Append-only events. |
| `referrals` | 6 | 0 | Referral headers. |
| `referral_events` | 12 | 0 | Append-only events. |
| `notification_preferences` | 20 | 0 | Per-user preferences. |
| `notification_deliveries` | 30 | 0 | Linked only to real demo notification ULIDs. |
| `automation_definitions` | 4 | 0 | Automation headers. |
| `automation_executions` | 12 | 0 | Execution records. |
| `document_reviews` | 6 | 0 | Review headers. |
| `document_review_events` | 12 | 0 | Append-only review events. |
| `integration_registries` | 4 | 0 | Integration definitions with `.test`/`.invalid` endpoints. |
| `webhook_endpoints` | 4 | 0 | Inert secret placeholder only. |
| `webhook_deliveries` | 12 | 0 | Delivery records. |
| `incoming_webhook_events` | 6 | 0 | Inbound events. |

## Source-of-truth notes

- `docs/demo/DATABASE_SEEDING_AUDIT.md` remains the schema inventory: migrations define columns/keys, models define casts/scopes/boot behavior.
- Reference counts can change if the underlying reference seeders change; demo-owned counts are controlled by the `Demo/*` seeders and are checked by `php artisan pcdr:demo:verify`.
- No real beneficiary data, real organization names as partners, or false achievements are included. Every person, partner, project and metric is fictional.
