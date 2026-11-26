<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\AccessManagementService;
use App\Services\SiteSettings;
use App\Support\SiteSettingsCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CmsSiteSettingsRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', '12345678901234567890123456789012');
        config()->set('cms.local_auth_enabled', false);

        $this->withoutVite();
    }

    public function test_settings_page_exposes_only_the_canonical_public_catalog(): void
    {
        $owner = User::factory()->platformOwner()->create();

        $this->actingAs($owner)->get('/ar/cms/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cms/Settings/Index')
                ->where('settings', fn ($settings): bool => $settings->count() === 22
                    && $settings->pluck('key')->all() === SiteSettingsCatalog::keys()
                    && $settings->every(fn (array $setting): bool => ! str_starts_with($setting['key'], 'app.')
                        && ! str_starts_with($setting['key'], 'mail.')))
                ->where('groups', fn ($groups): bool => $groups->pluck('key')->all() === SiteSettingsCatalog::groupOrder()
                    && $groups->every(fn (array $group): bool => $group['label'] !== ''))
                ->where('canUpdate', true)
                ->where('uploadKeys', ['organization.logo', 'seo.og_image']));

        $this->assertTrue(Route::has('cms.settings.index'));
        $this->assertTrue(Route::has('cms.settings.update'));
        $this->assertTrue(Route::has('cms.settings.upload-image'));
        $this->assertTrue(Route::has('admin.settings.index'));
    }

    public function test_update_persists_values_flushes_public_cache_and_writes_audit(): void
    {
        $owner = User::factory()->platformOwner()->create();

        WebsiteSetting::query()->create([
            'group' => 'contact',
            'key' => 'contact.email',
            'type' => 'email',
            'is_public' => true,
            'is_localized' => false,
            'value' => 'old@example.test',
        ]);
        app(SiteSettings::class)->flush();

        $this->actingAs($owner)->put('/ar/cms/settings', [
            'settings' => [
                ['key' => 'contact.email', 'value' => ' info@example.test '],
                ['key' => 'organization.name', 'translations' => ['ar' => 'جمعية العمل التشاركي', 'en' => 'PCDR']],
                ['key' => 'organization.logo', 'value' => '/assets/pcdr-homepage/logo/pcdr-logo.png'],
                ['key' => 'social.facebook', 'value' => 'https://www.facebook.com/pcdr'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('website_settings', [
            'key' => 'contact.email',
            'value' => 'info@example.test',
            'is_public' => true,
        ]);
        $this->assertDatabaseHas('website_settings', [
            'key' => 'organization.logo',
            'value' => '/assets/pcdr-homepage/logo/pcdr-logo.png',
        ]);
        $this->assertDatabaseHas('website_setting_translations', [
            'locale' => 'ar',
            'value' => 'جمعية العمل التشاركي',
        ]);

        $values = app(SiteSettings::class)->publicValues('ar');
        $this->assertSame('info@example.test', $values['contact.email']);
        $this->assertSame('جمعية العمل التشاركي', $values['organization.name']);
        $this->assertSame('https://www.facebook.com/pcdr', $values['social.facebook']);

        $audit = AuditLog::query()->where('action', 'cms.settings.update')->latest('id')->firstOrFail();
        $this->assertContains('contact.email', $audit->context['keys']);
        $this->assertSame('info@example.test', $audit->after['contact.email']);
    }

    public function test_social_values_are_normalized_on_save_and_invalid_values_are_rejected(): void
    {
        $owner = User::factory()->platformOwner()->create();

        $this->actingAs($owner)->put('/ar/cms/settings', [
            'settings' => [
                ['key' => 'social.tiktok', 'value' => ' @pcdr '],
                ['key' => 'social.whatsapp', 'value' => '+970 59 123 4567'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('website_settings', [
            'key' => 'social.tiktok',
            'value' => 'https://www.tiktok.com/@pcdr',
        ]);
        $this->assertDatabaseHas('website_settings', [
            'key' => 'social.whatsapp',
            'value' => 'https://wa.me/970591234567',
        ]);

        $links = app(SiteSettings::class)->socialLinks('en');
        $this->assertContains([
            'platform' => 'tiktok',
            'label' => 'TikTok',
            'url' => 'https://www.tiktok.com/@pcdr',
        ], $links);
        $this->assertContains([
            'platform' => 'whatsapp',
            'label' => 'WhatsApp',
            'url' => 'https://wa.me/970591234567',
        ], $links);

        $this->actingAs($owner)->put('/ar/cms/settings', [
            'settings' => [
                ['key' => 'social.facebook', 'value' => 'javascript:alert(1)'],
            ],
        ])->assertSessionHasErrors('settings.0.value');

        $this->assertDatabaseMissing('website_settings', ['key' => 'social.facebook']);
    }

    public function test_update_rejects_invalid_values_and_unknown_setting_keys(): void
    {
        $owner = User::factory()->platformOwner()->create();

        $this->actingAs($owner)->put('/ar/cms/settings', [
            'settings' => [
                ['key' => 'contact.email', 'value' => 'not-an-email'],
                ['key' => 'app.key', 'value' => 'secret'],
                ['key' => 'organization.logo', 'value' => 'javascript:alert(1)'],
                ['key' => 'website.default_language', 'value' => 'fr'],
            ],
        ])->assertSessionHasErrors([
            'settings.0.value',
            'settings.1.key',
            'settings.2.value',
            'settings.3.value',
        ]);

        $this->assertDatabaseMissing('website_settings', ['key' => 'app.key']);
        $this->assertDatabaseMissing('website_settings', ['key' => 'contact.email']);
    }

    public function test_unauthorized_users_cannot_read_or_mutate_site_settings(): void
    {
        $user = $this->userWith(['news.view']);

        $this->actingAs($user)->get('/ar/cms/settings')->assertForbidden();
        $this->actingAs($user)->put('/ar/cms/settings', ['settings' => []])->assertForbidden();
        $this->actingAs($user)->post('/ar/cms/settings/image', [
            'key' => 'organization.logo',
            'file' => $this->fakeImage('logo.png'),
        ])->assertForbidden();
    }

    public function test_image_upload_updates_public_setting_and_preserves_old_asset(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/old-logo.png', 'old-logo-content');

        $owner = User::factory()->platformOwner()->create();

        WebsiteSetting::query()->create([
            'group' => 'organization',
            'key' => 'organization.logo',
            'type' => 'url',
            'is_public' => true,
            'is_localized' => false,
            'value' => '/storage/settings/old-logo.png',
        ]);

        $this->actingAs($owner)->post('/ar/cms/settings/image', [
            'key' => 'organization.logo',
            'file' => $this->fakeImage('new-logo.png'),
        ])->assertRedirect();

        $media = Media::query()->where('original_name', 'new-logo.png')->firstOrFail();
        $setting = WebsiteSetting::query()->where('key', 'organization.logo')->firstOrFail();

        $this->assertStringNotContainsString('old-logo.png', (string) $setting->value);
        $this->assertSame($media->url(), $setting->value);
        $this->assertTrue(Storage::disk('public')->exists('settings/old-logo.png'));
        $this->assertTrue(Storage::disk('public')->exists($media->path));
        $this->assertFalse((bool) $media->is_restricted);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cms.settings.image_uploaded']);
    }

    public function test_image_upload_rejects_unsupported_settings_and_file_types(): void
    {
        $owner = User::factory()->platformOwner()->create();

        $this->actingAs($owner)->post('/ar/cms/settings/image', [
            'key' => 'contact.email',
            'file' => $this->fakeImage('logo.png'),
        ])->assertSessionHasErrors('key');

        $this->actingAs($owner)->post('/ar/cms/settings/image', [
            'key' => 'seo.og_image',
            'file' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseMissing('media', ['original_name' => 'logo.png']);
    }

    private function fakeImage(string $name): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ1sAAAAASUVORK5CYII=',
            true,
        );

        return UploadedFile::fake()->createWithContent($name, $png === false ? '' : $png);
    }

    /** @param list<string> $keys */
    private function userWith(array $keys): User
    {
        $role = Role::query()->create([
            'key' => 'settings-limited-'.count($keys),
            'name' => 'Settings limited user',
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
}
