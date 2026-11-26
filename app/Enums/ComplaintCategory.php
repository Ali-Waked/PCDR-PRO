<?php

declare(strict_types=1);

namespace App\Enums;

enum ComplaintCategory: string
{
    case ServiceDelivery = 'service_delivery';
    case StaffConduct = 'staff_conduct';
    case Assistance = 'assistance';
    case Accessibility = 'accessibility';
    case DataPrivacy = 'data_privacy';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
