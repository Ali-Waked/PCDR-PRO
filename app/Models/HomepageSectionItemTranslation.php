<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class HomepageSectionItemTranslation extends Model
{
    protected $fillable = [
        'homepage_section_item_id',
        'locale',
        'label',
        'title',
        'body',
        'display_value',
        'url',
        'date_label',
        'cta_label',
        'caption',
        'unit',
    ];
}
