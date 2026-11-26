<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomepageSectionTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'homepage_section_id', 'locale', 'eyebrow', 'title', 'body', 'cta_label', 'cta_link',
        'ticker_label', 'tab_labels', 'video_url', 'video_caption',
    ];

    protected function casts(): array
    {
        return [
            'tab_labels' => 'array',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(HomepageSection::class, 'homepage_section_id');
    }
}
