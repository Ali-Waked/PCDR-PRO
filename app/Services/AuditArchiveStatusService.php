<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditArchive;
use App\Models\AuditArchiveStatusTransition;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * The only supported way to change an audit archive status.
 *
 * The map is deliberately narrow: verified archives are final evidence and
 * pruned archives are never re-opened. Generation and retention transitions
 * are system-owned; human transitions require an explicit reason.
 */
final class AuditArchiveStatusService
{
    /** @var array<string, array<string, string>> */
    private const TRANSITIONS = [
        AuditArchive::STATUS_PENDING => [
            AuditArchive::STATUS_PROCESSING => 'system',
            AuditArchive::STATUS_FAILED => 'system',
        ],
        AuditArchive::STATUS_PROCESSING => [
            AuditArchive::STATUS_VERIFIED => 'system',
            AuditArchive::STATUS_FAILED => 'system',
        ],
        AuditArchive::STATUS_FAILED => [
            AuditArchive::STATUS_PENDING => 'manual',
            AuditArchive::STATUS_PROCESSING => 'system',
            AuditArchive::STATUS_VERIFIED => 'manual',
        ],
        AuditArchive::STATUS_VERIFIED => [
            AuditArchive::STATUS_PRUNED => 'system',
        ],
        AuditArchive::STATUS_PRUNED => [],
    ];

    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function transition(
        AuditArchive $archive,
        string $toStatus,
        ?User $actor = null,
        ?string $reason = null,
        array $context = [],
    ): AuditArchive {
        $fromStatus = (string) $archive->status;
        $mode = self::TRANSITIONS[$fromStatus][$toStatus] ?? null;

        if ($mode === null) {
            throw new RuntimeException("The transition from {$fromStatus} to {$toStatus} is not allowed.");
        }

        // A null actor means this transition is system-owned (queue worker,
        // scheduler or retention). Human-initiated transitions must be
        // authorized and explained; the FormRequest enforces the same rule
        // before the controller reaches this service.
        if ($mode === 'manual' && $actor !== null) {
            Gate::authorize('manage', $archive);
            if (trim((string) $reason) === '') {
                throw new RuntimeException('A reason is required for this status change.');
            }
        }

        $actorId = $actor?->id;
        $actorType = $actor === null ? AuditArchiveStatusTransition::ACTOR_SYSTEM : AuditArchiveStatusTransition::ACTOR_USER;
        $recordedReason = $mode === 'manual' && $actor !== null ? trim((string) $reason) : null;

        $attributes = ['status' => $toStatus];

        if ($toStatus === AuditArchive::STATUS_PROCESSING) {
            $attributes['started_at'] = $context['started_at'] ?? now();
        }

        if ($toStatus === AuditArchive::STATUS_VERIFIED) {
            $attributes['verified_at'] = $context['verified_at'] ?? now();
            $attributes['finished_at'] = $context['finished_at'] ?? now();
            $attributes['error'] = null;
        }

        if ($toStatus === AuditArchive::STATUS_FAILED) {
            $attributes['failed_at'] = $context['failed_at'] ?? now();
            $attributes['finished_at'] = $context['finished_at'] ?? now();
        }

        if ($toStatus === AuditArchive::STATUS_PRUNED) {
            $attributes['pruned_at'] = $context['pruned_at'] ?? now();
        }

        $archive->update($attributes);

        AuditArchiveStatusTransition::create([
            'audit_archive_id' => $archive->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'reason' => $recordedReason,
            'context' => $context,
            'created_at' => now(),
        ]);

        $this->audit->record(
            'audit_archives.status_changed',
            'audit_archives',
            (string) $archive->id,
            ['status' => $fromStatus],
            ['status' => $toStatus],
            [
                'period' => $archive->period_key,
                'actor_type' => $actorType,
                'reason' => $recordedReason,
                'description' => 'Audit archive status changed',
            ],
        );

        return $archive->refresh();
    }

    /** @return list<array{to: string, mode: string}> */
    public function allowedTransitions(AuditArchive $archive): array
    {
        return collect(self::TRANSITIONS[(string) $archive->status] ?? [])
            ->map(fn (string $mode, string $to): array => ['to' => $to, 'mode' => $mode])
            ->values()
            ->all();
    }

    public function requiresReason(AuditArchive $archive, string $toStatus): bool
    {
        return (self::TRANSITIONS[(string) $archive->status][$toStatus] ?? null) === 'manual';
    }
}
