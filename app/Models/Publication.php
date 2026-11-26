<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Publication extends Model
{
    use HasFactory;
    use HasTranslations;
    use Publishable;
    use SoftDeletes;

    protected $fillable = [
        'category', 'status', 'published_at', 'publication_date',
        'strategic_pillar_id', 'document_media_id', 'cover_media_id',
        'version', 'is_restricted', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'publication_date' => 'date',
            'is_restricted' => 'boolean',
        ];
    }

    public function translationModel(): string
    {
        return PublicationTranslation::class;
    }

    public function translationForeignKey(): string
    {
        return 'publication_id';
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(StrategicPillar::class, 'strategic_pillar_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'document_media_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'publication_media')->withPivot('usage', 'sort_order');
    }

    public function categoryLabel(string $locale): string
    {
        return __("website.publication.categories.{$this->category}", [], $locale);
    }
}
