<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintContactMethod;
use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use Database\Factories\ComplaintFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Complaint extends Model
{
    /** @use HasFactory<ComplaintFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'reference',
        'category',
        'subject',
        'description',
        'locale',
        'related_project',
        'location',
        'status',
        'priority',
        'is_anonymous',
        'complainant_name',
        'preferred_contact_method',
        'email',
        'phone',
        'preferred_locale',
        'assigned_to',
        'resolved_at',
        'resolution_details',
        'ip_hash',
        'user_agent',
    ];

    /** @var list<string> */
    protected $hidden = [
        'complainant_name',
        'preferred_contact_method',
        'email',
        'phone',
        'preferred_locale',
        'resolution_details',
        'ip_hash',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'category' => ComplaintCategory::class,
            'preferred_contact_method' => ComplaintContactMethod::class,
            'status' => ComplaintStatus::class,
            'priority' => ComplaintPriority::class,
            'is_anonymous' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ComplaintNote::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ComplaintStatusHistory::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ComplaintAttachment::class);
    }

    public function isAnonymous(): bool
    {
        return (bool) $this->is_anonymous;
    }

    /** @param Builder<self> $query */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }
}
