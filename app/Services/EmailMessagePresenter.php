<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Renders every outbound message through the same institutional email layout.
 *
 * Template bodies remain database-managed content: they are never evaluated as
 * PHP or Blade, and HTML bodies pass through an explicit sanitizer. The trusted
 * table-based shell is assembled here so the CMS preview and every delivered
 * mailable share the same header, typography, buttons and footer.
 */
final class EmailMessagePresenter
{
    private const HTML_PATTERN = '/<\s*\/?\s*(?:p|div|br|h[1-6]|table|ul|ol|li|blockquote|a|span|strong|b|em|i|img|hr)\b/i';

    public function __construct(
        private EmailHtmlSanitizer $sanitizer,
        private EmailLogoService $logo,
    ) {}

    public function isHtml(string $body): bool
    {
        return (bool) preg_match(self::HTML_PATTERN, $body);
    }

    /**
     * The sanitized HTML fragment used inside the shared layout. Plain text
     * keeps the legacy escaping and newline behaviour.
     */
    public function bodyHtml(string $renderedBody): string
    {
        if (! $this->isHtml($renderedBody)) {
            return nl2br(e($renderedBody));
        }

        return $this->sanitizer->clean($renderedBody);
    }

    public function plainText(string $renderedBody): string
    {
        if (! $this->isHtml($renderedBody)) {
            return $renderedBody;
        }

        $text = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $renderedBody) ?? $renderedBody;
        $text = preg_replace('/<\/(?:p|div|h[1-6]|li|blockquote|tr)>/i', "\n\n", $text) ?? $text;
        $text = strip_tags($text);

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Safe HTML for the rich-text editor. Plain-text templates are converted
     * into paragraphs so the existing line structure remains editable.
     */
    public function forEditor(string $body): string
    {
        if ($this->isHtml($body)) {
            return $this->sanitizer->clean($body);
        }

        $lines = preg_split('/\R/u', $body) ?: [];
        $blocks = [];

        foreach ($lines as $line) {
            $blocks[] = trim($line) === ''
                ? '<p><br></p>'
                : '<p>'.e($line).'</p>';
        }

        return implode('', $blocks);
    }

    public function normalizeLocale(?string $locale): string
    {
        $locale = is_string($locale) ? strtolower(trim($locale)) : '';

        return in_array($locale, (array) config('website.locales', ['ar', 'en']), true)
            ? $locale
            : (string) config('website.fallback_locale', 'en');
    }

