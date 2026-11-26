<?php

declare(strict_types=1);

namespace App\Enums;

enum ComplaintStatus: string
{
    case New = 'new';
    case UnderReview = 'under_review';
    case InProgress = 'in_progress';
    case WaitingInformation = 'waiting_information';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Rejected = 'rejected';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return list<string> */
    public function allowedTransitions(): array
    {
        return array_values(array_filter(
            (array) config("complaints.status_transitions.{$this->value}", []),
            static fn (mixed $value): bool => is_string($value) && self::tryFrom($value) !== null,
        ));
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status->value, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this === self::Closed || $this === self::Rejected;
    }
}
