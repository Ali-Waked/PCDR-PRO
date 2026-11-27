<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateAuditArchiveJob;
use App\Models\AuditArchive;
use App\Models\AuditArchiveStatusTransition;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\AccessManagementService;
use App\Services\AuditArchiveAutomationService;
use App\Services\AuditArchiveService;
use App\Services\AuditArchiveStatusService;
use App\Services\SiteSettings;
use App\Support\SiteSettingsCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

final class AuditArchiveManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', '12345678901234567890123456789012');
        $this->withoutVite();
        Notification::fake();
        Storage::fake('private');
    }

    public function test_store_queues_a_pending_archive_without_generating_it(): void
    {
        $this->travelTo('2026-11-24 12:00:00');
        Queue::fake();

        $owner = User::factory()->platformOwner()->create();
        $event = $this->createAuditLog('2026-10-05 10:00:00');

        $this->actingAs($owner)
            ->post('/ar/admin/audit-archives', ['month' => '2026-10'])
            ->assertRedirect();

        $archive = AuditArchive::query()->where('period_key', '2026-10')->firstOrFail();

        $this->assertSame(AuditArchive::STATUS_PENDING, $archive->status);
        $this->assertSame(AuditArchive::SOURCE_MANUAL, $archive->source);
        $this->assertSame(0, $archive->attempts);
        $this->assertNotNull($archive->queued_at);
        $this->assertNull($archive->started_at);
        $this->assertNull($archive->file_size);

        Queue::assertPushed(GenerateAuditArchiveJob::class, function (GenerateAuditArchiveJob $job) use ($archive): bool {
            return $job->archiveId === $archive->id
                && $job->month === '2026-10'
                && $job->source === AuditArchive::SOURCE_MANUAL
                && $job->expectedAttempt === 1
                && $job->queue === 'audit';
        });

        $this->assertSame(1, AuditLog::query()->whereKey($event->id)->whereNull('archive_id')->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'audit_archives.created')->count());
    }

    public function test_store_validates_month_and_force_reason(): void
    {
        $owner = User::factory()->platformOwner()->create();
        $this->actingAs($owner);

        $this->post('/ar/admin/audit-archives', ['month' => '10/2026'])
            ->assertSessionHasErrors('month');

        $this->post('/ar/admin/audit-archives', ['month' => '2026-10', 'force' => true])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, AuditArchive::query()->count());
    }

    public function test_queued_job_generates_and_verifies_the_archive(): void
    {
        $this->travelTo('2026-11-24 12:00:00');
        $event = $this->createAuditLog('2026-10-05 10:00:00', 'orders.created');
        $archives = app(AuditArchiveService::class);

        $archive = $archives->prepareArchive(
            '2026-10',
            null,
            false,
            AuditArchive::SOURCE_MANUAL,
            'manual:2026-10',
        );

        (new GenerateAuditArchiveJob(
            $archive->id,
            '2026-10',
            null,
            AuditArchive::SOURCE_MANUAL,
            1,
        ))->handle(app(AuditArchiveService::class));

        $archive->refresh();

        $this->assertSame(AuditArchive::STATUS_VERIFIED, $archive->status);
        $this->assertSame(1, $archive->attempts);
        $this->assertSame(1, $archive->record_count);
        $this->assertNotNull($archive->started_at);
        $this->assertNotNull($archive->finished_at);
        $this->assertNull($archive->failed_at);
        $this->assertNotEmpty($archive->checksums);
        $this->assertTrue(Storage::disk('private')->exists($archive->file_path));

        $this->assertSame(AuditArchive::SOURCE_MANUAL, $archive->manifest['archive_source']);
        $this->assertSame(AuditArchive::FORMAT_ZIP, $archive->manifest['format']);
        $this->assertSame('2026-10', $archive->manifest['period']);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $event->id,
            'archive_id' => $archive->id,
        ]);
        $this->assertSame(2, AuditArchiveStatusTransition::query()
            ->where('audit_archive_id', $archive->id)
            ->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'audit_archives.created')->count());
    }

    public function test_stale_job_attempt_is_rejected_without_changing_the_archive(): void
    {
        $this->travelTo('2026-11-24 12:00:00');
        $archives = app(AuditArchiveService::class);
        $archive = $archives->prepareArchive('2026-10');

        try {
            $archives->processArchive($archive->id, '2026-10', null, AuditArchive::SOURCE_MANUAL, 2);
            $this->fail('A stale attempt should not be processed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('no longer current', $exception->getMessage());
        }

        $archive->refresh();

        $this->assertSame(AuditArchive::STATUS_PENDING, $archive->status);
        $this->assertSame(0, $archive->attempts);
        $this->assertNull($archive->started_at);
        $this->assertSame(0, AuditArchiveStatusTransition::query()->where('audit_archive_id', $archive->id)->count());
    }

    public function test_job_failure_is_recorded_once_and_a_failed_archive_can_be_retried(): void
    {
        $this->travelTo('2026-11-24 12:00:00');
        $manager = $this->userWithPermission('audit_archives.manage');
        $this->actingAs($manager);
        $this->createAuditLog('2026-10-05 10:00:00');

        $archives = app(AuditArchiveService::class);
        $archive = $archives->prepareArchive('2026-10', $manager->id);

        (new GenerateAuditArchiveJob(
            $archive->id,
            '2026-10',
            $manager->id,
            AuditArchive::SOURCE_MANUAL,
            1,
        ))->failed(new RuntimeException('worker exploded'));

        $archive->refresh();

        $this->assertSame(AuditArchive::STATUS_FAILED, $archive->status);
        $this->assertStringContainsString('worker exploded', (string) $archive->error);
        $this->assertNotNull($archive->failed_at);
        $this->assertSame(1, AuditLog::query()->where('action', 'audit_archives.failed')->count());

        $retried = $archives->prepareArchive(
            '2026-10',
            $manager->id,
            true,
            AuditArchive::SOURCE_MANUAL,
            'manual-retry:2026-10',
            $manager,
            'Verified worker error and retried.',
        );

        $this->assertSame(AuditArchive::STATUS_PENDING, $retried->status);
        $this->assertSame(1, $retried->attempts);
        $this->assertNull($retried->error);

        $retried = $archives->processArchive(
            $retried->id,
            '2026-10',
            $manager->id,
            AuditArchive::SOURCE_MANUAL,
            2,
        );

        $this->assertSame(AuditArchive::STATUS_VERIFIED, $retried->status);
        $this->assertSame(2, $retried->attempts);
        $this->assertDatabaseHas('audit_archive_status_transitions', [
            'audit_archive_id' => $archive->id,
            'from_status' => AuditArchive::STATUS_FAILED,
            'to_status' => AuditArchive::STATUS_PENDING,
            'reason' => 'Verified worker error and retried.',
            'actor_type' => 'user',
        ]);
        $this->assertSame(1, AuditLog::query()->where('action', 'audit_archives.failed')->count());
    }

    public function test_late_failure_callback_does_not_fail_verified_or_pruned_archives(): void
    {
        $this->travelTo('2026-11-24 12:00:00');
        $this->createAuditLog('2026-10-05 10:00:00');
        $archives = app(AuditArchiveService::class);

        $archive = $archives->createArchive('2026-10');
        $before = AuditLog::query()->where('action', 'audit_archives.failed')->count();

        $archives->markJobFailure($archive, 'late failure');

        $archive->refresh();
        $this->assertSame(AuditArchive::STATUS_VERIFIED, $archive->status);
        $this->assertNull($archive->error);
        $this->assertSame($before, AuditLog::query()->where('action', 'audit_archives.failed')->count());

        app(AuditArchiveStatusService::class)->transition(
            $archive,
            AuditArchive::STATUS_PRUNED,
            null,
            null,
            ['operation' => 'test'],
        );
        $archives->markJobFailure($archive, 'late failure');

        $archive->refresh();
        $this->assertSame(AuditArchive::STATUS_PRUNED, $archive->status);
        $this->assertNull($archive->error);
        $this->assertSame($before, AuditLog::query()->where('action', 'audit_archives.failed')->count());
    }

    public function test_status_guard_blocks_invalid_paths_and_requires_a_manual_reason(): void
    {
        $manager = $this->userWithPermission('audit_archives.manage');
        $this->actingAs($manager);
        $statuses = app(AuditArchiveStatusService::class);

        $archive = AuditArchive::factory()->create([
            'period_key' => '2026-10',
            'status' => AuditArchive::STATUS_PENDING,
            'verified_at' => null,
            'failed_at' => null,
            'pruned_at' => null,
        ]);

        $statuses->transition($archive, AuditArchive::STATUS_PROCESSING);
        $this->assertSame(AuditArchive::STATUS_PROCESSING, $archive->refresh()->status);

        $statuses->transition($archive, AuditArchive::STATUS_VERIFIED);
        $this->assertSame(AuditArchive::STATUS_VERIFIED, $archive->refresh()->status);

        try {
            $statuses->transition($archive, AuditArchive::STATUS_PROCESSING);
            $this->fail('Verified archives must not return to processing.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('not allowed', $exception->getMessage());
        }

        $failed = AuditArchive::factory()->create([
            'period_key' => '2026-09',
            'status' => AuditArchive::STATUS_FAILED,
            'verified_at' => null,
            'failed_at' => now(),
        ]);

        try {
            $statuses->transition($failed, AuditArchive::STATUS_PENDING, $manager);
            $this->fail('Manual transitions require a reason.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('reason is required', $exception->getMessage());
        }

        $pruned = AuditArchive::factory()->pruned()->create(['period_key' => '2026-08']);

        try {
            $statuses->transition($pruned, AuditArchive::STATUS_VERIFIED, $manager, 'restore');
            $this->fail('Pruned archives must be final.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('not allowed', $exception->getMessage());
        }

        $this->assertSame(AuditArchive::STATUS_FAILED, $failed->refresh()->status);
        $this->assertSame(AuditArchive::STATUS_PRUNED, $pruned->refresh()->status);
    }

    public function test_index_combines_filters_sorting_and_pagination(): void
    {
        $viewer = $this->userWithPermission('audit_archives.view');

        for ($month = 1; $month <= 12; $month++) {
            AuditArchive::factory()->failed()->create([
                'period_key' => sprintf('2026-%02d', $month),
                'period_start' => sprintf('2026-%02d-01 00:00:00', $month),
                'name' => 'manual archive '.sprintf('2026-%02d', $month).'.zip',
                'source' => AuditArchive::SOURCE_MANUAL,
                'record_count' => $month * 100,
                'file_size' => $month * 1000,
            ]);
        }

        AuditArchive::factory()->create([
            'period_key' => '2025-12',
            'period_start' => '2025-12-01 00:00:00',
            'name' => 'automatic archive 2025-12.zip',
            'source' => AuditArchive::SOURCE_AUTOMATIC,
            'record_count' => 9999,
        ]);

        $this->actingAs($viewer)
            ->get('/ar/admin/audit-archives?status=failed&source=manual&month=2026-03&year=2026&search=manual&sort=period&direction=asc&per_page=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Admin/AuditArchives/Index')
                ->where('archives.data', fn ($rows): bool => count($rows) === 1
                    && $rows[0]['period_key'] === '2026-03'
                    && $rows[0]['source'] === AuditArchive::SOURCE_MANUAL)
                ->where('filters', fn ($filters): bool => $filters['status'] === 'failed'
                    && $filters['month'] === '2026-03'
                    && $filters['year'] === '2026'
                    && $filters['source'] === 'manual'
                    && $filters['search'] === 'manual'
                    && $filters['per_page'] === 10)
                ->where('years', ['2026', '2025']));

        $this->actingAs($viewer)
            ->get('/ar/admin/audit-archives?sort=records&direction=asc&per_page=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('archives.total', 13)
                ->where('archives.per_page', 10)
                ->where('archives.data', fn ($rows): bool => count($rows) === 10
                    && $rows[0]['record_count'] === 100
                    && $rows[9]['record_count'] === 1000)
                ->where('automation.enabled', false)
                ->where('can', fn ($can): bool => $can['view'] === true
                    && $can['download'] === false
                    && $can['manage'] === false));
    }

    public function test_show_and_download_enforce_separate_permissions_and_integrity(): void
    {
        $this->travelTo('2026-11-24 12:00:00');
        $event = $this->createAuditLog('2026-10-05 10:00:00');
        $archive = app(AuditArchiveService::class)->createArchive('2026-10');

        $viewer = $this->userWithPermission('audit_archives.view');
        $downloader = $this->userWithPermission('audit_archives.download');
        $unprivileged = User::factory()->create();

        $this->actingAs($viewer)
            ->get('/ar/admin/audit-archives/'.$archive->id)
            ->assertOk()
            ->assertJsonPath('archive.period_key', '2026-10')
            ->assertJsonCount(2, 'transitions');

        $this->actingAs($viewer)
            ->get('/ar/admin/audit-archives/'.$archive->id.'/download')
            ->assertForbidden();

        $this->actingAs($unprivileged)
            ->get('/ar/admin/audit-archives/'.$archive->id)
            ->assertForbidden();

        $response = $this->actingAs($downloader)
            ->get('/ar/admin/audit-archives/'.$archive->id.'/download')
            ->assertOk();

        $response->assertHeader('Content-Type', 'application/zip');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'audit_archives.download',
            'subject_id' => (string) $archive->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'id' => $event->id,
            'archive_id' => $archive->id,
        ]);

        $archive->update(['status' => AuditArchive::STATUS_PENDING]);
        $this->actingAs($downloader)
            ->get('/ar/admin/audit-archives/'.$archive->id.'/download')
            ->assertStatus(303);
    }

    public function test_archive_name_can_be_edited_and_is_audited(): void
    {
        $manager = $this->userWithPermission('audit_archives.manage');
        $archive = AuditArchive::factory()->create([
            'period_key' => '2026-10',
            'name' => 'audit-logs-2026-10.zip',
        ]);

        $this->actingAs($manager)
            ->put('/ar/admin/audit-archives/'.$archive->id, ['name' => 'October evidence package'])
            ->assertRedirect();

        $this->assertSame('October evidence package', $archive->refresh()->name);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'audit_archives.updated',
            'subject_id' => (string) $archive->id,
        ]);

        $this->put('/ar/admin/audit-archives/'.$archive->id, ['name' => 'bad/name'])
            ->assertSessionHasErrors('name');
    }

    public function test_failed_archive_can_be_manually_verified_after_inspection(): void
    {
        $this->travelTo('2026-11-24 12:00:00');
        $manager = $this->userWithPermission('audit_archives.manage');
        $this->actingAs($manager);
        $this->createAuditLog('2026-10-05 10:00:00');

        $archive = app(AuditArchiveService::class)->createArchive('2026-10');
        $archive->update([
            'status' => AuditArchive::STATUS_FAILED,
            'failed_at' => now(),
            'error' => 'Simulated worker failure.',
        ]);

        $this->post('/ar/admin/audit-archives/'.$archive->id.'/transition', [
            'to_status' => AuditArchive::STATUS_VERIFIED,
            'reason' => 'Private file and checksums inspected.',
        ])->assertRedirect();

        $archive->refresh();

        $this->assertSame(AuditArchive::STATUS_VERIFIED, $archive->status);
        $this->assertNull($archive->error);
        $this->assertDatabaseHas('audit_archive_status_transitions', [
            'audit_archive_id' => $archive->id,
            'from_status' => AuditArchive::STATUS_FAILED,
            'to_status' => AuditArchive::STATUS_VERIFIED,
            'reason' => 'Private file and checksums inspected.',
            'actor_type' => 'user',
        ]);
        $this->assertSame(1, AuditLog::query()->where('action', 'audit_archives.verified')->count());

        $this->post('/ar/admin/audit-archives/'.$archive->id.'/transition', [
            'to_status' => AuditArchive::STATUS_PROCESSING,
            'reason' => 'Should be rejected.',
        ])->assertSessionHasErrors('to_status');
    }

    public function test_automation_settings_are_private_in_cms_and_absent_from_public_payload(): void
    {
        $owner = User::factory()->platformOwner()->create();
        $this->actingAs($owner);

        $this->get('/ar/cms/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Cms/Settings/Index')
                ->where('settings', fn ($settings): bool => collect($settings)
                    ->filter(fn (array $setting): bool => str_starts_with($setting['key'], 'audit_automation.'))
                    ->count() === 7
                    && collect($settings)
                        ->filter(fn (array $setting): bool => str_starts_with($setting['key'], 'audit_automation.'))
                        ->every(fn (array $setting): bool => $setting['public'] === false))
                ->has('automation'));

        $this->put('/ar/cms/settings', ['settings' => [
            ['key' => 'audit_automation.enabled', 'value' => '1'],
            ['key' => 'audit_automation.interval_months', 'value' => '2'],
            ['key' => 'audit_automation.execution_day', 'value' => '9'],
            ['key' => 'audit_automation.execution_time', 'value' => '04:15'],
            ['key' => 'audit_automation.timezone', 'value' => 'Europe/Berlin'],
            ['key' => 'audit_automation.notifications_enabled', 'value' => '1'],
            ['key' => 'audit_automation.lookback_months', 'value' => '4'],
        ]])->assertRedirect();

        foreach ([
            'audit_automation.enabled' => '1',
            'audit_automation.interval_months' => '2',
            'audit_automation.execution_day' => '9',
            'audit_automation.execution_time' => '04:15',
            'audit_automation.timezone' => 'Europe/Berlin',
            'audit_automation.notifications_enabled' => '1',
            'audit_automation.lookback_months' => '4',
        ] as $key => $value) {
            $this->assertDatabaseHas('website_settings', [
                'key' => $key,
                'value' => $value,
                'is_public' => false,
            ]);
        }

        $publicValues = app(SiteSettings::class)->publicValues('ar');
        foreach (SiteSettingsCatalog::keys() as $key) {
            if (str_starts_with($key, 'audit_automation.')) {
                $this->assertArrayNotHasKey($key, $publicValues);
            }
        }

        $this->assertSame(1, AuditLog::query()->where('action', 'cms.settings.update')->count());
    }

    public function test_due_periods_honor_execution_day_interval_lookback_and_active_archives(): void
    {
        $this->setAutomationValue('audit_automation.enabled', '1');
        $this->setAutomationValue('audit_automation.interval_months', '1');
        $this->setAutomationValue('audit_automation.lookback_months', '3');
        $this->setAutomationValue('audit_automation.execution_day', '2');
        $this->setAutomationValue('audit_automation.timezone', 'UTC');

        $automation = app(AuditArchiveAutomationService::class);

        $this->assertSame([], $automation->duePeriods(Carbon::parse('2026-11-01 12:00:00')));
        $this->assertSame(
            ['2026-08', '2026-09', '2026-10'],
            $automation->duePeriods(Carbon::parse('2026-11-20 12:00:00')),
        );

        AuditArchive::factory()->create([
            'period_key' => '2026-10',
            'status' => AuditArchive::STATUS_VERIFIED,
            'source' => AuditArchive::SOURCE_AUTOMATIC,
        ]);
        AuditArchive::factory()->create([
            'period_key' => '2026-09',
            'status' => AuditArchive::STATUS_PENDING,
            'source' => AuditArchive::SOURCE_AUTOMATIC,
        ]);

        $this->assertSame(['2026-08'], $automation->duePeriods(Carbon::parse('2026-11-20 12:00:00')));

        AuditArchive::factory()->failed()->create([
            'period_key' => '2026-08',
            'source' => AuditArchive::SOURCE_AUTOMATIC,
        ]);
        $this->assertSame(['2026-08'], $automation->duePeriods(Carbon::parse('2026-11-20 12:00:00')));

        AuditArchive::query()->where('period_key', '2026-08')->update([
            'status' => AuditArchive::STATUS_VERIFIED,
        ]);
        $this->assertSame([], $automation->duePeriods(Carbon::parse('2026-11-20 12:00:00')));

        $this->setAutomationValue('audit_automation.enabled', '0');
        $this->assertSame([], $automation->duePeriods(Carbon::parse('2026-11-20 12:00:00')));
    }

    public function test_scheduler_dispatch_skips_failures_unless_force_is_supplied(): void
    {
        $this->travelTo('2026-11-20 06:00:00');
        Queue::fake();
        $this->enableAutomation();

        foreach (['2026-08', '2026-09'] as $period) {
            AuditArchive::factory()->create([
                'period_key' => $period,
                'period_start' => $period.'-01 00:00:00',
                'status' => AuditArchive::STATUS_VERIFIED,
                'source' => AuditArchive::SOURCE_AUTOMATIC,
            ]);
        }

        $failed = AuditArchive::factory()->failed()->create([
            'period_key' => '2026-10',
            'period_start' => '2026-10-01 00:00:00',
            'source' => AuditArchive::SOURCE_AUTOMATIC,
            'failed_at' => now()->subDay(),
        ]);

        $this->artisan('pcdr:audit:automation:check')
            ->expectsOutputToContain('No automatic archives are due.')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(AuditArchive::STATUS_FAILED, $failed->refresh()->status);

        $this->artisan('pcdr:audit:automation:check --force')
            ->expectsOutputToContain('Dispatched 1 automatic archive generation job(s).')
            ->assertSuccessful();

        Queue::assertPushed(GenerateAuditArchiveJob::class, function (GenerateAuditArchiveJob $job) use ($failed): bool {
            return $job->archiveId === $failed->id
                && $job->month === '2026-10'
                && $job->source === AuditArchive::SOURCE_AUTOMATIC
                && $job->expectedAttempt === 2
                && $job->queue === 'audit'
                && ($job->options['operation_key'] ?? null) === 'auto:2026-10';
        });

        $this->assertSame(AuditArchive::STATUS_PENDING, $failed->refresh()->status);
        $this->assertSame(AuditArchive::SOURCE_AUTOMATIC, $failed->source);
        $this->assertDatabaseHas('audit_archive_status_transitions', [
            'audit_archive_id' => $failed->id,
            'from_status' => AuditArchive::STATUS_FAILED,
            'to_status' => AuditArchive::STATUS_PENDING,
            'actor_type' => 'system',
        ]);
        $this->assertSame(2, AuditLog::query()
            ->where('action', AuditArchiveAutomationService::ACTION_CHECKED)
            ->count());
    }

    public function test_automation_status_reports_health_from_checks_and_archive_history(): void
    {
        $this->enableAutomation(['enabled' => '1']);
        $automation = app(AuditArchiveAutomationService::class);

        $this->assertSame('not_started', $automation->status()['health']);

        $automation->recordCheck(['2026-10'], 0);
        $this->assertSame('healthy', $automation->status()['health']);

        AuditArchive::factory()->create([
            'period_key' => '2026-09',
            'source' => AuditArchive::SOURCE_AUTOMATIC,
            'status' => AuditArchive::STATUS_VERIFIED,
            'verified_at' => now()->subDay(),
        ]);
        AuditArchive::factory()->failed()->create([
            'period_key' => '2026-10',
            'source' => AuditArchive::SOURCE_AUTOMATIC,
            'failed_at' => now(),
            'error' => 'Archive generation failed.',
        ]);

        $this->assertSame('degraded', $automation->status()['health']);
        $this->assertSame('Archive generation failed.', $automation->status()['last_failure']);

        $this->setAutomationValue('audit_automation.enabled', '0');
        $this->assertSame('disabled', $automation->status()['health']);
    }

    public function test_frontend_exposes_bilingual_help_filters_confirmations_and_blue_alert(): void
    {
        $page = (string) file_get_contents(base_path('resources/js/Pages/Admin/AuditArchives/Index.vue'));

        foreach ([
            'pc-workflow-alert--info',
            'openHelp',
            'confirm(',
            'queueGenerate',
            'applyFilters',
            'clearFilters',
            'sortIndicator',
            '<Pagination',
            "localePath('/cms/settings')",
            'audit_archives_page.automation_last_check',
        ] as $needle) {
            $this->assertStringContainsString($needle, $page);
        }

        $this->assertStringNotContainsString('name="queue"', $page);

        foreach (['en', 'ar'] as $locale) {
            $translations = (string) file_get_contents(base_path("lang/{$locale}/ui.php"));

            foreach ([
                "'info_title' =>",
                "'info_message' =>",
                "'help_title' =>",
                "'help_automation' =>",
                "'search_placeholder' =>",
                "'confirm_generate_message' =>",
                "'automation_last_check' =>",
            ] as $key) {
                $this->assertStringContainsString($key, $translations);
            }
        }

        $css = (string) file_get_contents(base_path('resources/css/dashboard.css'));
        $this->assertStringContainsString('.pc-workflow-alert--info', $css);
        $this->assertStringContainsString('.pc-audit-automation__grid', $css);
        $this->assertStringContainsString('.pc-audit-sort', $css);
    }

    public function test_operator_documentation_contains_exact_worker_cron_and_environment_commands(): void
    {
        $guide = (string) file_get_contents(base_path('docs/AUDIT_ARCHIVES_USER_GUIDE.md'));
        $this->assertStringContainsString('## English', $guide);
        $this->assertStringContainsString('## العربية', $guide);
        $this->assertStringContainsString('Help / User guide', $guide);
        $this->assertStringContainsString('زر المساعدة', $guide);

        $operations = (string) file_get_contents(base_path('docs/AUDIT_ARCHIVES_OPERATIONS.md'));

        foreach ([
            'php artisan queue:work database --queue=audit --tries=3 --timeout=600',
            '* * * * * php artisan schedule:run >> /dev/null 2>&1',
            'DB_QUEUE_RETRY_AFTER=660',
            'PCDR_AUDIT_QUEUE_NAME=audit',
            'PCDR_AUDIT_QUEUE_CONNECTION=',
            'php artisan pcdr:audit:automation:check --force',
            'php artisan schedule:list',
            'php artisan route:list --name=audit-archives',
        ] as $command) {
            $this->assertStringContainsString($command, $operations);
        }
    }

    private function createAuditLog(string $createdAt, string $action = 'test.event'): AuditLog
    {
        return AuditLog::create([
            'actor_id' => null,
            'action' => $action,
            'subject_type' => 'tests',
            'subject_id' => '1',
            'context' => [],
            'created_at' => $createdAt,
        ]);
    }

    private function enableAutomation(array $overrides = []): void
    {
        $values = array_merge([
            'enabled' => '1',
            'interval_months' => '1',
            'execution_day' => '2',
            'execution_time' => '02:30',
            'timezone' => 'UTC',
            'notifications_enabled' => '1',
            'lookback_months' => '3',
        ], $overrides);

        foreach ($values as $key => $value) {
            $this->setAutomationValue('audit_automation.'.$key, (string) $value);
        }

        $this->assertDatabaseHas('website_settings', [
            'key' => 'audit_automation.enabled',
            'group' => AuditArchiveAutomationService::GROUP,
            'value' => $values['enabled'],
            'is_public' => false,
        ]);
    }

    private function setAutomationValue(string $key, string $value): void
    {
        $meta = SiteSettingsCatalog::find($key);

        WebsiteSetting::query()->updateOrCreate(['key' => $key], [
            'group' => $meta['group'] ?? AuditArchiveAutomationService::GROUP,
            'type' => $meta['type'] ?? 'text',
            'is_public' => false,
            'is_localized' => false,
            'value' => $value,
        ]);
    }

    private function userWithPermission(string $permission): User
    {
        $user = User::factory()->create();
        $role = Role::create([
            'key' => 'audit_test_'.Str::lower(Str::random(10)),
            'name' => 'Audit archive test role',
        ]);
        $record = Permission::query()->firstOrCreate(
            ['key' => $permission],
            ['name' => $permission, 'module' => 'platform'],
        );

        $role->permissions()->attach($record);
        app(AccessManagementService::class)->syncRoles($user, [$role->id]);

        return $user;
    }
}