    /**
     * Build the complete, inline-styled email document shared by delivery and
     * preview. Only $renderedBody is untrusted; every other value is escaped.
     *
     * @param  array{
     *     title?: string,
     *     reference?: string|null,
     *     organizationName?: string|null,
     *     preview?: bool,
     *     imagesBlocked?: bool,
     *     previewSender?: array{name: string, address: string}|null,
     *     previewRecipient?: string|null,
     *     previewTemplateKey?: string|null
     * }  $options
     */
    public function document(string $renderedBody, string $locale, array $options = []): string
    {
        $locale = $this->normalizeLocale($locale);
        $direction = $locale === 'ar' ? 'rtl' : 'ltr';
        $alignStart = $direction === 'rtl' ? 'right' : 'left';
        $colors = $this->colors();
        $font = "font-family:'IBM Plex Sans Arabic',Tahoma,Arial,sans-serif";
        $preview = ($options['preview'] ?? false) === true;
        $imagesBlocked = ($options['imagesBlocked'] ?? false) === true;

        $title = trim((string) ($options['title'] ?? ''));
        $reference = trim((string) ($options['reference'] ?? ''));
        $organization = trim((string) ($options['organizationName'] ?? ''))
            ?: $this->organizationName($locale);
        $shortName = $this->shortName($locale);
        $fullName = $this->organizationName($locale);
        $websiteUrl = $this->websiteUrl();
        $body = $this->bodyHtml($renderedBody);

        $logoWidth = max(1, (int) config('email.brand.logo_width', 120));
        $logoHeight = max(1, (int) config('email.brand.logo_height', 120));
        $logoAlt = $this->logoAlt($locale, $organization);
        $logoSource = $this->logo->source($preview);

        if ($imagesBlocked) {
            $headerLogo = '<div data-email-logo-fallback="true" style="width:'.$logoWidth.'px;'
                .'height:'.$logoHeight.'px;border-radius:60px;background-color:'.$colors['white'].';'
                .'color:'.$colors['navy_900'].';font-size:13px;font-weight:700;line-height:1.3;'
                .'text-align:center;padding:0 4px;box-sizing:border-box;">'
                .e($logoAlt).'</div>';
        } else {
            $headerLogo = '<img data-pcdr-email-logo="true" src="'.e($logoSource).'" '
                .'width="'.$logoWidth.'" height="'.$logoHeight.'" alt="'.e($logoAlt).'" '
                .'style="display:block;border:0;outline:none;text-decoration:none;border-radius:60px;">';
        }

        $headerText = '<div style="margin:0;color:'.$colors['teal_100'].';font-size:10px;'
            .'font-weight:700;letter-spacing:.09em;line-height:1.4;text-transform:uppercase;">'
            .e((string) __('email.layout.eyebrow', [], $locale)).'</div>'
            .'<div style="margin:5px 0 0;color:'.$colors['white'].';font-size:19px;'
            .'font-weight:700;line-height:1.25;">'.e($shortName).'</div>';

        if ($fullName !== '' && $fullName !== $shortName) {
            $headerText .= '<div dir="'.$direction.'" style="margin:4px 0 0;color:'.$colors['teal_100'].';'
                .'font-size:13px;line-height:1.5;text-align:'.$alignStart.';">'.e($fullName).'</div>';
        }

        $headerGap = $direction === 'rtl' ? '0 16px 0 0' : '0 0 0 16px';
        $header = '<tr><td class="pc-email-pad" dir="'.$direction.'" style="padding:26px 32px 22px;'
            .'background-color:'.$colors['navy_900'].';">'
            .'<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" dir="'.$direction.'">'
            .'<tr>'
            .'<td align="'.$alignStart.'" valign="middle" style="width:'.$logoWidth.'px;padding:0;">'.$headerLogo.'</td>'
            .'<td align="'.$alignStart.'" valign="middle" style="padding:'.$headerGap.';">'
            .$headerText.'</td>'
            .'</tr></table></td></tr>';

        $heading = $title === ''
            ? ''
            : '<h1 dir="'.$direction.'" style="margin:0 0 18px;color:'.$colors['navy_900'].';'
                .'font-size:22px;font-weight:700;line-height:1.35;text-align:'.$alignStart.';">'
                .e($title).'</h1>';

        $referencePanel = '';
        if ($reference !== '') {
            $borderSide = $direction === 'rtl' ? 'right' : 'left';
            $referencePanel = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" '
                .'style="margin:0 0 22px;border-'.$borderSide.':3px solid '.$colors['teal'].';'
                .'background-color:'.$colors['sand_100'].';">'
                .'<tr><td dir="'.$direction.'" style="padding:12px 16px;color:'.$colors['navy_800'].';'
                .'font-size:12px;line-height:1.6;text-align:'.$alignStart.';">'
                .'<strong style="color:'.$colors['teal_700'].';">'
                .e((string) __('email.layout.reference', [], $locale)).':</strong> '
                .'<span dir="ltr" style="color:'.$colors['navy_900'].';">'.e($reference).'</span>'
                .'</td></tr></table>';
        }

        $websiteLink = '';
        if ($websiteUrl !== null) {
            $websiteLink = '<div style="margin:8px 0 0;font-size:12px;line-height:1.5;">'
                .'<a href="'.e($websiteUrl).'" style="color:'.$colors['teal_700'].';text-decoration:underline;">'
                .e((string) __('email.layout.website', [], $locale)).'</a></div>';
        }

        $footer = '<tr><td class="pc-email-pad" dir="'.$direction.'" style="padding:20px 32px 24px;'
            .'border-top:1px solid '.$colors['navy_100'].';background-color:'.$colors['sand_100'].';">'
            .'<div style="color:'.$colors['navy_900'].';font-size:13px;font-weight:700;line-height:1.5;'
            .'text-align:'.$alignStart.';">'.e($organization).'</div>'
            .$websiteLink
            .'<div style="margin:10px 0 0;color:'.$colors['navy_800'].';font-size:11px;line-height:1.6;'
            .'text-align:'.$alignStart.';">'.e((string) __('email.layout.footer_note', [], $locale)).'</div>'
            .'</td></tr>';

        $previewBanner = '';
        if (($options['preview'] ?? false) === true) {
            /** @var array{name: string, address: string}|null $sender */
            $sender = $options['previewSender'] ?? null;
            $senderText = $sender === null
                ? ''
                : trim($sender['name']).' <'.trim($sender['address']).'>';
            $recipientText = trim((string) ($options['previewRecipient'] ?? ''));
            $details = [];
            if ($senderText !== '') {
                $details[] = e((string) __('email.layout.preview_from', [], $locale)).': '.e($senderText);
            }
            if ($recipientText !== '') {
                $details[] = e((string) __('email.layout.preview_to', [], $locale)).': '.e($recipientText);
            }
            $keyText = trim((string) ($options['previewTemplateKey'] ?? ''));
            if ($keyText !== '') {
                $details[] = '<span dir="ltr">'.e($keyText).'</span>';
            }

            $previewBanner = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" '
                .'data-email-preview="true" style="max-width:640px;background-color:'.$colors['navy_900'].';'
                .'margin:0 0 14px;">'
                .'<tr><td dir="'.$direction.'" style="padding:10px 16px;color:'.$colors['white'].';'
                .'font-size:11px;line-height:1.6;text-align:'.$alignStart.';">'
                .'<strong>'.e((string) __('email.layout.preview_notice', [], $locale)).'</strong>'
                .($details === [] ? '' : '<div style="margin-top:3px;color:'.$colors['teal_100'].';">'.implode(' &middot; ', $details).'</div>')
                .'</td></tr></table>';
        }

        return '<!doctype html><html lang="'.$locale.'" dir="'.$direction.'">'
            .'<head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<meta name="x-apple-disable-message-reformatting">'
            .'<title>'.e($title).'</title>'
            .'<style type="text/css">'
            .'@media only screen and (max-width:620px){'
            .'.pc-email-container{width:100%!important;}'
            .'.pc-email-pad{padding-left:18px!important;padding-right:18px!important;}'
            .'}'
            .'</style></head>'
            .'<body dir="'.$direction.'" style="margin:0;padding:0;width:100%;background-color:'
            .$colors['sand_100'].';'.$font.';color:'.$colors['navy_900'].';'
            .'-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">'
            .'<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" '
            .'style="width:100%;background-color:'.$colors['sand_100'].';">'
            .'<tr><td align="center" style="padding:28px 12px;">'
            .$previewBanner
            .'<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" '
            .'align="center" class="pc-email-container" data-pcdr-email="layout-v1" '
            .'style="width:640px;max-width:640px;background-color:'.$colors['white'].';'
            .'border:1px solid '.$colors['navy_100'].';">'
            .'<tr><td style="height:4px;line-height:4px;font-size:0;background-color:'.$colors['teal'].';">&nbsp;</td></tr>'
            .$header
            .'<tr><td class="pc-email-pad" dir="'.$direction.'" style="padding:28px 32px 26px;'
            .'background-color:'.$colors['white'].';">'
            .$heading.$referencePanel
            .'<div dir="'.$direction.'" style="color:'.$colors['navy_800'].';font-size:15px;'
            .'line-height:1.75;text-align:'.$alignStart.';overflow-wrap:break-word;">'.$body.'</div>'
            .'</td></tr>'
            .$footer
            .'</table></td></tr></table></body></html>';
    }

