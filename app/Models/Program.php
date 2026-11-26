<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use HasFactory;
    use HasTranslations;
    use Publishable;
    use SoftDeletes;

    protected $fillable = [
        'strategic_pillar_id', 'status', 'published_at', 'start_date', 'end_date',
        'featured_image_id', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function translationModel(): string
    {
        return ProgramTranslation::class;
    }

    public function translationForeignKey(): string
    {
        return 'program_id';
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(StrategicPillar::class, 'strategic_pillar_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }
}
