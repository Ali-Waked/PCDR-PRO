<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\DemoSeedManifest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('pcdr:demo:verify')]
#[Description('Read-only checks for demo dataset coverage, references, images and idempotency markers')]
final class VerifyDemoDatasetCommand extends Command
{
    /** @var list<string> */
    private const REQUIRED_TRACKED_TABLES = [
        'users', 'role_user', 'role_scopes', 'media', 'media_translations', 'settings', 'setting_translations',
        'website_settings', 'website_setting_translations', 'user_email_changes', 'pages', 'page_translations', 'programs', 'program_translations',
        'projects', 'project_translations', 'article_categories', 'article_category_translations',
        'articles', 'article_translations', 'tags', 'tag_translations', 'article_tag',
        'article_media', 'publications', 'publication_translations', 'publication_media',
        'project_media', 'pillar_media', 'page_media', 'content_revisions',
        'homepage_section_items', 'homepage_section_item_translations', 'directory_partner_categories',
        'directory_partner_category_translations', 'directory_partners', 'directory_partner_translations',
        'contact_messages', 'volunteer_inquiries', 'partnership_inquiries', 'inquiry_notes',
        'inquiry_replies', 'email_delivery_logs', 'notifications', 'sessions', 'audit_archives',
        'audit_cleanup_runs', 'audit_log_holds', 'audit_logs', 'complaints', 'complaint_status_histories',
        'complaint_notes', 'complaint_attachments', 'complaint_reference_sequences',
        'operational_translations', 'domain_transitions', 'document_links', 'inventory_items',
        'locations', 'interventions', 'activities', 'distribution_plans', 'distribution_rounds',
        'distribution_records', 'warehouses', 'inventory_balances', 'inventory_movements',
        'volunteers', 'volunteer_teams', 'volunteer_team_members', 'volunteer_assignments',
        'volunteer_trainings', 'volunteer_training', 'committees', 'committee_members',
        'committee_meetings', 'community_feedback', 'partners', 'partner_project',
        'partner_agreements', 'partner_contacts', 'grant_opportunities', 'grant_proposals',
        'grant_awards', 'results_frameworks', 'reporting_periods', 'indicators',
        'indicator_measurements', 'evaluations', 'corrective_actions', 'assessments',
        'data_collection_instruments', 'assessment_instrument', 'operational_reports',
        'phase5_translations', 'workflow_definitions', 'workflow_versions', 'workflow_instances',
        'workflow_steps', 'workflow_transitions', 'tasks', 'task_collaborators',
        'task_assignment_history', 'task_checklist_items', 'task_dependencies', 'discussion_threads',
        'discussion_comments', 'comment_mentions', 'resource_followers', 'accountability_cases',
        'accountability_events', 'referrals', 'referral_events', 'notification_preferences',
        'notification_deliveries', 'automation_definitions', 'automation_executions', 'document_reviews',
        'document_review_events', 'integration_registries', 'webhook_endpoints', 'webhook_deliveries',
        'incoming_webhook_events',
    ];

