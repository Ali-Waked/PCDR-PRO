<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Mime\Email as SymfonyEmail;

/**
 * Resolves the single PCDR email logo asset for previews, URL mode and CID
 * delivery. All paths are resolved on the local filesystem so queued workers
 * never depend on a web request or an APP_URL hostname.
 */
final class EmailLogoService
{
    private const CID_MODE = 'cid';

    private const URL_MODE = 'url';

    public function mode(): string
    {
        $mode = strtolower(trim((string) config('email.brand.logo_mode', self::CID_MODE)));

        return $mode === self::URL_MODE ? self::URL_MODE : self::CID_MODE;
    }

    public function absolutePath(): string
    {
        $relative = trim((string) config('email.brand.embedded_logo_path', 'mail/email-logo.png'));
        $path = resource_path($relative);

        if (! is_file($path)) {
            throw new RuntimeException("Email logo is missing: {$path}");
        }

        return $path;
    }

    public function placeholder(): string
    {
        return 'cid:'.trim((string) config('email.brand.logo_cid', 'pcdr-email-logo@pcdr.local'));
    }

    /**
     * The src used by the shared layout. Preview uses an inline data URI only;
     * real messages use a CID placeholder that is replaced at send time, or a
     * validated absolute HTTPS URL when explicitly configured.
     */
    public function source(bool $preview = false): string
    {
        if ($preview) {
            return 'data:image/png;base64,'.base64_encode((string) file_get_contents($this->absolutePath()));
        }

        if ($this->mode() === self::URL_MODE) {
            $url = $this->publicUrl();
            if ($url !== null) {
                return $url;
            }
        }

        return $this->placeholder();
    }

    /** Embed the same file via Laravel's mail message and return the real CID. */
    public function embed(Message $message): string
    {
        return $message->embed($this->absolutePath());
    }

    /**
     * Replace the layout placeholder with a real inline attachment. Registered
     * on MessageSending, this covers Mailables, notifications and queued mail.
     */
    public function attachTo(SymfonyEmail $email): void
    {
        $html = $email->getHtmlBody();
        if (! is_string($html) || ! str_contains($html, $this->placeholder())) {
            return;
        }

        $cid = $this->embed(new Message($email));
        $email->html(str_replace($this->placeholder(), $cid, $html));
    }

    private function publicUrl(): ?string
    {
        $configured = config('email.brand.logo_url');
        if (is_string($configured) && $configured !== '' && $this->isPublicHttpsUrl($configured)) {
            return $configured;
        }

        $path = trim((string) config('email.brand.public_logo_path', 'assets/email/email-logo.png'));
        $bases = [
            config('email.brand.asset_url'),
            config('app.url'),
        ];

        foreach ($bases as $base) {
            if (! is_string($base) || $base === '') {
                continue;
            }

            if (! $this->isPublicHttpsUrl($base)) {
                continue;
            }

            return rtrim($base, '/').'/'.ltrim($path, '/');
        }

        Log::warning('MAIL_LOGO_MODE=url requires a public HTTPS MAIL_ASSET_URL or APP_URL; falling back to CID.');

        return null;
    }

    private function isPublicHttpsUrl(string $url): bool
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || trim((string) ($parts['host'] ?? '')) === ''
            || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower((string) $parts['host']);
        foreach (['localhost', '.localhost', '.local', '.test', '.invalid', '.example', '.internal'] as $suffix) {
            if ($host === $suffix || str_ends_with($host, $suffix)) {
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
}
