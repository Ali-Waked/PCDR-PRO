<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AuditArchiveService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class GenerateAuditArchiveJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 540;

    public int $uniqueFor = 600;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly int $archiveId,
        public readonly ?string $month,
        public readonly ?int $initiatedBy,
        public readonly string $source,
        public readonly int $expectedAttempt,
        public readonly array $options = [],
    ) {
        $connection = config('audit_archive.queue.connection');
        if (is_string($connection) && $connection !== '') {
            $this->onConnection($connection);
        }

        $this->onQueue((string) config('audit_archive.queue.queue', 'audit'));
    }

    public function handle(AuditArchiveService $archives): void
    {
        $archives->processArchive(
            $this->archiveId,
            $this->month,
            $this->initiatedBy,
            $this->source,
            $this->expectedAttempt,
        );
    }

    public function failed(Throwable $exception): void
    {
        $archives = app(AuditArchiveService::class);
        $archive = $archives->findArchive($this->archiveId);

        if ($archive === null || $archive->status === 'verified') {
            return;
        }

        $archives->markJobFailure($archive, $exception->getMessage());
    }

    public function uniqueId(): string
    {
        return 'audit-archive-job:'.$this->archiveId.':'.$this->expectedAttempt;
    }
}
