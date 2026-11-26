<?php

declare(strict_types=1);

namespace App\Notifications\Phase5;

use App\Services\EmailMessagePresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;

/** Queued, content-free mail: subjects and bodies are fixed localized notices. */
final class Phase5Mail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $titleKey,
        public readonly string $previewKey,
        public readonly ?string $recipientLocale = null,
    ) {
        $this->afterCommit();
    }

    public function build(): self
    {
        $presenter = app(EmailMessagePresenter::class);
        $locale = $presenter->normalizeLocale($this->recipientLocale);
        $shortKey = str_starts_with($this->titleKey, 'phase5.notification.')
            ? substr($this->titleKey, strlen('phase5.notification.'))
            : (str_starts_with($this->titleKey, 'phase5.')
                ? substr($this->titleKey, strlen('phase5.'))
                : $this->titleKey);
        $key = 'phase5.'.$shortKey;
        $subject = Lang::has($key, $locale)
            ? (string) __($key, [], $locale)
            : (string) __('phase5.created', [], $locale);
        $body = (string) __('phase5.privacy_notice', [], $locale);
        $bodyHtml = '<p style="margin:0 0 14px;">'.e($subject).'</p>'
            .$presenter->noticePanel($body, $locale);

        return $this
            ->subject($subject)
            ->view([
                'html' => new HtmlString($presenter->document($bodyHtml, $locale, ['title' => $subject])),
                'text' => new HtmlString($subject."\n\n".$body),
            ]);
    }
}
