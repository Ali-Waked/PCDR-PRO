<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Media;
use App\Support\Locale;

final class Seo
{
    public function __construct(private SiteSettings $settings) {}

    /** @param array<string, mixed> $overrides */
    public function for(
        string $locale,
        string $title,
        ?string $description = null,
        ?string $path = null,
        ?Media $image = null,
        array $overrides = [],
    ): array {
        $identity = $this->settings->publicIdentity($locale);
        $description ??= $identity['description'];
        $shortName = $identity['shortName'];
        $siteTitle = $identity['seoTitle'];
        $path ??= request()->path();
        $alternates = Locale::alternates($path);
        $ogImage = $identity['ogImage'] ?: $image?->url();

        $data = [
            'title' => $title.' — '.$shortName,
            'description' => mb_strimwidth($description, 0, 320, '…', 'UTF-8'),
            'canonical' => url('/'.ltrim($path, '/')),
            'locale' => $locale,
            'alternates' => $alternates,
            'og' => [
                'type' => 'website',
                'title' => $title,
                'description' => $description,
                'url' => url('/'.ltrim($path, '/')),
                'locale' => $locale === 'ar' ? 'ar_AR' : 'en_US',
                'site_name' => $siteTitle,
                'image' => $ogImage,
            ],
            'twitter' => [
                'card' => $ogImage ? 'summary_large_image' : 'summary',
                'title' => $title,
                'description' => $description,
                'image' => $ogImage,
            ],
            'robots' => 'index,follow,max-snippet:-1,max-image-preview:large',
        ];

        return array_replace_recursive($data, $overrides);
    }

    /** @param array<string, mixed> $meta */
    public function organizationJsonLd(array $meta): string
    {
        $locale = (string) ($meta['locale'] ?? config('website.default_locale', 'ar'));
        $identity = $this->settings->publicIdentity($locale);
        $name = $identity['name'];
        $shortName = $identity['shortName'];

        return json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $name,
            'alternateName' => $shortName,
            'url' => $meta['canonical'],
            'description' => $meta['description'],
            'inLanguage' => [config('website.locales')],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
