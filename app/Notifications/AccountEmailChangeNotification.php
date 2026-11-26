<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\EmailMessagePresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

final class AccountEmailChangeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly string $mailLocale,
        public readonly int $expiryMinutes = 60,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $presenter = app(EmailMessagePresenter::class);
        $locale = $presenter->normalizeLocale($this->mailLocale);
        $url = URL::temporarySignedRoute(
            'account.email.change.confirm',
            now()->addMinutes($this->expiryMinutes),
            [
                'locale' => $locale,
                'token' => $this->token,
            ],
        );

        $message = (new MailMessage)
            ->subject((string) __('email.account.change_subject', [], $locale))
            ->line((string) __('email.account.change_intro', [], $locale))
            ->action((string) __('email.account.change_action', [], $locale), $url)
            ->line((string) __('email.account.change_outro', ['count' => $this->expiryMinutes], $locale));

        return $presenter->institutionalMailMessage($message, $locale);
    }
}
