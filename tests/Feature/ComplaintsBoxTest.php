<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\SiteSettings;
use Database\Seeders\SiteSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ComplaintsBoxTest extends TestCase
{
    use RefreshDatabase;

    private const EDITOR_EMAIL = 'complaints-editor@example.test';

    private const DEFAULT_EMAIL = 'complaints@pal-arc.org';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', '12345678901234567890123456789012');
        config()->set('cms.bootstrap.email', self::EDITOR_EMAIL);
    }

    #[DataProvider('localeProvider')]
    public function test_public_route_renders_localized_page_with_backend_email_and_seo(
        string $locale,
        string $direction,
        string $title,
        string $description,
    ): void {
        $this->seed(SiteSettingsSeeder::class);

        $response = $this->get("/{$locale}/complaints");
        $response->assertOk();

        $body = (string) $response->getContent();
        $this->assertStringContainsString(
            '<html lang="'.$locale.'" dir="'.$direction.'" data-locale="'.$locale.'">',
            $body,
        );

        $page = $this->inertiaPage($response);
        $props = $page['props'];

        $this->assertSame('Public/Complaints', $page['component']);
        $this->assertSame(self::DEFAULT_EMAIL, $props['complaintsEmail']);
        $this->assertSame($title, $props['meta']['title']);
        $this->assertSame($description, $props['meta']['description']);
        $this->assertSame(url("/{$locale}/complaints"), $props['meta']['canonical']);
        $this->assertSame(url('/ar/complaints'), $props['meta']['alternates']['ar']);
        $this->assertSame(url('/en/complaints'), $props['meta']['alternates']['en']);
        $this->assertSame($locale, $props['meta']['locale']);
        $this->assertSame($title, $props['meta']['og']['title']);
        $this->assertSame($description, $props['meta']['og']['description']);
        $this->assertSame($title, $props['meta']['twitter']['title']);
        $this->assertSame($description, $props['meta']['twitter']['description']);
        $this->assertStringContainsString('index', $props['meta']['robots']);
        $this->assertStringNotContainsString('noindex', $props['meta']['robots']);
    }

    public function test_complaints_route_is_registered_with_the_expected_name(): void
    {
        $this->assertTrue(Route::has('public.complaints'));
        $this->assertSame(url('/ar/complaints'), route('public.complaints', ['locale' => 'ar']));
        $this->assertSame(url('/en/complaints'), route('public.complaints', ['locale' => 'en']));
    }

    #[DataProvider('localeProvider')]
    public function test_cms_setting_catalog_exposes_exact_localized_label_and_description(
        string $locale,
        string $direction,
        string $title,
        string $description,
    ): void {
        $this->actingAs($this->editor());

        $props = $this->inertiaPage($this->get("/{$locale}/cms/settings"))['props'];
        $settings = collect($props['settings'])->keyBy('key');
        $setting = $settings->get('contact.complaints_email');

        $this->assertNotNull($setting);
        $this->assertSame('contact', $setting['group']);
        $this->assertSame('email', $setting['type']);
        $this->assertFalse($setting['localized']);
        $this->assertSame(
            $locale === 'ar' ? 'البريد الإلكتروني لصندوق الشكاوى' : 'Complaints Box Email',
            $setting['label'],
        );
        $this->assertSame(
            $locale === 'ar'
                ? 'البريد الإلكتروني الرسمي الذي يظهر للزوار للتواصل مع الجمعية بشأن الشكاوى.'
                : 'The official email address displayed to visitors for submitting complaints.',
            $setting['description'],
        );

        $this->assertCount(22, $settings);
        $this->assertTrue($settings->every(fn (array $entry): bool => ! str_starts_with($entry['key'], 'app.')
            && ! str_starts_with($entry['key'], 'mail.')));
    }

    public function test_authorized_update_persists_the_email_and_invalidates_public_cache(): void
    {
        $this->seed(SiteSettingsSeeder::class);

        $this->actingAs($this->editor())
            ->put('/en/cms/settings', $this->payload('contact.complaints_email', 'feedback@example.test'))
            ->assertRedirect();

        $this->assertDatabaseHas('website_settings', [
            'key' => 'contact.complaints_email',
            'value' => 'feedback@example.test',
        ]);
        $this->assertSame('feedback@example.test', app(SiteSettings::class)->complaintsEmail('en'));
        $this->assertSame(
            'feedback@example.test',
            $this->inertiaPage($this->get('/en/complaints'))['props']['complaintsEmail'],
        );
    }

    public function test_invalid_email_is_rejected_without_replacing_the_previous_value(): void
    {
        $this->seed(SiteSettingsSeeder::class);

        $editor = $this->editor();

        $this->actingAs($editor)
            ->put('/en/cms/settings', $this->payload('contact.complaints_email', 'official@example.test'))
            ->assertRedirect();

        $this->actingAs($editor)
            ->put('/en/cms/settings', $this->payload('contact.complaints_email', 'not-an-email'))
            ->assertSessionHasErrors(['settings.0.value']);

        $this->assertDatabaseHas('website_settings', [
            'key' => 'contact.complaints_email',
            'value' => 'official@example.test',
        ]);
        $this->assertSame('official@example.test', app(SiteSettings::class)->complaintsEmail('en'));
        $this->assertSame(
            'official@example.test',
            $this->inertiaPage($this->get('/en/complaints'))['props']['complaintsEmail'],
        );
    }

    public function test_unauthorized_user_cannot_update_the_email(): void
    {
        $this->seed(SiteSettingsSeeder::class);

        $outsider = User::factory()->create([
            'email' => 'complaints-outsider@example.test',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($outsider)
            ->put('/en/cms/settings', $this->payload('contact.complaints_email', 'changed@example.test'))
            ->assertForbidden();

        $this->assertDatabaseHas('website_settings', [
            'key' => 'contact.complaints_email',
            'value' => self::DEFAULT_EMAIL,
        ]);
    }

    public function test_empty_email_is_allowed_and_exposed_as_an_empty_public_value(): void
    {
        $this->seed(SiteSettingsSeeder::class);

        $this->actingAs($this->editor())
            ->put('/ar/cms/settings', $this->payload('contact.complaints_email', ''))
            ->assertRedirect();

        $this->assertDatabaseHas('website_settings', [
            'key' => 'contact.complaints_email',
            'value' => null,
        ]);
        $this->assertNull(app(SiteSettings::class)->complaintsEmail('ar'));
        $this->assertSame(
            '',
            $this->inertiaPage($this->get('/ar/complaints'))['props']['complaintsEmail'],
        );
    }

    public function test_seeder_initializes_default_email_without_overwriting_existing_values(): void
    {
        $setting = WebsiteSetting::query()->create([
            'group' => 'contact',
            'key' => 'contact.complaints_email',
            'type' => 'email',
            'is_public' => true,
            'is_localized' => false,
            'value' => 'admin-choice@example.test',
        ]);

        $this->seed(SiteSettingsSeeder::class);

        $this->assertSame('admin-choice@example.test', $setting->fresh()?->value);

        $setting->forceFill(['value' => null])->save();
        $this->seed(SiteSettingsSeeder::class);

        $this->assertSame(self::DEFAULT_EMAIL, $setting->fresh()?->value);
    }

    public function test_public_response_does_not_expose_private_platform_settings(): void
    {
        $this->seed(SiteSettingsSeeder::class);

        $props = $this->inertiaPage($this->get('/en/complaints'))['props'];
        $publicSettings = $props['settings']['public'];

        foreach (array_keys($publicSettings) as $key) {
            $this->assertMatchesRegularExpression('/^(general|branding|localization)\./', $key);
        }

        $json = json_encode($props, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('mail.host', $json);
        $this->assertStringNotContainsString('mail.password', $json);
        $this->assertStringNotContainsString('MAIL_HOST', $json);
        $this->assertStringNotContainsString('app.key', $json);
    }

    public function test_frontend_sources_read_the_setting_from_the_backend_and_never_hardcode_it(): void
    {
        $page = (string) file_get_contents(resource_path('js/Pages/Public/Complaints.vue'));
        $helper = (string) file_get_contents(resource_path('js/Support/complaintsEmail.js'));
        $css = (string) file_get_contents(resource_path('css/complaints-box.css'));

        foreach ([$page, $helper, $css] as $source) {
            $this->assertStringNotContainsString(self::DEFAULT_EMAIL, $source);
        }

        $this->assertStringContainsString('complaintsEmail', $page);
        $this->assertStringContainsString('complaintsMailto', $page);
        $this->assertStringContainsString('copyComplaintsEmail', $page);
        $this->assertStringContainsString('role="status"', $page);
        $this->assertStringContainsString('aria-live="polite"', $page);
        $this->assertStringContainsString('aria-atomic="true"', $page);
        $this->assertStringContainsString('dir="ltr"', $page);
        $this->assertStringContainsString('ui.complaints.not_configured', $page);
        $this->assertStringContainsString('PublicLayout', $page);
        $this->assertStringNotContainsString('<form', $page);
        $this->assertStringNotContainsString('router.post', $page);
        $this->assertStringNotContainsString('localStorage', $page);
        $this->assertStringNotContainsString('linear-gradient', $css);
        $this->assertStringNotContainsString('animation:', $css);
    }

    /** @return array<int, array{0: string, 1: string, 2: string, 3: string}> */
    public static function localeProvider(): array
    {
        return [
            [
                'ar',
                'rtl',
                'صندوق الشكاوى | جمعية العمل التشاركي في الأزمات والكوارث',
                'قناة واضحة للتواصل مع جمعية العمل التشاركي في الأزمات والكوارث بشأن الشكاوى، مع إرشادات لكتابة الرسالة ومعلومات قد تساعد في فهمها.',
            ],
            [
                'en',
                'ltr',
                'Complaints Box | PCDR',
                'A clear channel for contacting PCDR about complaints, with guidance for preparing your email and details that may help explain your message.',
            ],
        ];
    }

    private function editor(): User
    {
        return User::factory()->create([
            'email' => self::EDITOR_EMAIL,
            'password' => Hash::make('password'),
        ]);
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function payload(string $key, ?string $value): array
    {
        return [
            'settings' => [
                [
                    'key' => $key,
                    'value' => $value,
                    'translations' => [],
                ],
            ],
        ];
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
