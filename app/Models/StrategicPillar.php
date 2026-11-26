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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StrategicPillar extends Model
{
    use HasFactory;
    use HasTranslations;
    use Publishable;
    use SoftDeletes;

    protected $fillable = [
        'code', 'status', 'published_at', 'featured_image_id',
        'display_order', 'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    /**
     * Search code and translated title, short title, slug and description.
     * Values are bound by Eloquent; LIKE wildcards in user input are escaped.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $needle = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $query) use ($needle): void {
            $query->where('code', 'like', $needle)
                ->orWhereHas('translations', function (Builder $query) use ($needle): void {
                    $query->where('title', 'like', $needle)
                        ->orWhere('short_title', 'like', $needle)
                        ->orWhere('description', 'like', $needle)
                        ->orWhere('slug', 'like', $needle);
                });
        });
    }

    public function scopeWorkflowStatus(Builder $query, ContentStatus|string|null $status): Builder
    {
        $value = $status instanceof ContentStatus ? $status->value : $status;

        if ($value === null || ! in_array($value, ContentStatus::values(), true)) {
            return $query;
        }

        return $query->where('status', $value);
    }

    public function translationModel(): string
    {
        return StrategicPillarTranslation::class;
    }

    public function translationForeignKey(): string
    {
        return 'strategic_pillar_id';
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'pillar_media')->withPivot('usage', 'sort_order');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function objectiveLines(): array
    {
        return $this->splitLines($this->t('objectives'));
    }

    public function targetGroupLines(): array
    {
        return $this->splitLines($this->t('target_groups'));
    }

    private function splitLines(?string $value): array
    {
        if (! $value) {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\R+/u', $value) ?: [])));
    }
}
