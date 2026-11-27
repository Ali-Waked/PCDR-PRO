<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Setting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Explicit, restart-safe preview initialization.
 *
 * Migrations always run; preview seed data runs once, only when opted in via
 * PCDR_PREVIEW_INIT=true. Nothing here is destructive (no fresh/wipe/reset).
 */
final class InitializePreviewDatabaseCommand extends Command
{
    protected $signature = 'pcdr:preview:initialize {--no-seed : Run migrations only}';

    protected $description = 'Apply migrations and, for preview only, seed reference data once';

    public function handle(): int
    {
        $this->info('Applying database migrations...');
        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        $this->line(trim(Artisan::output()));

        if ($this->option('no-seed') || ! config('preview.initialize')) {
            $this->info('Preview seeding is disabled; initialization complete.');

            return self::SUCCESS;
        }

        $group = (string) config('preview.marker.group');
        $key = (string) config('preview.marker.key');

        if (Schema::hasTable('settings')
            && Setting::query()->where('group', $group)->where('key', $key)->exists()) {
            $this->info('Preview database is already initialized; skipping seeders.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($group, $key): void {
                Artisan::call('db:seed', [
                    '--class' => DatabaseSeeder::class,
                    '--force' => true,
                    '--no-interaction' => true,
                ]);

                Setting::query()->updateOrCreate(
                    ['group' => $group, 'key' => $key],
                    [
                        'type' => 'string',
                        'value' => now()->toISOString(),
                        'is_public' => false,
                    ],
                );
            });
        } catch (RuntimeException $exception) {
            // Conflicts (existing owner/email) are reported, never fatal to boot.
            $this->warn($exception->getMessage());
            $this->warn('Preview seeding was skipped; the service can still start.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Preview initialization failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Preview database initialized successfully.');

        return self::SUCCESS;
    }
}
