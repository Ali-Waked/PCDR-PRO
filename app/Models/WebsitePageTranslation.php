<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebsitePageTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['website_page_id', 'locale', 'name', 'seo_title', 'seo_description'];
}
