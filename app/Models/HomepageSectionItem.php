<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomepageSectionItem extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $fillable = [
        'homepage_section_id',
        'item_type',
        'item_id',
        'media_id',
        'item_key',
        'is_published',
        'numeric_value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
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

    public function translationModel(): string
    {
        return HomepageSectionItemTranslation::class;
    }

    public function translationForeignKey(): string
    {
        return 'homepage_section_item_id';
    }

    public function isInline(): bool
    {
        return $this->item_type === 'inline';
    }

    public function resolve(): ?Model
    {
        return match ($this->item_type) {
            'pillar' => StrategicPillar::query()->published()->find($this->item_id),
            'article' => Article::query()->published()->find($this->item_id),
            'publication' => Publication::query()->published()->find($this->item_id),
            'page' => Page::query()->published()->find($this->item_id),
            'project' => Project::query()->published()->find($this->item_id),
            default => null,
        };
    }
}
