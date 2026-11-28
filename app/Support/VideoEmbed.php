<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Safe, centralized resolver for administrator-configured video URLs.
 *
 * Only an explicit provider allowlist is accepted. Embed URLs are rebuilt from
 * validated identifiers and fixed provider endpoints; administrator input is
 * never interpolated into iframe markup.
 */
final class VideoEmbed
{
    private const YOUTUBE_HOSTS = [
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
        'youtu.be',
        'www.youtu.be',
        'youtube-nocookie.com',
        'www.youtube-nocookie.com',
    ];

    private const FACEBOOK_HOSTS = [
        'facebook.com',
        'www.facebook.com',
        'm.facebook.com',
        'fb.com',
        'www.fb.com',
    ];

    private const SANDBOX = 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-presentation';

    private const YOUTUBE_ALLOW = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';

    private const FACEBOOK_ALLOW = 'encrypted-media; picture-in-picture';

    /** @return list<string> */
    public static function providers(): array
    {
        return ['youtube', 'facebook'];
    }

    /**
     * @return array{provider: string, url: string, embedUrl: string, sandbox: string, allow: string}|null
     */
    public static function resolve(?string $provider, ?string $url): ?array
    {
        if ($provider === null || $url === null) {
            return null;
        }

        $provider = strtolower(trim($provider));
        $url = trim($url);

        if (! in_array($provider, self::providers(), true) || $url === '' || preg_match('/[\x00-\x20<>"\']/', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ($parts['port'] ?? 443) !== 443
            || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        return match ($provider) {
            'youtube' => self::youtube($host, (string) ($parts['path'] ?? ''), self::query($parts)),
            'facebook' => self::facebook($host, (string) ($parts['path'] ?? ''), self::query($parts)),
            default => null,
        };
    }

    /**
     * @param  array<string, string|null>  $query
     * @return array{provider: string, url: string, embedUrl: string, sandbox: string, allow: string}|null
     */
    private static function youtube(string $host, string $path, array $query): ?array
    {
        if (! in_array($host, self::YOUTUBE_HOSTS, true)) {
            return null;
        }

        $id = null;
        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));
            $id = $segments[0] ?? null;
        } elseif ($path === '/watch' || $path === '/watch/') {
            $id = $query['v'] ?? null;
        } elseif (preg_match('#^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})(?:/|$)#', $path, $matches) === 1) {
            $id = $matches[1];
        }

        if (! is_string($id) || preg_match('/^[A-Za-z0-9_-]{11}$/', $id) !== 1) {
            return null;
        }

        return [
            'provider' => 'youtube',
            'url' => "https://www.youtube.com/watch?v={$id}",
            'embedUrl' => "https://www.youtube-nocookie.com/embed/{$id}",
            'sandbox' => self::SANDBOX,
            'allow' => self::YOUTUBE_ALLOW,
        ];
    }

    /**
     * @param  array<string, string|null>  $query
     * @return array{provider: string, url: string, embedUrl: string, sandbox: string, allow: string}|null
     */
    private static function facebook(string $host, string $path, array $query): ?array
    {
        if (! in_array($host, self::FACEBOOK_HOSTS, true)) {
            return null;
        }

        $video = $query['v'] ?? null;
        $id = null;

        if (in_array($path, ['/watch', '/watch/', '/video.php', '/photo.php'], true)) {
            $id = is_string($video) && preg_match('/^\d{8,20}$/', $video) === 1 ? $video : null;
        } elseif (preg_match('#^/(?:reel|videos)/(\d{8,20})(?:/|$)#', $path, $matches) === 1) {
            $id = $matches[1];
        } elseif (preg_match('#^/[^/]+/videos/(\d{8,20})(?:/|$)#', $path, $matches) === 1) {
            $id = $matches[1];
        }

        if ($id === null) {
            return null;
        }

        $canonical = "https://www.facebook.com/watch/?v={$id}";

        return [
            'provider' => 'facebook',
            'url' => $canonical,
            'embedUrl' => 'https://www.facebook.com/plugins/video.php?href='
                .rawurlencode($canonical).'&show_text=false&width=560',
            'sandbox' => self::SANDBOX,
            'allow' => self::FACEBOOK_ALLOW,
        ];
    }

    /**
     * @param  array<string, mixed>  $parts
     * @return array<string, string|null>
     */
    private static function query(array $parts): array
    {
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $parsed);

        foreach ($parsed as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $query[$key] = $value;
            }
        }

        return $query;
    }
}
