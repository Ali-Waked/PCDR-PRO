<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\WebsiteSetting;
use App\Support\SocialLinkNormalizer;
use Illuminate\Support\Facades\Cache;

/**
 * Public site configuration boundary.
 *
 * Phase 2 owns the platform-wide settings service. This class intentionally
 * reads only Phase 3's small public-site setting catalogue and exposes only
 * is_public keys. When Phase 2 settings become available, this class should
 * delegate to it; no public page should read private settings directly.
 */
final class SiteSettings
{
    /** @return array<string, string|null> */
    public function publicValues(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        return Cache::remember("website.settings.public.{$locale}", 300, function () use ($locale): array {
            return WebsiteSetting::query()
                ->public()
                ->with('translations')
                ->get()
                ->mapWithKeys(fn (WebsiteSetting $setting) => [
                    $setting->key => $setting->resolvedValue($locale),
                ])
                ->all();
        });
    }

    public function value(string $key, ?string $default = null, ?string $locale = null): ?string
    {
        return $this->publicValues($locale)[$key] ?? $default;
    }

    /** @return array{email: ?string, phone: ?string, address: ?string, hours: ?string} */
    public function contactDetails(?string $locale = null): array
    {
        return [
            'email' => $this->value('contact.email', null, $locale),
            'phone' => $this->value('contact.phone', null, $locale),
            'address' => $this->value('contact.address', null, $locale),
            'hours' => $this->value('contact.hours', null, $locale),
        ];
    }

    public function complaintsEmail(?string $locale = null): ?string
    {
        return $this->value('contact.complaints_email', null, $locale);
    }

    /** @return list<array{platform: string, label: string, url: string}> */
    public function socialLinks(?string $locale = null): array
    {
        $links = [];
        $normalizer = app(SocialLinkNormalizer::class);
        $locale ??= app()->getLocale();
        $labels = __('website.ui.social_platforms', [], $locale);

        foreach ($normalizer->platforms() as $platform) {
            $key = "social.{$platform}";
            $url = $normalizer->normalize($key, $this->value($key, null, $locale));

            if ($url === null) {
                continue;
            }

            $links[] = [
                'platform' => $platform,
                'label' => $labels[$platform] ?? $platform,
                'url' => $url,
            ];
        }

        return $links;
    }

    public function flush(): void
    {
        foreach (config('website.locales', ['ar', 'en']) as $locale) {
            Cache::forget("website.settings.public.{$locale}");
        }
    }
}