    /**
     * @param  array{name: string, address: string}  $sender
     */
    public function previewDocument(
        string $renderedSubject,
        string $renderedBody,
        string $locale,
        array $sender,
        string $recipient,
        string $templateKey,
        bool $imagesBlocked = false,
    ): string {
        return $this->document($renderedBody, $locale, [
            'title' => $renderedSubject,
            'preview' => true,
            'imagesBlocked' => $imagesBlocked,
            'previewSender' => $sender,
            'previewRecipient' => $recipient,
            'previewTemplateKey' => $templateKey,
        ]);
    }

    /** Wrap an existing notification MailMessage in the shared layout. */
    public function institutionalMailMessage(MailMessage $message, string $locale): MailMessage
    {
        $locale = $this->normalizeLocale($locale);
        $data = $message->toArray();
        $before = [];
        $after = [];
        $plainBefore = [];
        $plainAfter = [];

        $greeting = trim((string) ($data['greeting'] ?? ''));
        if ($greeting !== '') {
            $before[] = $this->paragraph($greeting);
            $plainBefore[] = $greeting;
        }

        foreach ((array) ($data['introLines'] ?? []) as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $before[] = $this->paragraph($line);
                $plainBefore[] = $line;
            }
        }

        $button = '';
        $actionText = trim((string) ($data['actionText'] ?? ''));
        $actionUrl = trim((string) ($data['actionUrl'] ?? ''));
        if ($actionText !== '' && $actionUrl !== '') {
            $button = $this->actionButton($actionText, $actionUrl);
            $plainBefore[] = $actionText.': '.$actionUrl;
        }

