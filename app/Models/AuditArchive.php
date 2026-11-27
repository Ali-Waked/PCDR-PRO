<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AuditArchiveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditArchive extends Model
{
    /** @use HasFactory<AuditArchiveFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PRUNED = 'pruned';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_AUTOMATIC = 'automatic';

    public const FORMAT_ZIP = 'zip';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'cutoff_at' => 'datetime',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'failed_at' => 'datetime',
            'verified_at' => 'datetime',
            'pruned_at' => 'datetime',
            'last_cleanup_at' => 'datetime',
            'checksums' => 'array',
            'manifest' => 'array',
            'record_count' => 'integer',
            'file_size' => 'integer',
            'attempts' => 'integer',
            'initiated_by' => 'integer',
        ];
    }

    /** @return HasMany<AuditLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'archive_id');
    }

    /** @return HasMany<AuditCleanupRun, $this> */
    public function cleanupRuns(): HasMany
    {
        return $this->hasMany(AuditCleanupRun::class);
    }

    /** @return BelongsTo<User, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /** @return HasMany<AuditArchiveStatusTransition, $this> */
    public function statusTransitions(): HasMany
    {
        return $this->hasMany(AuditArchiveStatusTransition::class, 'audit_archive_id');
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function periodLabel(): string
    {
        return $this->period_key ?? ($this->period_start?->format('Y-m') ?? '');
    }
}