    /**
     * @var array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    private const FOREIGN_KEY_CHECKS = [
        'role_user.user_id' => ['role_user', 'user_id', 'users', 'id'],
        'media.uploaded_by' => ['media', 'uploaded_by', 'users', 'id'],
        'media_translations.media_id' => ['media_translations', 'media_id', 'media', 'id'],
        'page_translations.page_id' => ['page_translations', 'page_id', 'pages', 'id'],
        'program_translations.program_id' => ['program_translations', 'program_id', 'programs', 'id'],
        'project_translations.project_id' => ['project_translations', 'project_id', 'projects', 'id'],
        'project_programs' => ['projects', 'program_id', 'programs', 'id'],
        'article_translations.article_id' => ['article_translations', 'article_id', 'articles', 'id'],
        'article_tag.article_id' => ['article_tag', 'article_id', 'articles', 'id'],
        'article_media.media_id' => ['article_media', 'media_id', 'media', 'id'],
        'publication_translations.publication_id' => ['publication_translations', 'publication_id', 'publications', 'id'],
        'homepage_section_items.media_id' => ['homepage_section_items', 'media_id', 'media', 'id'],
        'directory_partner_translations.partner_id' => ['directory_partner_translations', 'directory_partner_id', 'directory_partners', 'id'],
        'complaint_attachments.media_id' => ['complaint_attachments', 'media_id', 'media', 'id'],
        'complaint_notes.complaint_id' => ['complaint_notes', 'complaint_id', 'complaints', 'id'],
        'notification_deliveries.notification_id' => ['notification_deliveries', 'notification_id', 'notifications', 'id'],
        'inventory_balances.warehouse_id' => ['inventory_balances', 'warehouse_id', 'warehouses', 'id'],
        'distribution_records.round_id' => ['distribution_records', 'round_id', 'distribution_rounds', 'id'],
        'indicator_measurements.indicator_id' => ['indicator_measurements', 'indicator_id', 'indicators', 'id'],
        'task_checklist_items.task_id' => ['task_checklist_items', 'task_id', 'tasks', 'id'],
        'workflow_steps.instance_id' => ['workflow_steps', 'instance_id', 'workflow_instances', 'id'],
        'discussion_comments.thread_id' => ['discussion_comments', 'thread_id', 'discussion_threads', 'id'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const VISIBLE_LABEL_COLUMNS = [
        'page_translations' => ['title', 'summary', 'body', 'seo_title', 'seo_description'],
        'program_translations' => ['title', 'summary', 'description', 'objectives', 'target_groups', 'seo_title', 'seo_description'],
        'project_translations' => ['title', 'summary', 'description', 'objectives', 'target_groups', 'seo_title', 'seo_description'],
        'article_translations' => ['title', 'excerpt', 'body', 'seo_title', 'seo_description'],
        'publication_translations' => ['title', 'description', 'seo_title', 'seo_description'],
        'article_category_translations' => ['name', 'description'],
        'tag_translations' => ['name'],
        'homepage_section_item_translations' => ['label', 'title', 'body', 'date_label', 'cta_label', 'url', 'caption'],
        'directory_partner_translations' => ['name', 'description'],
        'contact_messages' => ['subject', 'message'],
        'volunteer_inquiries' => ['message'],
        'partnership_inquiries' => ['message'],
        'complaints' => ['subject', 'details', 'resolution_details'],
        'complaint_notes' => ['body'],
        'operational_translations' => ['name', 'title', 'summary', 'description'],
        'phase5_translations' => ['name', 'description'],
        'tasks' => ['title', 'description'],
    ];

    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    public function handle(): int
    {
        if (! Schema::hasTable('settings')) {
            $this->error('The settings table is missing; run migrations before verification.');

            return self::FAILURE;
        }

        $manifest = app(DemoSeedManifest::class);
        $rows = $manifest->rows();
        $pivots = $manifest->pivots();
        $files = $manifest->files();

        $this->checkCompletion($rows, $pivots, $files);
        $this->checkTrackedRows($rows);
        $this->checkCoverage($rows, $pivots);
        $this->checkFiles($files);
        $this->checkForeignKeys();
        $this->checkVisibleLabels();
        $this->checkEntryPointIsolation();

        $this->table(
            ['Check', 'Result'],
            [
                ['Manifest version', (string) ($manifest->completionVersion() ?? 'missing')],
                ['Tracked rows', (string) count($rows)],
                ['Tracked pivots', (string) count($pivots)],
                ['Tracked files', (string) count($files)],
                ['Errors', (string) count($this->errors)],
                ['Warnings', (string) count($this->warnings)],
            ],
        );

        foreach ($this->warnings as $warning) {
            $this->warn($warning);
        }

        foreach ($this->errors as $error) {
            $this->error($error);
        }

        if ($this->errors !== []) {
            return self::FAILURE;
        }

        $this->info('Demo dataset verification passed.');

        return self::SUCCESS;
    }

    /**
     * @param  list<array{table: string, id_column: string, id: string, natural_key: string}>  $rows
     * @param  list<array{table: string, match: array<string, mixed>}>  $pivots
     * @param  list<array{disk: string, path: string, checksum: string, natural_key: string}>  $files
     */
    private function checkCompletion(array $rows, array $pivots, array $files): void
    {
        $manifest = app(DemoSeedManifest::class);

        if ($manifest->completionVersion() === null) {
            $this->errors[] = 'The manifest has no completion marker; the dataset may be partially seeded.';
        }

        if ($manifest->settingValue(DemoSeedManifest::GROUP, 'run:completed_at') === null) {
            $this->warnings[] = 'The manifest has no completion timestamp.';
        }

        if ($rows === [] || $pivots === [] || $files === []) {
            $this->errors[] = 'The manifest is missing rows, pivots or files.';
        }
    }

