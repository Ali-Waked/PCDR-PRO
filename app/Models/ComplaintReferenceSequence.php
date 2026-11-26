<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplaintReferenceSequence extends Model
{
    protected $primaryKey = 'year';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'year',
        'next_value',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'next_value' => 'integer',
        ];
    }
}
