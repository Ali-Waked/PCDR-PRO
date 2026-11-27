<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebsiteOrganizationTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_organization_id',
        'locale',
        'title',
        'description',
        'vision_title',
        'vision',
        'mission_title',
        'mission',
    ];
}
