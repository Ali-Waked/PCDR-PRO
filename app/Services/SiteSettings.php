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

    /**
     * The single public identity payload consumed by Inertia, Blade and JSON
     * responses. Every value is resolved from the canonical CMS setting.
     *
     * @param  array<string, string|null>|null  $values
     * @return array<string, mixed>
     */
    public function publicIdentity(?string $locale = null, ?array $values = null): array
    {
        $locale ??= app()->getLocale();
        $values ??= $this->publicValues($locale);
        $value = static fn (string $key, ?string $default = null): ?string => $values[$key] ?? $default;

        $name = $value('organization.name', (string) config("website.name.{$locale}", 'PCDR'));
        $shortName = $value('organization.short_name', (string) config("website.short_name.{$locale}", 'PCDR'));
        $description = $value('seo.default_description', (string) config("website.description.{$locale}", ''));

        return [
            'name' => $name,
            'shortName' => $shortName,
            'seoTitle' => $value('seo.site_title', $shortName),
            'logo' => $value('organization.logo'),
            'description' => $description,
            'ogImage' => $value('seo.og_image'),
            'region' => (string) config("website.region.{$locale}", ''),
            'slogan' => (string) config("website.slogan.{$locale}", ''),
            'footerCopyright' => $value('footer.copyright'),
            'footerLegalNote' => $value('footer.legal_note'),
            'contact' => $this->contactDetails($locale, $values),
            'complaintsEmail' => $value('contact.complaints_email'),
            'social' => $this->socialLinks($locale, $values),
        ];
    }

    /**
     * Error pages must render without touching the database. Reuse the public
     * identity only when it is already cached by a normal site request.
     *
     * @return array<string, mixed>|null
     */
    public function cachedPublicIdentity(?string $locale = null): ?array
    {
        $locale ??= app()->getLocale();
        $values = Cache::get("website.settings.public.{$locale}");

        return is_array($values) ? $this->publicIdentity($locale, $values) : null;
    }

    /**
     * @param  array<string, string|null>|null  $values
     * @return array{email: ?string, phone: ?string, address: ?string, hours: ?string}
     */
    public function contactDetails(?string $locale = null, ?array $values = null): array
    {
        $values ??= $this->publicValues($locale);

        return [
            'email' => $values['contact.email'] ?? null,
            'phone' => $values['contact.phone'] ?? null,
            'address' => $values['contact.address'] ?? null,
            'hours' => $values['contact.hours'] ?? null,
        ];
    }

    public function complaintsEmail(?string $locale = null): ?string
    {
        return $this->value('contact.complaints_email', null, $locale);
    }

    /**
     * @param  array<string, string|null>|null  $values
     * @return list<array{platform: string, label: string, url: string}>
     */
    public function socialLinks(?string $locale = null, ?array $values = null): array
    {
        $links = [];
        $normalizer = app(SocialLinkNormalizer::class);
        $locale ??= app()->getLocale();
        $values ??= $this->publicValues($locale);
        $labels = __('website.ui.social_platforms', [], $locale);

        foreach ($normalizer->platforms() as $platform) {
            $key = "social.{$platform}";
            $url = $normalizer->normalize($key, $values[$key] ?? null);

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

        // Public chrome mixes canonical settings with cached homepage and
        // navigation payloads, so all three must refresh together.
        app(HomepageService::class)->flush();
        app(NavigationService::class)->flush();
    }
}
