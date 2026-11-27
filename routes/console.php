<?php

declare(strict_types=1);

use App\Console\Commands\AuditCleanupDue;
use App\Console\Commands\CheckAuditArchiveAutomation;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The due-check command is intentionally lightweight: it reads Website
 * Settings, determines completed unarchived months, and dispatches queue jobs.
 * Archive generation never runs inside the scheduler request.
 *
 * Production must run a scheduler (schedule:work or cron schedule:run) and a
 * dedicated queue worker for the "audit" queue.
 */
Schedule::command(CheckAuditArchiveAutomation::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->onOneServer()
    ->runInBackground();

Schedule::command(AuditCleanupDue::class)
    ->dailyAt((string) config('audit_archive.schedule.cleanup_time', '03:30'))
    ->withoutOverlapping(60)
    ->onOneServer()
    ->runInBackground()
    ->when((bool) config('audit_archive.enabled', true));
