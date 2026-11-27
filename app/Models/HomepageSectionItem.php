<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomepageSectionItem extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $fillable = [
        'homepage_section_id',
        'item_type',
        'item_id',
        'media_id',
        'parent_item_id',
        'item_key',
        'tab',
        'is_featured',
        'motif_key',
        'is_published',
        'display_start_at',
        'display_end_at',
        'numeric_value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'tab' => 'string',
            'is_featured' => 'boolean',
            'motif_key' => 'string',
            'is_published' => 'boolean',
            'display_start_at' => 'datetime',
            'display_end_at' => 'datetime',
            'numeric_value' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(HomepageSection::class, 'homepage_section_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /** @param Builder<self> $query */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_item_id');
    }

    /** @param Builder<self> $query */
    public function scopeEligible(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function (Builder $query): void {
                $query->whereNull('display_start_at')->orWhere('display_start_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('display_end_at')->orWhere('display_end_at', '>=', now());
            });
    }

    public function isEligibleNow(): bool
    {
        if (! $this->is_published) {
            return false;
        }

        if ($this->display_start_at !== null && $this->display_start_at->isFuture()) {
            return false;
        }

        return $this->display_end_at === null || $this->display_end_at->isPast() === false;
    }

    public function translationModel(): string
    {
        return HomepageSectionItemTranslation::class;
    }

    public function translationForeignKey(): string
    {
        return 'homepage_section_item_id';
    }

    private ?Model $resolvedRecord = null;

    private bool $resolutionLoaded = false;

    public function isInline(): bool
    {
        return $this->item_type === 'inline';
    }

    /**
     * Attach a record already loaded by the public query so relationship rows
     * do not trigger one query each while a page is rendered.
     */
    public function useResolved(?Model $model): void
    {
        $this->resolvedRecord = $model;
        $this->resolutionLoaded = true;
    }

    public function resolve(): ?Model
    {
        if ($this->resolutionLoaded) {
            return $this->resolvedRecord;
        }

        return match ($this->item_type) {
            'pillar' => StrategicPillar::query()->published()->find($this->item_id),
            'article' => Article::query()->published()->find($this->item_id),
            'publication' => Publication::query()->published()->find($this->item_id),
            'page' => Page::query()->published()->find($this->item_id),
            'project' => Project::query()->published()->find($this->item_id),
            'program' => Program::query()->published()->find($this->item_id),
            'partner' => DirectoryPartner::query()->published()->find($this->item_id),
            default => null,
        };
    }
}
