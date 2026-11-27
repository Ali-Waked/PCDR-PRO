<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateAuditArchiveJob;
use App\Models\AuditArchive;
use App\Services\AuditArchiveService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('pcdr:audit:archive {month? : Archive a specific YYYY-MM month; defaults to the previous complete month} {--force : Retry a pending or failed archive} {--queue : Dispatch archive generation to the queue without blocking the command}')]
#[Description('Create and verify a private monthly audit log archive')]
final class ArchiveAuditMonth extends Command
{
    public function handle(AuditArchiveService $archives): int
    {
        if (! (bool) config('audit_archive.enabled', true)) {
            $this->error('Audit archiving is disabled by configuration.');

            return self::FAILURE;
        }

        $month = $this->argument('month');
        $month = is_string($month) && $month !== '' ? $month : null;
        $force = (bool) $this->option('force');

        try {
            if ((bool) $this->option('queue')) {
                $archive = $archives->prepareArchive(
                    $month,
                    null,
                    $force,
                    AuditArchive::SOURCE_MANUAL,
                    null,
                    null,
                    $force ? 'Manual queued retry' : null,
                );

                GenerateAuditArchiveJob::dispatch(
                    $archive->id,
                    $month,
                    null,
                    AuditArchive::SOURCE_MANUAL,
                    (int) $archive->attempts + 1,
                );

                $this->info("Archive {$archive->period_key} queued.");

                return self::SUCCESS;
            }

            $archive = $archives->createArchive($month, null, $force);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Archive {$archive->period_key} verified: {$archive->record_count} records.");

        return self::SUCCESS;
    }
}
