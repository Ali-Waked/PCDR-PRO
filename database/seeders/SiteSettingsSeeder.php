<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\WebsiteSetting;
use App\Services\SiteSettings;
use Illuminate\Database\Seeder;

/**
 * Initializes only the public website settings managed by the CMS.
 *
 * Values are applied only when they are missing or empty. Existing
 * administrator-managed values are never overwritten. Secrets, environment
 * configuration and unverified contact details are intentionally absent; the
 * published complaints address is initialized only when no value exists.
 */
final class SiteSettingsSeeder extends Seeder
{
    /**
     * @var array<string, array{group: string, type: string, localized: bool, value: array<string, ?string>|string|null}>
     */
    private const SETTINGS = [
        'organization.name' => [
            'group' => 'organization',
            'type' => 'text',
            'localized' => true,
            'value' => [
                'ar' => 'جمعية العمل التشاركي في الأزمات والكوارث الخيرية – قطاع غزة',
                'en' => 'Participatory Community for Disaster Response',
            ],
        ],
        'organization.short_name' => [
            'group' => 'organization',
            'type' => 'text',
            'localized' => true,
            'value' => ['ar' => 'PCDR', 'en' => 'PCDR'],
        ],
        'organization.logo' => [
            'group' => 'organization',
            'type' => 'url',
            'localized' => false,
            'value' => '/assets/pcdr-homepage/logo/pcdr-logo.png',
        ],
        'website.default_language' => [
            'group' => 'website',
            'type' => 'locale',
            'localized' => false,
            'value' => 'ar',
        ],
        'seo.site_title' => [
            'group' => 'seo',
            'type' => 'text',
            'localized' => true,
            'value' => ['ar' => 'PCDR', 'en' => 'PCDR'],
        ],
        'seo.default_description' => [
            'group' => 'seo',
            'type' => 'textarea',
            'localized' => true,
            'value' => [
                'ar' => 'منصة مؤسسية تقدم الرؤية والرسالة والمحاور الاستراتيجية والبرامج المخططة وتقارير جمعية العمل التشاركي في الأزمات والكوارث الخيرية.',
                'en' => 'The institutional platform of the Participatory Community for Disaster Response (PCDR): vision, mission, strategic pillars, planned programmes and publications.',
            ],
        ],
        'seo.og_image' => [
            'group' => 'seo',
            'type' => 'url',
            'localized' => false,
            'value' => null,
        ],
        'footer.copyright' => [
            'group' => 'footer',
            'type' => 'text',
            'localized' => true,
            'value' => ['ar' => null, 'en' => null],
        ],
        'footer.legal_note' => [
            'group' => 'footer',
            'type' => 'textarea',
            'localized' => true,
            'value' => ['ar' => null, 'en' => null],
        ],
        'contact.email' => ['group' => 'contact', 'type' => 'email', 'localized' => false, 'value' => null],
        'contact.complaints_email' => ['group' => 'contact', 'type' => 'email', 'localized' => false, 'value' => 'complaints@pal-arc.org'],
        'contact.phone' => ['group' => 'contact', 'type' => 'text', 'localized' => false, 'value' => null],
        'contact.address' => ['group' => 'contact', 'type' => 'text', 'localized' => false, 'value' => null],
        'contact.hours' => ['group' => 'contact', 'type' => 'text', 'localized' => false, 'value' => null],
        'social.facebook' => ['group' => 'social', 'type' => 'url', 'localized' => false, 'value' => null],
        'social.x' => ['group' => 'social', 'type' => 'url', 'localized' => false, 'value' => null],
        'social.instagram' => ['group' => 'social', 'type' => 'url', 'localized' => false, 'value' => null],
        'social.linkedin' => ['group' => 'social', 'type' => 'url', 'localized' => false, 'value' => null],
        'social.youtube' => ['group' => 'social', 'type' => 'url', 'localized' => false, 'value' => null],
        'social.tiktok' => ['group' => 'social', 'type' => 'url', 'localized' => false, 'value' => null],
        'social.snapchat' => ['group' => 'social', 'type' => 'url', 'localized' => false, 'value' => null],
        'social.whatsapp' => ['group' => 'social', 'type' => 'url', 'localized' => false, 'value' => null],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as $key => $definition) {
            $setting = WebsiteSetting::query()->firstOrCreate(
                ['key' => $key],
                [
                    'group' => $definition['group'],
                    'type' => $definition['type'],
                    'is_localized' => $definition['localized'],
                    'is_public' => true,
                    'value' => $definition['localized'] ? null : $this->defaultValue($definition['value']),
                ],
            );

            if (! $definition['localized']) {
                if (! filled($setting->value) && filled($this->defaultValue($definition['value']))) {
                    $setting->forceFill(['value' => $this->defaultValue($definition['value'])])->save();
                }

                continue;
            }

            foreach (config('website.locales', ['ar', 'en']) as $locale) {
                $default = $this->defaultValue($definition['value'], $locale);
                $translation = $setting->translations()->firstOrNew(['locale' => $locale]);

                if (filled($translation->value) || ! filled($default)) {
                    continue;
                }

                $translation->forceFill(['value' => $default])->save();
            }
        }

        app(SiteSettings::class)->flush();
    }

    /**
     * @param  array<string, ?string>|string|null  $value
     */
    private function defaultValue(array|string|null $value, ?string $locale = null): ?string
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($locale !== null) {
            return $value[$locale] ?? null;
        }

        return null;
    }
}
