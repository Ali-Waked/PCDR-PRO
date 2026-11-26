<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\ProjectStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory;
    use HasTranslations;
    use SoftDeletes;

    protected $fillable = [
        'program_id', 'strategic_pillar_id', 'status', 'workflow_status',
        'published_at', 'start_date', 'end_date', 'featured_image_id', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'workflow_status' => ContentStatus::class,
            'published_at' => 'datetime',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('workflow_status', ContentStatus::Published->value)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeVisibleToPublic(Builder $query): Builder
    {
        return $query->published();
    }

    public function isPublished(): bool
    {
        return $this->workflow_status === ContentStatus::Published
            && ($this->published_at === null || $this->published_at->lessThanOrEqualTo(now()));
    }

    public function translationModel(): string
    {
        return ProjectTranslation::class;
    }

    public function translationForeignKey(): string
    {
        return 'project_id';
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(StrategicPillar::class, 'strategic_pillar_id');
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'project_media')->withPivot('usage', 'sort_order');
    }

    public function statusLabel(?string $locale = null): string
    {
        return $this->status->label($locale ?? app()->getLocale());
    }
}
