<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditArchive;
use App\Models\AuditCleanupRun;
use App\Models\AuditLog;
use App\Models\AuditLogHold;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

final class AuditRetentionService
{
    public const CONFIRMATION_PHRASE = 'DELETE AUDIT LOGS';

    public function __construct(
        private readonly AuditArchiveService $archives,
        private readonly AuditArchiveStatusService $statuses,
        private readonly AuditService $audit,
        private readonly AuditLifecycleNotifier $notifier,
    ) {}

    /** @return array<string, mixed> */
    public function configuration(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'hot_months' => $this->hotMonths(),
            'min_archive_age_days' => max(0, (int) config('audit_archive.retention.min_archive_age_days', 7)),
            'require_verified_archive' => (bool) config('audit_archive.retention.require_verified_archive', true),
            'require_dry_run' => (bool) config('audit_archive.retention.require_dry_run', true),
            'batch_size' => max(50, (int) config('audit_archive.retention.batch_size', 500)),
            'confirmation_phrase' => self::CONFIRMATION_PHRASE,
        ];
    }

    public function isEnabled(): bool
    {
        return (bool) config('audit_archive.retention.enabled', false) && $this->hotMonths() > 0;
    }

    public function hotMonths(): int
    {
        $months = config('audit_archive.retention.hot_months');

        return is_numeric($months) ? max(1, (int) $months) : 0;
    }

    /** @return Collection<int, AuditArchive> */
    public function dueArchives(): Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        return AuditArchive::query()
            ->where('status', AuditArchive::STATUS_VERIFIED)
            ->where('period_end', '<=', $this->retentionBoundary())
            ->where('verified_at', '<=', now()->subDays(
                max(0, (int) config('audit_archive.retention.min_archive_age_days', 7)),
            ))
            ->orderBy('period_start')
            ->get();
    }

    /** @return Collection<int, AuditCleanupRun> */
    public function dryRun(?int $archiveId = null): Collection
    {
        $runs = collect();
        $archives = $this->dueArchives();

        foreach ($archives as $archive) {
            if ($archiveId !== null && $archive->id !== $archiveId) {
                continue;
            }

            $run = AuditCleanupRun::create([
                'audit_archive_id' => $archive->id,
                'mode' => AuditCleanupRun::MODE_DRY_RUN,
                'status' => AuditCleanupRun::STATUS_PROCESSING,
                'period_start' => $archive->period_start,
                'period_end' => $archive->period_end,
                'started_at' => now(),
            ]);

            $report = $this->reportArchive($archive);
            $report['enabled'] = $this->isEnabled();
            $report['hot_months'] = $this->hotMonths();
            $report['retention_boundary'] = $this->retentionBoundary()->toISOString();

            $run->update([
                'status' => AuditCleanupRun::STATUS_COMPLETED,
                'eligible_count' => $report['eligible_count'],
                'skipped_hold_count' => $report['held_count'],
                'report' => $report,
                'finished_at' => now(),
            ]);

            $runs->push($run->refresh());
        }

        if ($runs->isEmpty()) {
            $runs->push(AuditCleanupRun::create([
                'mode' => AuditCleanupRun::MODE_DRY_RUN,
                'status' => AuditCleanupRun::STATUS_COMPLETED,
                'started_at' => now(),
                'finished_at' => now(),
                'report' => [
                    'enabled' => $this->isEnabled(),
                    'hot_months' => $this->hotMonths(),
                    'retention_boundary' => $this->retentionBoundary()->toISOString(),
                    'archives' => [],
                ],
            ]));
        }

        return $runs;
    }

    public function execute(AuditArchive $archive, string $confirmation, ?int $requestedBy = null): AuditCleanupRun
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Audit retention is disabled. No records may be deleted.');
        }

        if (! hash_equals(self::CONFIRMATION_PHRASE, trim($confirmation))) {
            throw new RuntimeException('Destructive cleanup requires explicit confirmation.');
        }

        if (! $archive->isVerified() || ! (bool) config('audit_archive.retention.require_verified_archive', true)) {
            throw new RuntimeException('Only a verified archive may be cleaned up.');
        }

        if ($archive->period_end?->greaterThan($this->retentionBoundary())) {
            throw new RuntimeException('The archive has not reached the configured retention period.');
        }

        if ($this->freshDryRun($archive) === null && (bool) config('audit_archive.retention.require_dry_run', true)) {
            throw new RuntimeException('A current dry-run report is required before cleanup.');
        }

        $lock = Cache::lock('pcdr:audit-cleanup:'.$archive->id, 600);

        return $lock->block(0, function () use ($archive, $requestedBy): AuditCleanupRun {
            $run = AuditCleanupRun::create([
                'audit_archive_id' => $archive->id,
                'mode' => AuditCleanupRun::MODE_EXECUTE,
                'status' => AuditCleanupRun::STATUS_PROCESSING,
                'period_start' => $archive->period_start,
                'period_end' => $archive->period_end,
                'requested_by' => $requestedBy,
                'started_at' => now(),
            ]);

            try {
                $integrity = $this->archives->verifyArchive($archive, false);
                $holds = $this->activeHolds();
                $total = $this->logQuery($archive)->count();

                if ($this->hasGlobalHold()) {
                    throw new RuntimeException('A global legal hold prevents cleanup.');
                }

                $eligibleQuery = $this->logQuery($archive);
                $this->applyHoldConstraints($eligibleQuery, $holds);
                $eligibleCount = (clone $eligibleQuery)->count();

                $deletedCount = 0;
                $batchSize = max(50, (int) config('audit_archive.retention.batch_size', 500));

                $eligibleQuery
                    ->select(['id'])
                    ->orderBy('id')
                    ->chunkById($batchSize, function ($logs) use (&$deletedCount): void {
                        AuditLog::withCleanupDeletion(function () use ($logs, &$deletedCount): void {
                            foreach ($logs as $log) {
                                if ($log->delete()) {
                                    $deletedCount++;
                                }
                            }
                        });
                    });

                $skippedHoldCount = $total - $eligibleCount;
                $remaining = $this->logQuery($archive)->count();

                if ($remaining !== $skippedHoldCount || $deletedCount !== $eligibleCount) {
                    throw new RuntimeException('Cleanup counts did not reconcile; archive state was not pruned.');
                }

                $report = [
                    'archive' => $archive->period_key,
                    'eligible_count' => $eligibleCount,
                    'deleted_count' => $deletedCount,
                    'held_count' => $skippedHoldCount,
                    'remaining_count' => $remaining,
                    'integrity' => $integrity,
                    'hold_labels' => $holds->pluck('label')->unique()->values()->all(),
                ];

                $pruned = $remaining === 0;

                if ($pruned) {
                    $this->statuses->transition($archive, AuditArchive::STATUS_PRUNED, null, null, [
                        'operation' => 'retention',
                        'deleted_count' => $deletedCount,
                    ]);
                }

                $archive->update([
                    'last_cleanup_at' => now(),
                    'pruned_at' => $pruned ? $archive->pruned_at : null,
                ]);

                $run->update([
                    'status' => AuditCleanupRun::STATUS_COMPLETED,
                    'eligible_count' => $eligibleCount,
                    'deleted_count' => $deletedCount,
                    'skipped_hold_count' => $skippedHoldCount,
                    'report' => $report,
                    'finished_at' => now(),
                ]);

                $this->notifier->cleanupCompleted($run->refresh());
            } catch (Throwable $exception) {
                $run->update([
                    'status' => AuditCleanupRun::STATUS_FAILED,
                    'error' => mb_substr($exception->getMessage(), 0, 1000),
                    'finished_at' => now(),
                ]);

                $this->notifier->cleanupFailed($run->refresh(), $exception->getMessage());

                throw $exception;
            }

            return $run->refresh();
        });
    }

    /** @return Collection<int, AuditLogHold> */
    public function activeHolds(): Collection
    {
        return AuditLogHold::query()
            ->whereNull('released_at')
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createHold(array $input, ?int $createdBy = null): AuditLogHold
    {
        $scope = (string) ($input['scope_type'] ?? '');
        if (! in_array($scope, [
            AuditLogHold::SCOPE_GLOBAL,
            AuditLogHold::SCOPE_SUBJECT,
            AuditLogHold::SCOPE_ACTOR,
            AuditLogHold::SCOPE_PERIOD,
        ], true)) {
            throw new RuntimeException('Unsupported legal hold scope.');
        }

        $reason = trim((string) ($input['reason'] ?? ''));
        $label = trim((string) ($input['label'] ?? ''));
        if ($reason === '' || $label === '') {
            throw new RuntimeException('A legal hold requires a label and reason.');
        }

        $subjectType = trim((string) ($input['subject_type'] ?? ''));
        $subjectId = trim((string) ($input['subject_id'] ?? ''));
        $actorId = isset($input['actor_id']) && $input['actor_id'] !== '' ? (int) $input['actor_id'] : null;
        $periodStart = $this->parseDate($input['period_start'] ?? null);
        $periodEnd = $this->parseDate($input['period_end'] ?? null);
        $expiresAt = $this->parseDate($input['expires_at'] ?? null, true);

        if ($scope === AuditLogHold::SCOPE_SUBJECT && ($subjectType === '' || $subjectId === '')) {
            throw new RuntimeException('A subject hold requires both subject type and subject ID.');
        }

        if ($scope === AuditLogHold::SCOPE_ACTOR && $actorId === null) {
            throw new RuntimeException('An actor hold requires an actor.');
        }

        if ($scope === AuditLogHold::SCOPE_PERIOD && ($periodStart === null || $periodEnd === null || ! $periodEnd->gt($periodStart))) {
            throw new RuntimeException('A period hold requires a valid start and end date.');
        }

        $hold = AuditLogHold::create([
            'label' => mb_substr($label, 0, 160),
            'reason' => $reason,
            'scope_type' => $scope,
            'subject_type' => $subjectType !== '' ? $subjectType : null,
            'subject_id' => $subjectId !== '' ? $subjectId : null,
            'actor_id' => $actorId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
        ]);

        $this->audit->record('audit_log_holds.created', 'audit_log_holds', (string) $hold->id, null, [
            'label' => $hold->label,
            'scope' => $hold->scope_type,
            'reason' => $hold->reason,
        ], [
            'description' => 'Legal hold created',
        ]);

        return $hold;
    }

    public function releaseHold(int $holdId, ?int $releasedBy = null): AuditLogHold
    {
        $hold = AuditLogHold::query()->findOrFail($holdId);

        if ($hold->released_at !== null) {
            throw new RuntimeException('The legal hold has already been released.');
        }

        $hold->update([
            'released_at' => now(),
            'released_by' => $releasedBy,
        ]);

        $this->audit->record('audit_log_holds.released', 'audit_log_holds', (string) $hold->id, null, [
            'label' => $hold->label,
            'scope' => $hold->scope_type,
        ], [
            'description' => 'Legal hold released',
        ]);

        return $hold->refresh();
    }

    /** @return array<string, mixed> */
    public function describeRun(AuditCleanupRun $run): array
    {
        return [
            'id' => $run->id,
            'archive_id' => $run->audit_archive_id,
            'archive_period' => $run->archive?->period_key,
            'status' => $run->status,
            'mode' => $run->mode,
            'eligible_count' => $run->eligible_count,
            'deleted_count' => $run->deleted_count,
            'skipped_hold_count' => $run->skipped_hold_count,
            'report' => $run->report,
            'error' => $run->error,
            'started_at' => $run->started_at?->toISOString(),
            'finished_at' => $run->finished_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    public function describeHold(AuditLogHold $hold): array
    {
        return [
            'id' => $hold->id,
            'label' => $hold->label,
            'reason' => $hold->reason,
            'scope_type' => $hold->scope_type,
            'subject_type' => $hold->subject_type,
            'subject_id' => $hold->subject_id,
            'actor_id' => $hold->actor_id,
            'period_start' => $hold->period_start?->toISOString(),
            'period_end' => $hold->period_end?->toISOString(),
            'expires_at' => $hold->expires_at?->toISOString(),
            'released_at' => $hold->released_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function reportArchive(AuditArchive $archive): array
    {
        $holds = $this->activeHolds();
        $total = $this->logQuery($archive)->count();
        $eligibleQuery = $this->logQuery($archive);
        $this->applyHoldConstraints($eligibleQuery, $holds);

        $integrity = 'not_checked';
        $integrityError = null;
        try {
            $this->archives->verifyArchive($archive, false);
            $integrity = 'verified';
        } catch (Throwable $exception) {
            $integrity = 'failed';
            $integrityError = mb_substr($exception->getMessage(), 0, 500);
        }

        return [
            'archive_id' => $archive->id,
            'period' => $archive->period_key,
            'record_count' => $archive->record_count,
            'stored_count' => $total,
            'eligible_count' => (clone $eligibleQuery)->count(),
            'held_count' => $total - (clone $eligibleQuery)->count(),
            'integrity' => $integrity,
            'integrity_error' => $integrityError,
        ];
    }

    private function logQuery(AuditArchive $archive): Builder
    {
        return AuditLog::query()
            ->where('archive_id', $archive->id)
            ->whereNotNull('archived_at')
            ->where('created_at', '>=', $archive->period_start)
            ->where('created_at', '<', $archive->period_end);
    }

    /** @param Collection<int, AuditLogHold> $holds */
    private function applyHoldConstraints(Builder $query, Collection $holds): void
    {
        foreach ($holds as $hold) {
            if ($hold->scope_type === AuditLogHold::SCOPE_GLOBAL) {
                $query->whereRaw('0 = 1');

                continue;
            }

            if ($hold->scope_type === AuditLogHold::SCOPE_SUBJECT && $hold->subject_type !== null && $hold->subject_id !== null) {
                // NULL-safe exclusion: logs for other subjects (and logs with
                // no subject) must remain eligible even with a subject hold.
                $query->where(function (Builder $outer) use ($hold): void {
                    $outer->whereNull('subject_type')
                        ->orWhereNull('subject_id')
                        ->orWhere(function (Builder $inner) use ($hold): void {
                            $inner->where('subject_type', '!=', $hold->subject_type)
                                ->orWhere('subject_id', '!=', $hold->subject_id);
                        });
                });

                continue;
            }

            if ($hold->scope_type === AuditLogHold::SCOPE_ACTOR && $hold->actor_id !== null) {
                // System-generated events have no actor and must not be
                // accidentally excluded by SQL's NULL comparison semantics.
                $query->where(function (Builder $outer) use ($hold): void {
                    $outer->whereNull('actor_id')
                        ->orWhere('actor_id', '!=', $hold->actor_id);
                });

                continue;
            }

            if ($hold->scope_type === AuditLogHold::SCOPE_PERIOD && $hold->period_start !== null && $hold->period_end !== null) {
                $query->where(function (Builder $outer) use ($hold): void {
                    $outer->whereNull('created_at')
                        ->orWhere('created_at', '<', $hold->period_start)
                        ->orWhere('created_at', '>=', $hold->period_end);
                });
            }
        }
    }

    private function hasGlobalHold(): bool
    {
        return $this->activeHolds()->contains(fn (AuditLogHold $hold): bool => $hold->scope_type === AuditLogHold::SCOPE_GLOBAL);
    }

    private function freshDryRun(AuditArchive $archive): ?AuditCleanupRun
    {
        return AuditCleanupRun::query()
            ->where('audit_archive_id', $archive->id)
            ->where('mode', AuditCleanupRun::MODE_DRY_RUN)
            ->where('status', AuditCleanupRun::STATUS_COMPLETED)
            ->where('created_at', '>=', now()->subDay())
            ->latest('id')
            ->first();
    }

    private function retentionBoundary(): Carbon
    {
        return now()->subMonths($this->hotMonths())->startOfMonth();
    }

    private function parseDate(mixed $value, bool $allowFuture = false): ?Carbon
    {
        $text = is_string($value) ? trim($value) : '';
        if ($text === '' || preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2})?)?$/', $text) !== 1) {
            return null;
        }

        $date = Carbon::parse($text);
        if (! $allowFuture && $date->isFuture()) {
            return null;
        }

        return $date;
    }
}
