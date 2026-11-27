<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteOrganization extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $fillable = ['logo_media_id', 'destination_url'];

    public function translationModel(): string
    {
        return WebsiteOrganizationTranslation::class;
    }

    public function translationForeignKey(): string
    {
        return 'website_organization_id';
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }
}
