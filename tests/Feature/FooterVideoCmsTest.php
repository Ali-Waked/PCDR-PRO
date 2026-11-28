<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\DeleteMedia;
use App\Enums\MediaType;
use App\Exceptions\MediaInUseException;
use App\Models\Media;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\AccessManagementService;
use App\Services\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class FooterVideoCmsTest extends TestCase
{
    use RefreshDatabase;

    private const SANDBOX = 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-presentation';

    private const YOUTUBE_ALLOW = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';

    private const FACEBOOK_ALLOW = 'encrypted-media; picture-in-picture';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', '12345678901234567890123456789012');
        config()->set('cms.local_auth_enabled', false);

        $this->withoutVite();
        app(SiteSettings::class)->flush();
    }

    public function test_youtube_video_is_persisted_and_delivered_as_a_safe_public_payload(): void
    {
        $owner = User::factory()->platformOwner()->create();
        $poster = $this->publicImage();

        $this->save($owner, [
            ['key' => 'footer.video.provider', 'value' => 'youtube'],
            ['key' => 'footer.video.url', 'value' => 'https://youtu.be/abcdefghijk?t=12'],
            ['key' => 'footer.video.title', 'translations' => [
                'ar' => 'الفيلم التعريفي بالجمعية',
                'en' => 'Association Introduction Film',
            ]],
            ['key' => 'footer.video.poster_media_id', 'value' => (string) $poster->id],
            ['key' => 'footer.video.visible', 'value' => '1'],
        ])->assertRedirect();

        $this->assertDatabaseHas('website_settings', [
            'key' => 'footer.video.provider',
            'value' => 'youtube',
            'is_public' => 1,
        ]);
        $this->assertDatabaseHas('website_settings', [
            'key' => 'footer.video.url',
            'value' => 'https://youtu.be/abcdefghijk?t=12',
        ]);
        $this->assertSame(
            'الفيلم التعريفي بالجمعية',
            WebsiteSetting::query()->where('key', 'footer.video.title')->firstOrFail()->t('value', 'ar'),
        );

        $expected = [
            'visible' => true,
            'available' => true,
            'provider' => 'youtube',
            'title' => 'Association Introduction Film',
            'url' => 'https://www.youtube.com/watch?v=abcdefghijk',
            'watchUrl' => 'https://www.youtube.com/watch?v=abcdefghijk',
            'embedUrl' => 'https://www.youtube-nocookie.com/embed/abcdefghijk',
            'posterUrl' => $poster->url(),
            'sandbox' => self::SANDBOX,
            'allow' => self::YOUTUBE_ALLOW,
        ];

        $this->assertSame($expected, app(SiteSettings::class)->footerVideo('en'));
        $this->assertSame($expected, $this->sitePayload('/en/contact'));
        $this->assertSame(
            'الفيلم التعريفي بالجمعية',
            $this->sitePayload('/ar/contact')['title'],
        );
    }

    public function test_facebook_video_uses_the_official_plugin_endpoint(): void
    {
        $owner = User::factory()->platformOwner()->create();

        $this->save($owner, [
            ['key' => 'footer.video.provider', 'value' => 'facebook'],
            ['key' => 'footer.video.url', 'value' => 'https://www.facebook.com/reel/123456789012345/'],
            ['key' => 'footer.video.title', 'translations' => ['ar' => 'فيلم الجمعية', 'en' => 'Association film']],
            ['key' => 'footer.video.visible', 'value' => '1'],
        ])->assertRedirect();

        $expected = [
            'visible' => true,
            'available' => true,
            'provider' => 'facebook',
            'title' => 'Association film',
            'url' => 'https://www.facebook.com/watch/?v=123456789012345',
            'watchUrl' => 'https://www.facebook.com/watch/?v=123456789012345',
            'embedUrl' => 'https://www.facebook.com/plugins/video.php?href=https%3A%2F%2Fwww.facebook.com%2Fwatch%2F%3Fv%3D123456789012345&show_text=false&width=560',
            'posterUrl' => null,
            'sandbox' => self::SANDBOX,
            'allow' => self::FACEBOOK_ALLOW,
        ];

        $this->assertSame($expected, app(SiteSettings::class)->footerVideo('en'));
        $this->assertSame($expected, $this->sitePayload('/en/contact'));
    }

    public function test_updating_the_url_refreshes_the_cached_public_player(): void
    {
        $owner = User::factory()->platformOwner()->create();

        $this->save($owner, [
            ['key' => 'footer.video.provider', 'value' => 'youtube'],
            ['key' => 'footer.video.url', 'value' => 'https://www.youtube.com/watch?v=abcdefghijk'],
            ['key' => 'footer.video.visible', 'value' => '1'],
        ])->assertRedirect();

        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/abcdefghijk',
            $this->sitePayload('/en/contact')['embedUrl'],
        );

        $this->save($owner, [
            ['key' => 'footer.video.provider', 'value' => 'youtube'],
            ['key' => 'footer.video.url', 'value' => 'https://www.youtube.com/shorts/zyxwvutsrqp'],
            ['key' => 'footer.video.visible', 'value' => '1'],
        ])->assertRedirect();

        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/zyxwvutsrqp',
            $this->sitePayload('/en/contact')['embedUrl'],
        );
    }

    public function test_visibility_is_independent_from_the_saved_configuration(): void
    {
        $owner = User::factory()->platformOwner()->create();
        $poster = $this->publicImage();

        $this->save($owner, [
            ['key' => 'footer.video.provider', 'value' => 'youtube'],
            ['key' => 'footer.video.url', 'value' => 'https://www.youtube.com/watch?v=abcdefghijk'],
            ['key' => 'footer.video.title', 'translations' => ['ar' => 'الفيلم التعريفي', 'en' => 'Introduction film']],
            ['key' => 'footer.video.poster_media_id', 'value' => (string) $poster->id],
            ['key' => 'footer.video.visible', 'value' => '1'],
        ])->assertRedirect();

        $this->save($owner, [
            ['key' => 'footer.video.visible', 'value' => '0'],
        ])->assertRedirect();

        $this->assertDatabaseHas('website_settings', [
            'key' => 'footer.video.provider',
            'value' => 'youtube',
        ]);
        $this->assertDatabaseHas('website_settings', [
            'key' => 'footer.video.url',
            'value' => 'https://www.youtube.com/watch?v=abcdefghijk',
        ]);
        $this->assertDatabaseHas('website_settings', [
            'key' => 'footer.video.poster_media_id',
            'value' => (string) $poster->id,
        ]);

        $hidden = $this->sitePayload('/en/contact');
        $this->assertFalse($hidden['visible']);
        $this->assertTrue($hidden['available']);
        $this->assertSame('https://www.youtube-nocookie.com/embed/abcdefghijk', $hidden['embedUrl']);
        $this->assertSame('Introduction film', $hidden['title']);
        $this->assertSame($poster->url(), $hidden['posterUrl']);

        $this->save($owner, [
            ['key' => 'footer.video.visible', 'value' => '1'],
        ])->assertRedirect();

        $this->assertTrue($this->sitePayload('/en/contact')['visible']);
    }

    public function test_unsupported_or_unsafe_video_urls_are_rejected_before_persistence(): void
    {
        $owner = User::factory()->platformOwner()->create();

        foreach ([
            'youtube' => [
                'http://www.youtube.com/watch?v=abcdefghijk',
                'https://vimeo.com/123456789',
                'https://youtube.com.evil.test/watch?v=abcdefghijk',
                'https://www.youtube.com/watch?v=short',
                'javascript:alert(1)',
                'data:text/html,<script>alert(1)</script>',
                '<iframe src="https://www.youtube.com/embed/abcdefghijk"></iframe>',
            ],
            'facebook' => [
                'https://facebook.com.evil.test/watch?v=123456789012345',
                'https://www.facebook.com/watch?v=abc',
                'https://www.tiktok.com/@pcdr/video/123456789012345',
                'javascript:alert(1)',
                '<iframe src="https://www.facebook.com/plugins/video.php"></iframe>',
            ],
        ] as $provider => $urls) {
            foreach ($urls as $url) {
                $this->save($owner, [
                    ['key' => 'footer.video.provider', 'value' => $provider],
                    ['key' => 'footer.video.url', 'value' => $url],
                ])->assertSessionHasErrors('settings.1.value');

                $this->assertDatabaseMissing('website_settings', ['key' => 'footer.video.url']);
            }
        }

        $this->assertSame('Enter a valid HTTPS video URL for the selected provider.', __('website.cms.setting_video_url_invalid', [], 'en'));
        $this->assertSame('أدخل رابط فيديو HTTPS صالحًا للمزوّد المحدد.', __('website.cms.setting_video_url_invalid', [], 'ar'));
    }

    public function test_poster_must_be_a_public_image_from_the_media_library(): void
    {
        $owner = User::factory()->platformOwner()->create();
        $restricted = $this->media([
            'is_restricted' => true,
            'type' => MediaType::Image,
            'mime_type' => 'image/png',
            'extension' => 'png',
        ]);
        $document = $this->media([
            'is_restricted' => false,
            'type' => MediaType::Document,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
        ]);

        foreach ([(string) $restricted->id, (string) $document->id, '999999'] as $posterId) {
            $this->save($owner, [
                ['key' => 'footer.video.poster_media_id', 'value' => $posterId],
            ])->assertSessionHasErrors('settings.0.value');

            $this->assertDatabaseMissing('website_settings', ['key' => 'footer.video.poster_media_id']);
        }

        $public = $this->publicImage();
        $this->save($owner, [
            ['key' => 'footer.video.poster_media_id', 'value' => (string) $public->id],
        ])->assertRedirect();

        $this->assertSame($public->url(), $this->sitePayload('/en/contact')['posterUrl']);

        WebsiteSetting::query()
            ->where('key', 'footer.video.poster_media_id')
            ->update(['value' => (string) $restricted->id]);
        app(SiteSettings::class)->flush();

        $this->assertNull($this->sitePayload('/en/contact')['posterUrl']);
    }

    public function test_poster_cannot_be_deleted_while_it_is_referenced_by_the_video_setting(): void
    {
        $owner = User::factory()->platformOwner()->create();
        $poster = $this->publicImage();

        $this->save($owner, [
            ['key' => 'footer.video.poster_media_id', 'value' => (string) $poster->id],
        ])->assertRedirect();

        $this->expectException(MediaInUseException::class);

        app(DeleteMedia::class)->handle($poster);
    }

    public function test_backend_preview_returns_the_same_server_resolved_player(): void
    {
        $owner = User::factory()->platformOwner()->create();

        $this->actingAs($owner)->postJson('/en/cms/settings/video-preview', [
            'provider' => 'youtube',
            'url' => 'https://www.youtube.com/watch?v=abcdefghijk',
        ])->assertOk()->assertExactJson([
            'provider' => 'youtube',
            'url' => 'https://www.youtube.com/watch?v=abcdefghijk',
            'embedUrl' => 'https://www.youtube-nocookie.com/embed/abcdefghijk',
            'sandbox' => self::SANDBOX,
            'allow' => self::YOUTUBE_ALLOW,
        ]);

        $this->actingAs($owner)->postJson('/en/cms/settings/video-preview', [
            'provider' => 'facebook',
            'url' => 'https://www.facebook.com/watch?v=123456789012345',
        ])->assertOk()->assertJsonPath('provider', 'facebook')
            ->assertJsonPath('embedUrl', 'https://www.facebook.com/plugins/video.php?href=https%3A%2F%2Fwww.facebook.com%2Fwatch%2F%3Fv%3D123456789012345&show_text=false&width=560');

        $this->actingAs($owner)->postJson('/ar/cms/settings/video-preview', [
            'provider' => 'youtube',
            'url' => 'https://vimeo.com/123456789',
        ])->assertUnprocessable()->assertJsonValidationErrors('url');

        $this->actingAs($owner)->postJson('/ar/cms/settings/video-preview', [
            'provider' => 'vimeo',
            'url' => 'https://vimeo.com/123456789',
        ])->assertUnprocessable()->assertJsonValidationErrors('provider');
    }

    public function test_users_without_settings_permission_cannot_configure_or_preview_the_video(): void
    {
        $user = $this->userWith(['news.view']);

        $this->actingAs($user)->get('/en/cms/settings')->assertForbidden();
        $this->save($user, [
            ['key' => 'footer.video.url', 'value' => 'https://www.youtube.com/watch?v=abcdefghijk'],
        ])->assertForbidden();
        $this->actingAs($user)->postJson('/en/cms/settings/video-preview', [
            'provider' => 'youtube',
            'url' => 'https://www.youtube.com/watch?v=abcdefghijk',
        ])->assertForbidden();

        $this->assertDatabaseMissing('website_settings', ['key' => 'footer.video.url']);
    }

    public function test_footer_and_cms_sources_use_the_central_payload_and_secure_player_attributes(): void
    {
        $footer = (string) File::get(resource_path('js/Components/RedesignHome/ProPrototypeFooter.vue'));

        foreach ([
            'site.value?.footerVideo',
            'data-footer-video',
            ':sandbox="footerVideo.sandbox"',
            ':allow="footerVideo.allow"',
            'referrerpolicy="strict-origin-when-cross-origin"',
            'loading="lazy"',
            'aspect-ratio: 16 / 9',
            'prefers-reduced-motion',
            'data-footer-video-play',
            'data-footer-video-external',
        ] as $marker) {
            $this->assertStringContainsString($marker, $footer, $marker.' marker missing.');
        }

        foreach (['videoSource(', '<dialog', 'autoplay', 'modal'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $footer, $forbidden.' marker must not be used.');
        }

        $settings = (string) File::get(resource_path('js/Pages/Cms/Settings/Index.vue'));

        foreach ([
            'data-cms-footer-video',
            'cms-footer-video-provider',
            'cms-footer-video-url',
            'cms-footer-video-title-',
            'ContentMediaField',
            'cms-footer-video-poster',
            'cms-footer-video-visible',
            'FooterVideoPreview',
            "localPath('cms/settings/video-preview')",
        ] as $marker) {
            $this->assertStringContainsString($marker, $settings, $marker.' marker missing.');
        }

        $this->assertSame('المرئيات', __('ui.footer_video_heading', [], 'ar'));
        $this->assertSame('مشاهدة الفيديو', __('ui.footer_watch_video', [], 'ar'));
        $this->assertSame('Audiovisuals', __('ui.footer_video_heading', [], 'en'));
        $this->assertSame('Watch video', __('ui.footer_watch_video', [], 'en'));
    }

    /** @param list<array<string, mixed>> $settings */
    private function save(User $user, array $settings): TestResponse
    {
        return $this->actingAs($user)->put('/en/cms/settings', ['settings' => $settings]);
    }

    /** @return array<string, mixed> */
    private function sitePayload(string $url): array
    {
        $html = (string) $this->get($url)->assertOk()->getContent();
        $this->assertSame(
            1,
            preg_match('/<script data-page="app" type="application\/json">(.*?)<\/script>/s', $html, $matches),
            'Inertia page payload was not found in the HTML response.',
        );

        $page = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($page);

        return $page['props']['site']['footerVideo'];
    }

    /** @param array<string, mixed> $attributes */
    private function media(array $attributes): Media
    {
        return Media::factory()->create($attributes);
    }

    private function publicImage(): Media
    {
        return $this->media([
            'disk' => 'public',
            'path' => 'media/footer/poster-'.fake()->unique()->uuid().'.png',
            'type' => MediaType::Image,
            'mime_type' => 'image/png',
            'extension' => 'png',
            'is_restricted' => false,
        ]);
    }

    /** @param list<string> $keys */
    private function userWith(array $keys): User
    {
        $role = Role::query()->create([
            'key' => 'footer-video-limited-'.count($keys),
            'name' => 'Footer video limited user',
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
