<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The canonical catalogue of Website Settings editable in the CMS.
 *
 * Public-facing values remain public. Private operational entries (currently
 * the audit archive automation section) are marked public => false so they can
 * be edited in the same UI without ever entering the shared site payload.
 * Keep the list in one place so controllers, requests and tests cannot drift.
 */
final class SiteSettingsCatalog
{
    /** @var array<string, array{group: string, localized: bool, type: string, public?: bool}> */
    private const SETTINGS = [
        'organization.name' => ['group' => 'organization', 'localized' => true, 'type' => 'text'],
        'organization.short_name' => ['group' => 'organization', 'localized' => true, 'type' => 'text'],
        'organization.logo' => ['group' => 'organization', 'localized' => false, 'type' => 'url'],
        'website.default_language' => ['group' => 'website', 'localized' => false, 'type' => 'locale'],
        'seo.site_title' => ['group' => 'seo', 'localized' => true, 'type' => 'text'],
        'seo.default_description' => ['group' => 'seo', 'localized' => true, 'type' => 'textarea'],
        'seo.og_image' => ['group' => 'seo', 'localized' => false, 'type' => 'url'],
        'footer.copyright' => ['group' => 'footer', 'localized' => true, 'type' => 'text'],
        'footer.legal_note' => ['group' => 'footer', 'localized' => true, 'type' => 'textarea'],
        'footer.video.provider' => ['group' => 'footer', 'localized' => false, 'type' => 'video_provider'],
        'footer.video.url' => ['group' => 'footer', 'localized' => false, 'type' => 'video_url'],
        'footer.video.title' => ['group' => 'footer', 'localized' => true, 'type' => 'text'],
        'footer.video.poster_media_id' => ['group' => 'footer', 'localized' => false, 'type' => 'media'],
        'footer.video.visible' => ['group' => 'footer', 'localized' => false, 'type' => 'boolean'],
        'contact.email' => ['group' => 'contact', 'localized' => false, 'type' => 'email'],
        'contact.complaints_email' => ['group' => 'contact', 'localized' => false, 'type' => 'email'],
        'contact.phone' => ['group' => 'contact', 'localized' => false, 'type' => 'text'],
        'contact.address' => ['group' => 'contact', 'localized' => false, 'type' => 'text'],
        'contact.hours' => ['group' => 'contact', 'localized' => false, 'type' => 'text'],
        'social.facebook' => ['group' => 'social', 'localized' => false, 'type' => 'url'],
        'social.x' => ['group' => 'social', 'localized' => false, 'type' => 'url'],
        'social.instagram' => ['group' => 'social', 'localized' => false, 'type' => 'url'],
        'social.linkedin' => ['group' => 'social', 'localized' => false, 'type' => 'url'],
        'social.youtube' => ['group' => 'social', 'localized' => false, 'type' => 'url'],
        'social.tiktok' => ['group' => 'social', 'localized' => false, 'type' => 'url'],
        'social.snapchat' => ['group' => 'social', 'localized' => false, 'type' => 'url'],
        'social.whatsapp' => ['group' => 'social', 'localized' => false, 'type' => 'url'],
        'audit_automation.enabled' => ['group' => 'audit_automation', 'localized' => false, 'type' => 'boolean', 'public' => false],
        'audit_automation.interval_months' => ['group' => 'audit_automation', 'localized' => false, 'type' => 'interval', 'public' => false],
        'audit_automation.execution_day' => ['group' => 'audit_automation', 'localized' => false, 'type' => 'integer', 'public' => false],
        'audit_automation.execution_time' => ['group' => 'audit_automation', 'localized' => false, 'type' => 'time', 'public' => false],
        'audit_automation.timezone' => ['group' => 'audit_automation', 'localized' => false, 'type' => 'timezone', 'public' => false],
        'audit_automation.notifications_enabled' => ['group' => 'audit_automation', 'localized' => false, 'type' => 'boolean', 'public' => false],
        'audit_automation.lookback_months' => ['group' => 'audit_automation', 'localized' => false, 'type' => 'integer', 'public' => false],
    ];

    /** @var list<string> */
    private const GROUP_ORDER = ['organization', 'website', 'seo', 'footer', 'contact', 'social', 'audit_automation'];

    /** @var list<string> */
    private const IMAGE_KEYS = ['organization.logo', 'seo.og_image'];

    /** @return array<string, array{group: string, localized: bool, type: string, public: bool}> */
    public static function all(): array
    {
        return collect(self::SETTINGS)
            ->map(fn (array $meta): array => array_merge(['public' => true], $meta))
            ->all();
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::SETTINGS);
    }

    /** @return list<string> */
    public static function groupOrder(): array
    {
        return self::GROUP_ORDER;
    }

    /** @return list<string> */
    public static function imageKeys(): array
    {
        return self::IMAGE_KEYS;
    }

    /** @return array{group: string, localized: bool, type: string, public: bool}|null */
    public static function find(?string $key): ?array
    {
        if ($key === null) {
            return null;
        }

        $meta = self::SETTINGS[$key] ?? null;

        return $meta === null ? null : array_merge(['public' => true], $meta);
    }
}