    /** @param  list<array{table: string, id_column: string, id: string, natural_key: string}>  $rows */
    private function checkTrackedRows(array $rows): void
    {
        $seen = [];

        foreach ($rows as $row) {
            $identity = $row['table'].':'.$row['id'];

            if (isset($seen[$identity])) {
                $this->errors[] = "Duplicate ownership marker for {$identity}.";
            }

            $seen[$identity] = true;

            if (! Schema::hasTable($row['table'])) {
                $this->errors[] = "Ownership marker references missing table [{$row['table']}].";

                continue;
            }

            if (! DB::table($row['table'])->where($row['id_column'], $row['id'])->exists()) {
                $this->errors[] = "Ownership marker {$identity} does not resolve to a row.";
            }
        }
    }

    /**
     * @param  list<array{table: string, id_column: string, id: string, natural_key: string}>  $rows
     * @param  list<array{table: string, match: array<string, mixed>}>  $pivots
     */
    private function checkCoverage(array $rows, array $pivots): void
    {
        $tracked = [];

        foreach ($rows as $row) {
            $tracked[$row['table']] = ($tracked[$row['table']] ?? 0) + 1;
        }

        foreach ($pivots as $pivot) {
            $tracked[$pivot['table']] = ($tracked[$pivot['table']] ?? 0) + 1;
        }

        foreach (self::REQUIRED_TRACKED_TABLES as $table) {
            if (($tracked[$table] ?? 0) === 0) {
                $this->errors[] = "No demo-owned rows are tracked for [{$table}].";
            }
        }
    }

    /** @param  list<array{disk: string, path: string, checksum: string, natural_key: string}>  $files */
    private function checkFiles(array $files): void
    {
        foreach ($files as $file) {
            try {
                $disk = Storage::disk($file['disk']);

                if (! $disk->exists($file['path'])) {
                    $this->errors[] = "Tracked file [{$file['path']}] is missing.";

                    continue;
                }

                $contents = $disk->get($file['path']);

                if (! is_string($contents) || hash('sha256', $contents) !== $file['checksum']) {
                    $this->errors[] = "Tracked file [{$file['path']}] failed checksum verification.";

                    continue;
                }

                $absolutePath = $disk->path($file['path']);
                $image = @getimagesize($absolutePath);

                if ($image === false || ($image['mime'] ?? null) !== 'image/jpeg') {
                    $this->errors[] = "Tracked file [{$file['path']}] is not a readable JPEG.";
                }
            } catch (Throwable $exception) {
                $this->errors[] = "Tracked file [{$file['path']}] could not be verified: ".$exception->getMessage();
            }
        }
    }

    private function checkForeignKeys(): void
    {
        foreach (self::FOREIGN_KEY_CHECKS as $label => [$table, $childColumn, $parentTable, $parentColumn]) {
            if (! Schema::hasTable($table) || ! Schema::hasTable($parentTable)) {
                $this->warnings[] = "Foreign-key check [{$label}] was skipped because a table is missing.";

                continue;
            }

            $orphans = DB::table($table)
                ->whereNotNull($table.'.'.$childColumn)
                ->leftJoin($parentTable, $table.'.'.$childColumn, '=', $parentTable.'.'.$parentColumn)
                ->whereNull($parentTable.'.'.$parentColumn)
                ->count();

            if ($orphans > 0) {
                $this->errors[] = "Foreign-key check [{$label}] found {$orphans} orphaned row(s).";
            }
        }
    }

    private function checkVisibleLabels(): void
    {
        foreach (self::VISIBLE_LABEL_COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $matches = DB::table($table)
                    ->whereNotNull($column)
                    ->where(function ($query) use ($column): void {
                        foreach (['DEMO DATA', 'FICTIONAL PROJECT', 'SAMPLE REPORT', 'Demo', 'Rehearsal', 'fictional', 'تجريبي', 'تجريبية', 'افتراضي', 'افتراضية', 'بيانات تجريبية', 'مشروع افتراضي'] as $needle) {
                            $query->orWhere($column, 'like', '%'.$needle.'%');
                        }
                    })
                    ->count();

                if ($matches > 0) {
                    $this->errors[] = "Visible demo wording found in {$table}.{$column} ({$matches} row(s)).";
                }
            }
        }
    }

    private function checkEntryPointIsolation(): void
    {
        $path = base_path('database/seeders/DatabaseSeeder.php');
        $source = @file_get_contents($path);

        if (! is_string($source)) {
            $this->warnings[] = 'DatabaseSeeder.php could not be read for isolation verification.';

            return;
        }

        if (str_contains($source, 'DemoDatabaseSeeder::class')) {
            $this->errors[] = 'DatabaseSeeder still calls the dedicated demo seeder.';
        }
    }
}
