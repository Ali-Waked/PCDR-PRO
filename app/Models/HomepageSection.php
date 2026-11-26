<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomepageSection extends Model
{
    use HasFactory;
    use HasTranslations;
    use Publishable;

    protected $fillable = [
        'key', 'type', 'status', 'published_at', 'is_enabled', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'is_enabled' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function translationModel(): string
    {
        return HomepageSectionTranslation::class;
    }

    public function translationForeignKey(): string
    {
        return 'homepage_section_id';
    }

    public function items(): HasMany
    {
        return $this->hasMany(HomepageSectionItem::class)->orderBy('sort_order');
    }
}
