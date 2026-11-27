<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Models\WebsiteSettingTranslation;
use App\Services\AccessManagementService;
use App\Services\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class PublicSiteSettingsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', '12345678901234567890123456789012');
        config()->set('cms.local_auth_enabled', false);

        $this->withoutVite();
        app(SiteSettings::class)->flush();
    }

    public function test_admin_identity_updates_appear_in_public_props_and_document_head(): void
    {
        $owner = $this->owner();

        $this->updateSettings($owner, [
            ['key' => 'organization.name', 'translations' => [
                'en' => 'Public English Organization',
                'ar' => 'الجمعية العربية العامة',
            ]],
            ['key' => 'organization.short_name', 'translations' => [
                'en' => 'PEO',
                'ar' => 'ع ع',
            ]],
            ['key' => 'organization.logo', 'value' => '/assets/custom-logo.png'],
            ['key' => 'seo.site_title', 'translations' => [
                'en' => 'English SEO Name',
                'ar' => 'اسم تحسين محركات عربي',
            ]],
            ['key' => 'seo.default_description', 'translations' => [
                'en' => 'English site description marker',
                'ar' => 'وصف الموقع بالعربية',
            ]],
            ['key' => 'seo.og_image', 'value' => '/assets/custom-og.png'],
            ['key' => 'footer.copyright', 'translations' => [
                'en' => 'English copyright marker',
                'ar' => 'علامة حقوق عربية',
            ]],
            ['key' => 'footer.legal_note', 'translations' => [
                'en' => 'English legal marker',
                'ar' => 'علامة قانونية عربية',
            ]],
        ]);

        foreach (['/en', '/en/about', '/en/contact', '/en/programs', '/en/media', '/en/partners', '/en/complaints'] as $url) {
            $response = $this->get($url)->assertOk();
            $site = $this->props($response)['site'];
            $html = (string) $response->getContent();

            $this->assertSame('Public English Organization', $site['name'], $url);
            $this->assertSame('PEO', $site['shortName'], $url);
            $this->assertSame('/assets/custom-logo.png', $site['logo'], $url);
            $this->assertSame('English SEO Name', $site['seoTitle'], $url);
            $this->assertSame('English site description marker', $site['description'], $url);
            $this->assertSame('/assets/custom-og.png', $site['ogImage'], $url);
            $this->assertSame('English copyright marker', $site['footerCopyright'], $url);
            $this->assertSame('English legal marker', $site['footerLegalNote'], $url);
            $this->assertStringContainsString('Public English Organization', $html, $url);
            $this->assertStringContainsString('English site description marker', $html, $url);
            $this->assertStringContainsString('English SEO Name', $html, $url);
        }

        $arabic = $this->props($this->get('/ar/about')->assertOk())['site'];
        $this->assertSame('الجمعية العربية العامة', $arabic['name']);
        $this->assertSame('ع ع', $arabic['shortName']);
        $this->assertSame('وصف الموقع بالعربية', $arabic['description']);
        $this->assertSame('علامة حقوق عربية', $arabic['footerCopyright']);
        $this->assertSame('علامة قانونية عربية', $arabic['footerLegalNote']);
    }

    public function test_uploaded_logo_replaces_the_public_reference_everywhere(): void
    {
        Storage::fake('public');

        $owner = $this->owner();

        $this->actingAs($owner)->post('/en/cms/settings/image', [
            'key' => 'organization.logo',
            'file' => $this->fakeImage('brand-logo.png'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $media = Media::query()->where('original_name', 'brand-logo.png')->firstOrFail();
        $setting = WebsiteSetting::query()->where('key', 'organization.logo')->firstOrFail();

        $this->assertSame($media->url(), $setting->value);
        $this->assertFalse((bool) $media->is_restricted);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cms.settings.image_uploaded']);

        foreach (['/en', '/en/about', '/ar/contact'] as $url) {
            $response = $this->get($url)->assertOk();

            $html = str_replace('\\/', '/', (string) $response->getContent());

            $this->assertSame($media->url(), $this->props($response)['site']['logo'], $url);
            $this->assertStringContainsString($media->url(), $html, $url);
        }
    }

    public function test_admin_contact_and_social_updates_reach_public_identity_and_page_data(): void
    {
        $owner = $this->owner();

        $this->updateSettings($owner, [
            ['key' => 'contact.email', 'value' => 'hello@example.test'],
            ['key' => 'contact.complaints_email', 'value' => 'complaints@example.test'],
            ['key' => 'contact.phone', 'value' => '+970 82 555 0100'],
            ['key' => 'contact.address', 'value' => 'Gaza Strip'],
            ['key' => 'contact.hours', 'value' => '09:00 - 17:00'],
            ['key' => 'social.facebook', 'value' => '@pcdr'],
            ['key' => 'social.instagram', 'value' => '@pcdr'],
            ['key' => 'social.whatsapp', 'value' => '+970 59 555 0100'],
        ]);

        $props = $this->props($this->get('/en/contact')->assertOk());

        $this->assertSame([
            'email' => 'hello@example.test',
            'phone' => '+970 82 555 0100',
            'address' => 'Gaza Strip',
            'hours' => '09:00 - 17:00',
        ], $props['site']['contact']);
        $this->assertSame('complaints@example.test', $props['site']['complaintsEmail']);
        $this->assertContains([
            'platform' => 'facebook',
            'label' => 'Facebook',
            'url' => 'https://www.facebook.com/pcdr',
        ], $props['site']['social']);
        $this->assertContains([
            'platform' => 'whatsapp',
            'label' => 'WhatsApp',
            'url' => 'https://wa.me/970595550100',
        ], $props['site']['social']);

        $this->assertSame('hello@example.test', $props['contact']['email'] ?? null);
        $this->assertSame('+970 82 555 0100', $props['contact']['phone'] ?? null);
        $this->assertSame('Gaza Strip', $props['contact']['address'] ?? null);
        $this->assertSame('09:00 - 17:00', $props['contact']['hours'] ?? null);
    }

    public function test_arabic_and_english_values_are_preserved_on_partial_updates(): void
    {
        $owner = $this->owner();

        $this->updateSettings($owner, [
            ['key' => 'organization.name', 'translations' => [
                'en' => 'English original name',
                'ar' => 'الاسم العربي الأصلي',
            ]],
            ['key' => 'footer.copyright', 'translations' => [
                'en' => 'English original footer',
                'ar' => 'تذييل عربي أصلي',
            ]],
        ]);

        $this->assertSame('English original name', $this->props($this->get('/en/contact'))['site']['name']);
        $this->assertSame('الاسم العربي الأصلي', $this->props($this->get('/ar/contact'))['site']['name']);

        $this->updateSettings($owner, [
            ['key' => 'organization.name', 'translations' => [
                'ar' => 'الاسم العربي المحدث',
            ]],
        ]);

        $settingId = (int) WebsiteSetting::query()->where('key', 'organization.name')->value('id');
        $this->assertDatabaseHas('website_setting_translations', [
            'website_setting_id' => $settingId,
            'locale' => 'en',
            'value' => 'English original name',
        ]);
        $this->assertDatabaseHas('website_setting_translations', [
            'website_setting_id' => $settingId,
            'locale' => 'ar',
            'value' => 'الاسم العربي المحدث',
        ]);

        $english = $this->get('/en/contact')->assertOk();
        $arabic = $this->get('/ar/contact')->assertOk();

        $this->assertSame('English original name', $this->props($english)['site']['name']);
        $this->assertSame('الاسم العربي المحدث', $this->props($arabic)['site']['name']);
        $this->assertStringContainsString('<html lang="en" dir="ltr"', (string) $english->getContent());
        $this->assertStringContainsString('<html lang="ar" dir="rtl"', (string) $arabic->getContent());
    }

    public function test_admin_updates_refresh_stale_public_settings(): void
    {
        $owner = $this->owner();
        $this->setting('organization.logo', '/assets/old-logo.png');
        app(SiteSettings::class)->flush();

        $this->get('/en/contact')->assertOk();
        $this->assertTrue(Cache::has('website.settings.public.en'));

        WebsiteSetting::query()
            ->where('key', 'organization.logo')
            ->update(['value' => '/assets/new-logo.png']);

        $this->assertSame('/assets/old-logo.png', $this->props($this->get('/en/contact'))['site']['logo']);

        $this->updateSettings($owner, [
            ['key' => 'organization.logo', 'value' => '/assets/new-logo.png'],
        ]);

        $this->assertSame('/assets/new-logo.png', $this->props($this->get('/en/contact'))['site']['logo']);
        $this->assertSame('/assets/new-logo.png', $this->props($this->get('/ar/contact'))['site']['logo']);
    }

    public function test_setting_flush_invalidates_homepage_and_navigation_caches(): void
    {
        foreach (['en', 'ar'] as $locale) {
            Cache::put("website.settings.public.{$locale}", ['stale' => true], 300);
            Cache::put("homepage.sections.{$locale}.enabled.published", ['stale' => true], 300);
            Cache::put("homepage.sections.{$locale}.all.published", ['stale' => true], 300);
            Cache::put("website.navigation.{$locale}", ['stale' => true], 300);
        }

        app(SiteSettings::class)->flush();

        foreach (['en', 'ar'] as $locale) {
            $this->assertFalse(Cache::has("website.settings.public.{$locale}"));
            $this->assertFalse(Cache::has("homepage.sections.{$locale}.enabled.published"));
            $this->assertFalse(Cache::has("homepage.sections.{$locale}.all.published"));
            $this->assertFalse(Cache::has("website.navigation.{$locale}"));
        }
    }

    public function test_missing_optional_settings_do_not_break_public_pages(): void
    {
        $this->get('/')->assertRedirect('/ar');

        foreach (['/en', '/en/about', '/en/contact', '/en/complaints', '/ar', '/ar/about'] as $url) {
            $this->get($url)->assertOk();
        }

        $site = $this->props($this->get('/en/contact'))['site'];

        $this->assertNull($site['logo']);
        $this->assertNull($site['ogImage']);
        $this->assertNull($site['footerCopyright']);
        $this->assertNull($site['footerLegalNote']);
        $this->assertNull($site['complaintsEmail']);
        $this->assertSame([
            'email' => null,
            'phone' => null,
            'address' => null,
            'hours' => null,
        ], $site['contact']);
        $this->assertSame([], $site['social']);
        $this->assertSame(config('website.name.en'), $site['name']);
        $this->assertSame(config('website.short_name.en'), $site['shortName']);
    }

    public function test_unauthorized_users_cannot_mutate_settings_or_change_public_output(): void
    {
        $this->setting('organization.logo', '/assets/original-logo.png');
        $this->localizedSetting('organization.name', [
            'en' => 'Original English name',
            'ar' => 'الاسم الإنجليزي الأصلي',
        ]);
        app(SiteSettings::class)->flush();

        $before = $this->props($this->get('/en/contact'))['site'];
        $user = $this->limitedUser(['news.view']);

        $this->actingAs($user)->get('/ar/cms/settings')->assertForbidden();
        $this->actingAs($user)->put('/ar/cms/settings', [
            'settings' => [
                ['key' => 'organization.logo', 'value' => '/assets/hacked-logo.png'],
                ['key' => 'organization.name', 'translations' => ['en' => 'Hacked name']],
            ],
        ])->assertForbidden();
        $this->actingAs($user)->post('/ar/cms/settings/image', [
            'key' => 'organization.logo',
            'file' => $this->fakeImage('hacked.png'),
        ])->assertForbidden();

        $this->assertDatabaseHas('website_settings', [
            'key' => 'organization.logo',
            'value' => '/assets/original-logo.png',
        ]);
        $this->assertDatabaseMissing('website_settings', ['value' => '/assets/hacked-logo.png']);
        $this->assertDatabaseMissing('media', ['original_name' => 'hacked.png']);
        $this->assertSame($before, $this->props($this->get('/en/contact'))['site']);
    }

    public function test_private_settings_are_never_exposed_on_public_pages(): void
    {
        $this->setting('contact.private_note', 'PRIVATE_CONTACT_MARKER', false);
        $this->setting('mail.password', 'PRIVATE_MAIL_MARKER', false);
        $this->setting('security.secret', 'PRIVATE_SECURITY_MARKER', false);
        $this->setting('organization.logo', '/assets/public-logo.png');
        app(SiteSettings::class)->flush();

        $markers = ['PRIVATE_CONTACT_MARKER', 'PRIVATE_MAIL_MARKER', 'PRIVATE_SECURITY_MARKER'];

        foreach (['/en', '/en/about', '/en/contact', '/ar/contact'] as $url) {
            $response = $this->get($url)->assertOk();
            $props = $this->props($response);
            $serialized = json_encode($props, JSON_THROW_ON_ERROR);
            $html = (string) $response->getContent();

            $this->assertSame([
                'name',
                'shortName',
                'seoTitle',
                'logo',
                'description',
                'ogImage',
                'region',
                'slogan',
                'footerCopyright',
                'footerLegalNote',
                'contact',
                'complaintsEmail',
                'social',
            ], array_keys($props['site']), $url);

            foreach ($markers as $marker) {
                $this->assertStringNotContainsString($marker, $serialized, $url);
                $this->assertStringNotContainsString($marker, $html, $url);
            }
        }
    }

    public function test_updating_one_setting_leaves_existing_settings_intact(): void
    {
        $owner = $this->owner();

        $this->updateSettings($owner, [
            ['key' => 'contact.email', 'value' => 'keep@example.test'],
            ['key' => 'footer.copyright', 'translations' => ['en' => 'Keep this footer', 'ar' => 'احتفظ بهذا التذييل']],
            ['key' => 'organization.logo', 'value' => '/assets/keep-logo.png'],
            ['key' => 'social.facebook', 'value' => '@keep'],
        ]);

        $this->updateSettings($owner, [
            ['key' => 'organization.name', 'translations' => [
                'en' => 'Updated organization name',
                'ar' => 'اسم المحدث',
            ]],
        ]);

        $this->assertDatabaseHas('website_settings', [
            'key' => 'contact.email',
            'value' => 'keep@example.test',
        ]);
        $this->assertDatabaseHas('website_settings', [
            'key' => 'organization.logo',
            'value' => '/assets/keep-logo.png',
        ]);
        $this->assertDatabaseHas('website_settings', [
            'key' => 'social.facebook',
            'value' => 'https://www.facebook.com/keep',
        ]);
        $this->assertDatabaseHas('website_setting_translations', [
            'locale' => 'en',
            'value' => 'Keep this footer',
        ]);

        $site = $this->props($this->get('/en/contact'))['site'];
        $this->assertSame('Updated organization name', $site['name']);
        $this->assertSame('keep@example.test', $site['contact']['email']);
        $this->assertSame('Keep this footer', $site['footerCopyright']);
        $this->assertSame('/assets/keep-logo.png', $site['logo']);
    }

    public function test_error_pages_render_cached_public_identity_without_database_queries(): void
    {
        $owner = $this->owner();

        $this->updateSettings($owner, [
            ['key' => 'organization.name', 'translations' => ['en' => 'Error page organization']],
            ['key' => 'organization.logo', 'value' => '/assets/error-logo.png'],
        ]);

        $this->get('/en/about')->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $response = $this->get('/en/pcdr-no-database-404-probe')->assertNotFound();
        } finally {
            DB::disableQueryLog();
        }

        $html = str_replace('\\/', '/', (string) $response->getContent());

        $this->assertStringContainsString('Error page organization', $html);
        $this->assertStringContainsString('/assets/error-logo.png', $html);
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_public_display_locations_all_consume_the_central_site_payload(): void
    {
        $sources = [
            'app/Services/Seo.php' => ['publicIdentity'],
            'app/Http/Controllers/Public/HomepageApiController.php' => ['publicIdentity'],
            'resources/js/Components/RedesignHome/ProPillHeader.vue' => ['site.value?.logo', 'site.value?.name'],
            'resources/js/Components/RedesignHome/ProPrototypeFooter.vue' => [
                'site.value?.contact',
                'site.value?.social',
                'site.value?.footerCopyright',
                'site.value?.footerLegalNote',
            ],
            'resources/js/Components/RedesignHome/ProAboutTabs.vue' => ['site.value?.logo'],
            'resources/js/Pages/Public/About.vue' => ['site.value?.logo', 'site.value?.description'],
            'resources/js/Pages/Public/Complaints.vue' => ['site.value?.complaintsEmail'],
            'resources/js/Layouts/AuthLayout.vue' => ['site.value?.logo', 'site.value?.shortName', 'site.value?.name'],
            'resources/js/Components/Auth/BrandIntro.vue' => ['site.value?.logo', 'site.value?.name'],
            'resources/js/Layouts/CmsLayout.vue' => ['site.value?.logo', 'site.value?.shortName'],
            'resources/js/Components/Dashboard/DashboardChrome.vue' => ['site.value?.logo', 'site.value?.shortName'],
            'resources/js/Components/BrandMark.vue' => ['site.value?.logo'],
            'resources/views/app.blade.php' => ['publicIdentity', "\$siteIdentity['name']", "\$siteIdentity['logo']"],
            'resources/views/errors/layout.blade.php' => ['cachedPublicIdentity', '$shortName', '$logo'],
        ];

        foreach ($sources as $path => $needles) {
            $absolute = base_path($path);
            $this->assertFileExists($absolute, $path);
            $source = (string) file_get_contents($absolute);

            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $source, $path);
            }
        }
    }

    /** @param list<array<string, mixed>> $settings */
    private function updateSettings(User $user, array $settings, string $locale = 'en'): void
    {
        $this->actingAs($user)
            ->put("/{$locale}/cms/settings", ['settings' => $settings])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    private function owner(): User
    {
        return User::factory()->platformOwner()->create();
    }

    /** @param list<string> $keys */
    private function limitedUser(array $keys): User
    {
        $role = Role::query()->create([
            'key' => 'public-settings-limited-'.Str::random(6),
            'name' => 'Public settings limited user',
        ]);

        foreach ($keys as $key) {
            $definition = (array) config('permissions.'.$key, []);
            $permission = Permission::query()->firstOrCreate(['key' => $key], [
                'name' => $definition['label'] ?? $key,
                'module' => $definition['module'] ?? 'content',
                'is_protected' => false,
            ]);
            $role->permissions()->attach($permission);
        }

        $user = User::factory()->create();
        app(AccessManagementService::class)->syncRoles($user, [$role->getKey()]);

        return $user;
    }

    private function setting(string $key, string $value, bool $public = true): void
    {
        WebsiteSetting::query()->create([
            'group' => Str::before($key, '.'),
            'key' => $key,
            'type' => 'string',
            'is_public' => $public,
            'is_localized' => false,
            'value' => $value,
        ]);
    }

    /** @param array<string, string> $values */
    private function localizedSetting(string $key, array $values): void
    {
        $setting = WebsiteSetting::query()->create([
            'group' => Str::before($key, '.'),
            'key' => $key,
            'type' => 'string',
            'is_public' => true,
            'is_localized' => true,
        ]);

        foreach ($values as $locale => $value) {
            WebsiteSettingTranslation::query()->create([
                'website_setting_id' => $setting->id,
                'locale' => $locale,
                'value' => $value,
            ]);
        }
    }

    private function fakeImage(string $name): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ1sAAAAASUVORK5CYII=',
            true,
        );

        return UploadedFile::fake()->createWithContent($name, $png === false ? '' : $png);
    }

    /** @return array<string, mixed> */
    private function props(TestResponse $response): array
    {
        return (array) ($this->inertiaPage($response)['props'] ?? []);
    }

    /** @return array<string, mixed> */
    private function inertiaPage(TestResponse $response): array
    {
        $html = (string) $response->getContent();
        $this->assertSame(
            1,
            preg_match('/<script data-page="app" type="application\/json">(.*?)<\/script>/s', $html, $matches),
            'Inertia page payload was not found in the HTML response.',
        );

        $page = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($page);

        return $page;
    }
}
