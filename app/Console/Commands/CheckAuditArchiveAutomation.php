<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateAuditArchiveJob;
use App\Models\AuditArchive;
use App\Services\AuditArchiveAutomationService;
use App\Services\AuditArchiveService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('pcdr:audit:automation:check {--force : Retry failed automatic archives}')]
#[Description('Check Website Settings and dispatch due audit archive generation jobs')]
final class CheckAuditArchiveAutomation extends Command
{
    public function handle(
        AuditArchiveAutomationService $automation,
        AuditArchiveService $archives,
    ): int {
        $config = $automation->configuration();

        if (! $config['enabled']) {
            $this->info('Automatic audit archiving is disabled.');

            return self::SUCCESS;
        }

        try {
            $periods = $automation->duePeriods();
            $dispatched = 0;

            foreach ($periods as $period) {
                $existing = AuditArchive::query()->where('period_key', $period)->first();

                if ($existing !== null && $existing->status === AuditArchive::STATUS_FAILED && ! (bool) $this->option('force')) {
                    continue;
                }

                $force = $existing !== null && $existing->status === AuditArchive::STATUS_FAILED;
                $operationKey = 'auto:'.$period;

                $archive = $archives->prepareArchive(
                    $period,
                    null,
                    $force,
                    AuditArchive::SOURCE_AUTOMATIC,
                    $operationKey,
                    null,
                    $force ? 'Automatic retry of a failed archive' : null,
                );

                GenerateAuditArchiveJob::dispatch(
                    $archive->id,
                    $period,
                    null,
                    AuditArchive::SOURCE_AUTOMATIC,
                    (int) $archive->attempts + 1,
                    ['operation_key' => $operationKey],
                );

                $dispatched++;
            }

            $automation->recordCheck($periods, $dispatched);

            $this->info($dispatched === 0
                ? 'No automatic archives are due.'
                : "Dispatched {$dispatched} automatic archive generation job(s).");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $automation->recordFailure($exception);
            $this->error('Automatic archive check failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
