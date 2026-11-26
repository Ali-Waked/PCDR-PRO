<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Optional status notice for a complainant who opted into email contact.
 *
 * It contains only the reference and the new status. Subject lines, narrative
 * text, internal notes, attachment names and contact details are deliberately
 * excluded so a forwarded or intercepted message cannot expose the complaint.
 */
final class ComplaintStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $reference,
        public readonly string $status,
        public readonly ?string $previousStatus,
        public readonly string $locale,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        if (! (bool) config('complaints.notifications.status_email_enabled', false)) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = __('website.ui.complaints.statuses.'.$this->status, [], $this->locale);

        return (new MailMessage)
            ->subject(__('website.ui.complaints.status_mail.subject', ['reference' => $this->reference], $this->locale))
            ->line(__('website.ui.complaints.status_mail.greeting', [], $this->locale))
            ->line(__('website.ui.complaints.status_mail.body', [
                'reference' => $this->reference,
                'status' => is_string($status) ? $status : $this->status,
            ], $this->locale))
            ->line(__('website.ui.complaints.status_mail.footer', [], $this->locale));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'key' => 'complaint.status_changed',
            'reference' => $this->reference,
            'status' => $this->status,
            'previous_status' => $this->previousStatus,
        ];
    }
}
