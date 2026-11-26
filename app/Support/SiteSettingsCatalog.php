<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The canonical catalogue of public website settings editable in the CMS.
 *
 * Platform, security, mail transport and hidden operational settings are not
 * part of this catalogue. Keep the list in one place so controllers, requests
 * and tests cannot drift apart.
 */
final class SiteSettingsCatalog
{
    /** @var array<string, array{group: string, localized: bool, type: string}> */
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
    ];

    /** @var list<string> */
    private const GROUP_ORDER = ['organization', 'website', 'seo', 'footer', 'contact', 'social'];

    /** @var list<string> */
    private const IMAGE_KEYS = ['organization.logo', 'seo.og_image'];

    /** @return array<string, array{group: string, localized: bool, type: string}> */
    public static function all(): array
    {
        return self::SETTINGS;
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

    /** @return array{group: string, localized: bool, type: string}|null */
    public static function find(?string $key): ?array
    {
        if ($key === null) {
            return null;
        }

        return self::SETTINGS[$key] ?? null;
    }
}
