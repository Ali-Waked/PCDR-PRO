<?php

declare(strict_types=1);

namespace App\Enums;

enum ComplaintContactMethod: string
{
    case Email = 'email';
    case Phone = 'phone';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
