<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Carbon\CarbonImmutable;
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
        $boundary = CarbonImmutable::now($this->scheduleTimezone())->format('Y-m-d H:i:s');

        return $query->where('is_published', true)
            ->where(function (Builder $query) use ($boundary): void {
                $query->whereNull('display_start_at')->orWhere('display_start_at', '<=', $boundary);
            })
            ->where(function (Builder $query) use ($boundary): void {
                $query->whereNull('display_end_at')->orWhere('display_end_at', '>=', $boundary);
            });
    }

    /**
     * Classify a record once so management counts and public eligibility cannot
     * disagree about what "hidden", "scheduled", "expired" and "eligible" mean.
     */
    public function scheduleState(): string
    {
        if (! $this->is_published) {
            return 'hidden';
        }

        $now = CarbonImmutable::now($this->scheduleTimezone());
        $start = $this->scheduleBoundary($this->display_start_at, $now);
        $end = $this->scheduleBoundary($this->display_end_at, $now);

        if ($start !== null && $start->greaterThan($now)) {
            return 'scheduled';
        }

        if ($end !== null && $end->lessThan($now)) {
            return 'expired';
        }

        return 'eligible';
    }

    public function isEligibleNow(): bool
    {
        return $this->scheduleState() === 'eligible';
    }

    /** Stored boundaries are full-day strings in the configured timezone. */
    private function scheduleTimezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    private function scheduleBoundary(mixed $value, CarbonImmutable $now): ?CarbonImmutable
    {
        if ($value === null || ! method_exists($value, 'format')) {
            return null;
        }

        return CarbonImmutable::parse((string) $value->format('Y-m-d H:i:s'), $now->timezoneName);
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
     * Hero slides are inline content except for the two presentation controls
     * and legacy rows that were seeded into hero by the ticker seeder. Keys are
     * kept for history, but the registry decides public slide membership.
     */
    public function isHeroSlide(): bool
    {
        if (! $this->isInline()) {
            return false;
        }

        $key = (string) $this->item_key;

        if ($key === '' || in_array($key, (array) config('cms_structure.hero_non_slide_keys', []), true)) {
            return false;
        }

        foreach ((array) config('cms_structure.item_prefixes.ticker', []) as $prefix) {
            if (str_starts_with($key, (string) $prefix)) {
                return false;
            }
        }

        return true;
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
