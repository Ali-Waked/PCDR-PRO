<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintContactMethod;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\User;
use App\Notifications\ComplaintStatusChangedNotification;
use App\Notifications\ComplaintSubmittedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Routes complaint notifications without placing private complaint content in
 * notification payloads. Email delivery is opt-in and never gates submission.
 */
final class ComplaintNotificationService
{
    public function __construct(private readonly RbacService $rbac) {}

    public function submitted(Complaint $complaint): void
    {
        $recipients = $this->staffRecipients();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new ComplaintSubmittedNotification(
            $complaint->reference,
            $complaint->id,
            $complaint->category instanceof ComplaintCategory
                ? $complaint->category->value
                : (string) $complaint->category,
        ));
    }

    public function statusChanged(Complaint $complaint, string $previousStatus): void
    {
        if (! (bool) config('complaints.notifications.status_email_enabled', false)) {
            return;
        }

        if ($complaint->isAnonymous() || ! $complaint->preferred_contact_method instanceof ComplaintContactMethod
            || $complaint->preferred_contact_method !== ComplaintContactMethod::Email) {
            return;
        }

        $email = (string) $complaint->email;
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $locale = in_array($complaint->preferred_locale, (array) config('website.locales', ['ar', 'en']), true)
            ? (string) $complaint->preferred_locale
            : (string) $complaint->locale;

        $notification = new ComplaintStatusChangedNotification(
            $complaint->reference,
            $complaint->status instanceof ComplaintStatus ? $complaint->status->value : (string) $complaint->status,
            $previousStatus,
            $locale,
        );

        Notification::route('mail', $email)->notify($notification->locale($locale));
    }

    /** @return Collection<int, User> */
    private function staffRecipients(): Collection
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->get()
            ->filter(fn (User $user): bool => $this->rbac->has($user, 'complaints.view_all'))
            ->values();
    }
}
