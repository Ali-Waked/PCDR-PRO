<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\EmailMessagePresenter;
use Illuminate\Mail\Mailable;
use Illuminate\Support\HtmlString;

/**
 * Synchronous so the CMS can report the real delivery result. Content is
 * rendered by TemplateRenderer before the mailable is constructed, then
 * placed in the shared institutional layout at send time.
 */
final class InquiryReplyMail extends Mailable
{
    public function __construct(
        public readonly string $emailSubject,
        public readonly string $renderedBody,
        public readonly ?string $recipientLocale = null,
        public readonly ?string $reference = null,
        private ?EmailMessagePresenter $presenter = null,
    ) {}

    public function build(): self
    {
        $presenter = $this->presenter ?? app(EmailMessagePresenter::class);
        $locale = $presenter->normalizeLocale($this->recipientLocale);

        return $this
            ->subject($this->emailSubject)
            ->view([
                'html' => new HtmlString($presenter->document($this->renderedBody, $locale, [
                    'title' => $this->emailSubject,
                    'reference' => $this->reference,
                ])),
                'text' => new HtmlString($presenter->plainText($this->renderedBody)),
            ]);
    }
}
