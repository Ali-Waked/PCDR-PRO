<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalizes CMS social-link input into canonical, https-only profile URLs.
 *
 * Administrators may paste a full profile URL, a scheme-less host/path, an
 *
 * @handle, a bare username, or (for WhatsApp) a phone number. Every accepted
 * form is converted to one canonical destination before it is stored and again
 * before it is rendered, so public pages can never emit an unsafe scheme.
 */
final class SocialLinkNormalizer
{
    /** @var array<string, array{hosts: list<string>, handle: string}> */
    private const PLATFORMS = [
        'facebook' => [
            'hosts' => ['www.facebook.com', 'facebook.com', 'm.facebook.com', 'fb.com', 'www.fb.com'],
            'handle' => 'https://www.facebook.com/{handle}',
        ],
        'x' => [
            'hosts' => ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com', 'mobile.twitter.com'],
            'handle' => 'https://x.com/{handle}',
        ],
        'instagram' => [
            'hosts' => ['www.instagram.com', 'instagram.com'],
            'handle' => 'https://www.instagram.com/{handle}/',
        ],
        'linkedin' => [
            'hosts' => ['www.linkedin.com', 'linkedin.com'],
            'handle' => 'https://www.linkedin.com/in/{handle}/',
        ],
        'youtube' => [
            'hosts' => ['www.youtube.com', 'youtube.com', 'm.youtube.com', 'youtu.be'],
            'handle' => 'https://www.youtube.com/@{handle}',
        ],
        'tiktok' => [
            'hosts' => ['www.tiktok.com', 'tiktok.com'],
            'handle' => 'https://www.tiktok.com/@{handle}',
        ],
        'snapchat' => [
            'hosts' => ['www.snapchat.com', 'snapchat.com'],
            'handle' => 'https://www.snapchat.com/add/{handle}',
        ],
        'whatsapp' => [
            'hosts' => ['wa.me', 'www.wa.me', 'api.whatsapp.com', 'web.whatsapp.com'],
            'handle' => 'https://wa.me/{handle}',
        ],
    ];

    /** @var array<string, string> */
    private const KEY_TO_PLATFORM = [
        'social.facebook' => 'facebook',
        'social.x' => 'x',
        'social.instagram' => 'instagram',
        'social.linkedin' => 'linkedin',
        'social.youtube' => 'youtube',
        'social.tiktok' => 'tiktok',
        'social.snapchat' => 'snapchat',
        'social.whatsapp' => 'whatsapp',
    ];

    /** @return list<string> */
    public function platforms(): array
    {
        return array_keys(self::PLATFORMS);
    }

    public function platformFor(string $key): ?string
    {
        return self::KEY_TO_PLATFORM[$key] ?? null;
    }

    public function normalize(string $key, ?string $value): ?string
    {
        $platform = $this->platformFor($key);
        if ($platform === null) {
            return null;
        }

        $raw = trim($value ?? '');
        if ($raw === '' || preg_match('/[\x00-\x1F\x7F]/', $raw) === 1) {
            return null;
        }

        if ($platform === 'whatsapp') {
            return $this->normalizeWhatsApp($raw);
        }

        if (str_starts_with($raw, '//')) {
            return $this->fromUrl($platform, 'https:'.$raw);
        }

        if (! preg_match('#^https?://#i', $raw)) {
            if (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}(\/|$)/i', $raw) === 1) {
                return $this->fromUrl($platform, 'https://'.$raw);
            }

            return $this->fromHandle($platform, $raw);
        }

