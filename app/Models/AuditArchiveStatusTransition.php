<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditArchiveStatusTransition extends Model
{
    use HasFactory;

    public const ACTOR_SYSTEM = 'system';

    public const ACTOR_USER = 'user';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'actor_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AuditArchive, $this> */
    public function archive(): BelongsTo
    {
        return $this->belongsTo(AuditArchive::class, 'audit_archive_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
