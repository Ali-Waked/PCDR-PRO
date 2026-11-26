<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\EmailMessagePresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AccountEmailChangeNoticeNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $mailLocale) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $presenter = app(EmailMessagePresenter::class);
        $locale = $presenter->normalizeLocale($this->mailLocale);

        $message = (new MailMessage)
            ->subject((string) __('email.account.notice_subject', [], $locale))
            ->line((string) __('email.account.notice_intro', [], $locale))
            ->line((string) __('email.account.notice_outro', [], $locale));

        return $presenter->institutionalMailMessage($message, $locale);
    }
}