        return $this->fromUrl($platform, $raw);
    }

    private function fromHandle(string $platform, string $raw): ?string
    {
        $handle = ltrim($raw, '@/');
        $handle = rtrim($handle, '/');

        if ($handle === '' || str_contains($handle, '/') || str_contains($handle, '?')) {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,49}$/', $handle) !== 1) {
            return null;
        }

        return str_replace('{handle}', $handle, self::PLATFORMS[$platform]['handle']);
    }

    private function fromUrl(string $platform, string $candidate): ?string
    {
        $parts = parse_url($candidate);
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        if (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443], true)) {
            return null;
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $query = (string) ($parts['query'] ?? '');

        if ($platform === 'linkedin' && preg_match('/^[a-z]{2}\.linkedin\.com$/', $host) === 1) {
            $host = 'www.linkedin.com';
        }

        if (! in_array($host, self::PLATFORMS[$platform]['hosts'], true)) {
            return null;
        }

        if ($platform === 'youtube' && $host === 'youtu.be') {
            if (preg_match('/^[A-Za-z0-9_-]{6,20}$/', $path) !== 1) {
                return null;
            }

            return 'https://www.youtube.com/watch?v='.$path;
        }

        if ($path === '') {
            return null;
        }

        return match ($platform) {
            'facebook' => $this->facebookUrl($path, $query),
            'x' => $this->singleSegmentUrl('https://x.com/', $path),
            'instagram' => $this->singleSegmentUrl('https://www.instagram.com/', $path, true),
            'linkedin' => $this->linkedinUrl($path),
            'youtube' => $this->youtubeUrl($path, $query),
            'tiktok' => $this->singleSegmentUrl('https://www.tiktok.com/', $path),
            'snapchat' => $this->snapchatUrl($path),
            default => null,
        };
    }

    private function facebookUrl(string $path, string $query): ?string
    {
        if (str_starts_with($path, 'profile.php')) {
            return $query === '' ? null : 'https://www.facebook.com/profile.php?'.$query;
        }

        return $this->singleSegmentUrl('https://www.facebook.com/', $path);
    }

    private function singleSegmentUrl(string $prefix, string $path, bool $trailingSlash = false): ?string
    {
        $segments = explode('/', $path);
        $handle = (string) $segments[0];

        if (preg_match('/^@?[A-Za-z0-9][A-Za-z0-9._-]{0,49}$/', $handle) !== 1) {
            return null;
        }

        return $prefix.$handle.($trailingSlash ? '/' : '');
    }

    private function linkedinUrl(string $path): ?string
    {
        $segments = array_values(array_filter(explode('/', $path), static fn (string $part): bool => $part !== ''));

        if (count($segments) >= 2 && in_array($segments[0], ['in', 'company', 'school'], true)) {
            $type = $segments[0];
            $handle = $segments[1];
        } else {
            $type = 'in';
            $handle = $segments[0] ?? '';
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,49}$/', ltrim($handle, '@')) !== 1) {
            return null;
        }

        return 'https://www.linkedin.com/'.$type.'/'.ltrim($handle, '@').'/';
    }

    private function youtubeUrl(string $path, string $query): ?string
    {
        if (str_starts_with($path, 'watch/')) {
            $path = 'watch';
        }

        if ($path === 'watch') {
            if (preg_match('/^v=[A-Za-z0-9_-]{6,20}$/', $query) !== 1) {
                return null;
            }

            return 'https://www.youtube.com/watch?'.$query;
        }

        if (preg_match('#^(channel|c|playlist|shorts|live)(/|$)#', $path) === 1) {
            return 'https://www.youtube.com/'.$path;
        }

        return $this->singleSegmentUrl('https://www.youtube.com/', str_starts_with($path, '@') ? $path : '@'.$path);
    }

    private function snapchatUrl(string $path): ?string
    {
        if (preg_match('#^(add|story)/([^/]+)$#', $path, $matches) === 1) {
            $handle = ltrim($matches[2], '@');

            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,49}$/', $handle) === 1) {
                return 'https://www.snapchat.com/'.$matches[1].'/'.$handle;
            }

            return null;
        }

        return $this->singleSegmentUrl('https://www.snapchat.com/add/', $path);
    }

    private function normalizeWhatsApp(string $raw): ?string
    {
        if (preg_match('#^(?:(?:https?:)?//)?(?:www\.)?(?:wa\.me|api\.whatsapp\.com|web\.whatsapp\.com)(/|$)#i', $raw) === 1) {
            $candidate = str_starts_with($raw, '//') ? 'https:'.$raw : $raw;
            $parts = parse_url($candidate);

            if (! is_array($parts) || empty($parts['host'])) {
                return null;
            }

            $host = strtolower(rtrim((string) $parts['host'], '.'));
            if (! in_array($host, self::PLATFORMS['whatsapp']['hosts'], true)) {
                return null;
            }

            $digits = preg_replace('/\D/', '', (string) ($parts['path'] ?? '')) ?? '';
        } elseif (preg_match('/^[+\d\s().-]+$/', $raw) === 1) {
            $digits = preg_replace('/\D/', '', $raw) ?? '';
        } else {
            return null;
        }

        if (preg_match('/^\d{7,15}$/', $digits) !== 1) {
            return null;
        }

        return 'https://wa.me/'.$digits;
    }
}
