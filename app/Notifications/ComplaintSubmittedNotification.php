<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Privacy-preserving internal notice. The complainant's name, contact
 * details, narrative, attachment names and IP address are never placed in the
 * notification payload; staff open the restricted CMS record instead.
 */
final class ComplaintSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $reference,
        public readonly int $complaintId,
        public readonly string $category,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'key' => 'complaint.submitted',
            'reference' => $this->reference,
            'complaint_id' => $this->complaintId,
            'category' => $this->category,
        ];
    }
}