        foreach ((array) ($data['outroLines'] ?? []) as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $after[] = $this->paragraph($line);
                $plainAfter[] = $line;
            }
        }

        $salutation = trim((string) ($data['salutation'] ?? ''));
        if ($salutation !== '') {
            $after[] = $this->paragraph($salutation);
            $plainAfter[] = $salutation;
        }

        $body = implode('', $before).$button.implode('', $after);
        $plain = implode("\n\n", array_merge($plainBefore, $plainAfter));
        $organization = $this->organizationName($locale);

        if ($organization !== '' && ! str_contains($plain, $organization)) {
            $plain = trim($plain."\n\n".$organization);
        }

        return $message->view([
            'html' => 'emails.unified',
            'text' => 'emails.unified-text',
        ], [
            'emailDocument' => $this->document($body, $locale, [
                'title' => trim((string) ($data['subject'] ?? '')),
            ]),
            'plainText' => $plain,
        ]);
    }

    /** Reusable primary action for fixed notification emails. */
    public function actionButton(string $label, string $url): string
    {
        if (! preg_match('/^(?:https?:|mailto:)/i', trim($url))) {
            return '';
        }

        $colors = $this->colors();

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" '
            .'style="margin:8px 0 22px;">'
            .'<tr><td align="center" bgcolor="'.$colors['teal'].'" '
            .'style="border-radius:4px;background-color:'.$colors['teal'].';">'
            .'<a href="'.e($url).'" style="display:inline-block;padding:12px 22px;'
            .'font-family:\'IBM Plex Sans Arabic\',Tahoma,Arial,sans-serif;font-size:14px;font-weight:700;'
            .'line-height:1.2;color:'.$colors['navy_900'].';text-decoration:none;'
            .'border:1px solid '.$colors['teal_700'].';border-radius:4px;">'
            .e($label).'</a></td></tr></table>';
    }

    /** Reusable highlighted notice for fixed notification emails. */
    public function noticePanel(string $text, string $locale): string
    {
        $locale = $this->normalizeLocale($locale);
        $direction = $locale === 'ar' ? 'rtl' : 'ltr';
        $alignStart = $direction === 'rtl' ? 'right' : 'left';
        $borderSide = $direction === 'rtl' ? 'right' : 'left';
        $colors = $this->colors();

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" '
            .'style="margin:0 0 18px;border-'.$borderSide.':3px solid '.$colors['teal_700'].';'
            .'background-color:'.$colors['teal_100'].';">'
            .'<tr><td dir="'.$direction.'" style="padding:12px 16px;color:'.$colors['navy_800'].';'
            .'font-size:13px;line-height:1.7;text-align:'.$alignStart.';">'
            .e($text).'</td></tr></table>';
    }

    /** @return array<string, string> */
    private function colors(): array
    {
        $configured = (array) config('email.colors', []);

        return [
            'navy' => (string) ($configured['navy'] ?? '#3E5172'),
            'teal' => (string) ($configured['teal'] ?? '#71BCB7'),
            'sand' => (string) ($configured['sand'] ?? '#DAC3A3'),
            'wheat' => (string) ($configured['wheat'] ?? '#D7C5A1'),
            'white' => (string) ($configured['white'] ?? '#FFFFFF'),
            'navy_900' => (string) ($configured['navy_900'] ?? '#25334B'),
            'navy_800' => (string) ($configured['navy_800'] ?? '#30415E'),
            'navy_100' => (string) ($configured['navy_100'] ?? '#E9EDF2'),
            'teal_700' => (string) ($configured['teal_700'] ?? '#397F7B'),
            'teal_100' => (string) ($configured['teal_100'] ?? '#EAF5F4'),
            'sand_100' => (string) ($configured['sand_100'] ?? '#F7F2EA'),
        ];
    }

    private function websiteUrl(): ?string
    {
        $configured = config('email.brand.website_url');

        return is_string($configured) && $this->isPublicHttpsUrl($configured) ? $configured : null;
    }

    private function isPublicHttpsUrl(string $url): bool
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || trim((string) ($parts['host'] ?? '')) === ''
            || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower((string) $parts['host']);
        $localSuffixes = ['.localhost', '.local', '.test', '.invalid', '.example', '.internal'];
        foreach ($localSuffixes as $suffix) {
            if ($host === rtrim($suffix, '.') || str_ends_with($host, $suffix)) {
                return false;
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return (bool) filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );
        }

        return (bool) filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
    }

    private function organizationName(string $locale): string
    {
        $candidates = [
            config('email.brand.organization_name.'.$locale),
            config('website.name.'.$locale),
            config('app.name'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }

    private function shortName(string $locale): string
    {
        $candidates = [
            config('website.short_name.'.$locale),
            config('website.short_name.en'),
            config('app.name'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }

    private function logoAlt(string $locale, string $organization): string
    {
        return (string) __('email.layout.logo_alt', [
            'organization' => $organization !== '' ? $organization : (string) config('app.name'),
        ], $locale);
    }

    private function paragraph(string $text): string
    {
        return '<p style="margin:0 0 14px;">'.e($text).'</p>';
    }
}
