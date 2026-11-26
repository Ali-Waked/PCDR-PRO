<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use HasFactory;
    use HasTranslations;
    use Publishable;
    use SoftDeletes;

    protected $fillable = [
        'parent_id', 'status', 'published_at', 'author_id', 'reviewer_id',
        'display_order', 'is_system',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'is_system' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function translationModel(): string
    {
        return PageTranslation::class;
    }

    public function translationForeignKey(): string
    {
        return 'page_id';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'page_media')->withPivot('usage', 'sort_order');
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(ContentRevision::class, 'revisionable');
    }
}
