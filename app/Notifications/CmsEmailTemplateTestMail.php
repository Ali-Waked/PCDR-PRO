<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\EmailMessagePresenter;
use Illuminate\Mail\Mailable;
use Illuminate\Support\HtmlString;

/**
 * Sends the currently previewed template through the real delivery path so
 * administrators can verify the CID logo in their own inbox.
 */
final class CmsEmailTemplateTestMail extends Mailable
{
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $renderedBody,
        public readonly ?string $recipientLocale = null,
        private ?EmailMessagePresenter $presenter = null,
    ) {}

    public function build(): self
    {
        $presenter = $this->presenter ?? app(EmailMessagePresenter::class);
        $locale = $presenter->normalizeLocale($this->recipientLocale);

        return $this
            ->subject($this->subjectLine)
            ->view([
                'html' => new HtmlString($presenter->document($this->renderedBody, $locale, [
                    'title' => $this->subjectLine,
                ])),
                'text' => new HtmlString($presenter->plainText($this->renderedBody)),
            ]);
    }
}
