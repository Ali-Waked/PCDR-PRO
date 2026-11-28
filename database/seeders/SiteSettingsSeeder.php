<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\WebsiteSetting;
use App\Services\SiteSettings;
use Illuminate\Database\Seeder;

/**
 * Initializes the Website Settings catalogue managed by the CMS.
 *
 * Public values and private operational values are both seeded, but private
 * rows are marked is_public = false and never enter the shared site payload.
 * Values are applied only when missing or empty; administrator-managed values
 * are never overwritten. Secrets and environment configuration are absent.
 */
final class SiteSettingsSeeder extends Seeder
{
    /**
     * @var array<string, array{group: string, type: string, localized: bool, public?: bool, value: array<string, ?string>|string|null}>
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
        'footer.video.provider' => [
            'group' => 'footer',
            'type' => 'video_provider',
            'localized' => false,
            'value' => 'youtube',
        ],
        'footer.video.url' => [
            'group' => 'footer',
            'type' => 'video_url',
            'localized' => false,
            'value' => null,
        ],
        'footer.video.title' => [
            'group' => 'footer',
            'type' => 'text',
            'localized' => true,
            'value' => [
                'ar' => 'الفيلم التعريفي بالجمعية',
                'en' => 'Association Introduction Film',
            ],
        ],
        'footer.video.poster_media_id' => [
            'group' => 'footer',
            'type' => 'media',
            'localized' => false,
            'value' => null,
        ],
        'footer.video.visible' => [
            'group' => 'footer',
            'type' => 'boolean',
            'localized' => false,
            'value' => '1',
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
        'audit_automation.enabled' => [
            'group' => 'audit_automation', 'type' => 'boolean', 'localized' => false, 'public' => false, 'value' => '0',
        ],
        'audit_automation.interval_months' => [
            'group' => 'audit_automation', 'type' => 'interval', 'localized' => false, 'public' => false, 'value' => '1',
        ],
        'audit_automation.execution_day' => [
            'group' => 'audit_automation', 'type' => 'integer', 'localized' => false, 'public' => false, 'value' => '2',
        ],
        'audit_automation.execution_time' => [
            'group' => 'audit_automation', 'type' => 'time', 'localized' => false, 'public' => false, 'value' => '02:30',
        ],
        'audit_automation.timezone' => [
            'group' => 'audit_automation', 'type' => 'timezone', 'localized' => false, 'public' => false, 'value' => 'UTC',
        ],
        'audit_automation.notifications_enabled' => [
            'group' => 'audit_automation', 'type' => 'boolean', 'localized' => false, 'public' => false, 'value' => '1',
        ],
        'audit_automation.lookback_months' => [
            'group' => 'audit_automation', 'type' => 'integer', 'localized' => false, 'public' => false, 'value' => '3',
        ],
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

            if (! ($definition['public'] ?? true) && $setting->is_public !== false) {
                $setting->forceFill(['is_public' => false])->save();
            }

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
