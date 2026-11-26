<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\Program;
use App\Models\Project;
use App\Models\Publication;
use App\Models\StrategicPillar;
use App\Models\VolunteerInquiry;
use Database\Seeders\WebsiteSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    private const HOME_TITLES = [
        'ar' => 'PCDR | جمعية العمل التشاركي في الأزمات والكوارث',
        'en' => 'PCDR | Participatory Action in Crises and Disasters',
    ];

    public function test_root_redirects_to_the_default_arabic_locale(): void
    {
        $this->get('/')->assertRedirect('/ar');
    }

    #[DataProvider('localeProvider')]
    public function test_public_home_renders_locale_correct_html_and_inertia_component(string $locale, string $direction): void
    {
        $response = $this->get("/{$locale}");
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString(
            '<html lang="'.$locale.'" dir="'.$direction.'" data-locale="'.$locale.'">',
            $html,
        );

        $page = $this->inertiaPage($response);
        $this->assertSame('Public/Home', $page['component']);
        $this->assertSame($locale, $page['props']['locale'] ?? $page['props']['meta']['locale'] ?? null);
    }

    public function test_server_rendered_seo_fallback_is_locale_specific_with_clean_alternates(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $response = $this->get("/{$locale}");
            $response->assertOk();

            $html = $response->getContent();
            $title = self::HOME_TITLES[$locale];
            $description = $locale === 'ar'
                ? 'من الاستجابة الإنسانية إلى التعافي والتنمية المستدامة — PCDR، قطاع غزة.'
                : (string) config('website.description.en');

            $this->assertMatchesRegularExpression(
                '/<title inertia>'.preg_quote($title, '/').'<\/title>/u',
                $html,
            );
            $this->assertStringContainsString('name="description" inertia content="'.$description.'"', $html);
            $this->assertStringContainsString('rel="canonical" inertia href="'.url('/'.$locale).'"', $html);
            $this->assertStringContainsString('hreflang="ar" href="'.url('/ar').'"', $html);
            $this->assertStringContainsString('hreflang="en" href="'.url('/en').'"', $html);
            $this->assertStringContainsString('hreflang="x-default" href="'.url('/ar').'"', $html);
            $this->assertStringNotContainsString(url('/ar/ar'), $html);
            $this->assertStringNotContainsString(url('/en/en'), $html);

            $this->assertSame(
                1,
                preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches),
                'JSON-LD block was not found in the HTML response.',
            );
            $jsonLd = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
            $this->assertIsArray($jsonLd);
            $this->assertArrayHasKey('@context', $jsonLd);
            $this->assertSame('https://schema.org', $jsonLd['@context']);
            $this->assertSame('Organization', $jsonLd['@type'] ?? null);

            $meta = $this->inertiaPage($response)['props']['meta'];
            $this->assertSame(url('/ar'), $meta['alternates']['ar'] ?? null);
            $this->assertSame(url('/en'), $meta['alternates']['en'] ?? null);
            $this->assertSame(url('/ar'), $meta['alternates']['x-default'] ?? null);
            $this->assertStringNotContainsString('/ar/ar', (string) json_encode($meta['alternates']));
        }
    }

    public function test_public_detail_routes_only_serve_published_records(): void
    {
        $publishedAt = now()->subDay();

        $draftPage = Page::create(['status' => ContentStatus::Draft->value]);
        $publishedPage = Page::create([
            'status' => ContentStatus::Published->value,
            'published_at' => $publishedAt,
        ]);
        $this->translate($draftPage, 'draft-page', 'Draft page');
        $this->translate($publishedPage, 'published-page', 'Published page');

        $draftArticle = Article::create(['status' => ContentStatus::Draft->value]);
        $publishedArticle = Article::create([
            'status' => ContentStatus::Published->value,
            'published_at' => $publishedAt,
        ]);
        $this->translate($draftArticle, 'draft-article', 'Draft article');
        $this->translate($publishedArticle, 'published-article', 'Published article');

        $draftProgram = Program::create(['status' => ContentStatus::Draft->value]);
        $publishedProgram = Program::create([
            'status' => ContentStatus::Published->value,
            'published_at' => $publishedAt,
        ]);
        $this->translate($draftProgram, 'draft-program', 'Draft program');
        $this->translate($publishedProgram, 'published-program', 'Published program');

        $draftPublication = Publication::create([
            'category' => 'annual_report',
            'status' => ContentStatus::Draft->value,
        ]);
        $publishedPublication = Publication::create([
            'category' => 'annual_report',
            'status' => ContentStatus::Published->value,
            'published_at' => $publishedAt,
        ]);
        $this->translate($draftPublication, 'draft-publication', 'Draft publication');
        $this->translate($publishedPublication, 'published-publication', 'Published publication');

        $draftPillar = StrategicPillar::create([
            'code' => 'D9',
            'status' => ContentStatus::Draft->value,
        ]);
        $publishedPillar = StrategicPillar::create([
            'code' => 'P9',
            'status' => ContentStatus::Published->value,
            'published_at' => $publishedAt,
        ]);
        $this->translate($draftPillar, 'draft-pillar', 'Draft pillar');
        $this->translate($publishedPillar, 'published-pillar', 'Published pillar');

        // Project publication is controlled by workflow_status; `status` is only
        // the project lifecycle (planned/ongoing/completed/archived).
        $draftProject = Project::create([
            'status' => 'ongoing',
            'workflow_status' => ContentStatus::Draft->value,
        ]);
        $publishedProject = Project::create([
            'status' => 'planned',
            'workflow_status' => ContentStatus::Published->value,
            'published_at' => $publishedAt,
        ]);
        $this->translate($draftProject, 'draft-project', 'Draft project');
        $this->translate($publishedProject, 'published-project', 'Published project');

        $routes = [
            'pages' => ['draft-page', 'published-page'],
            'news' => ['draft-article', 'published-article'],
            'programs' => ['draft-program', 'published-program'],
            'publications' => ['draft-publication', 'published-publication'],
            'strategic-areas' => ['draft-pillar', 'published-pillar'],
            'projects' => ['draft-project', 'published-project'],
        ];

        foreach (['ar', 'en'] as $locale) {
            foreach ($routes as $prefix => [$draftSlug, $publishedSlug]) {
                $this->get("/{$locale}/{$prefix}/{$draftSlug}")->assertNotFound();
                $this->get("/{$locale}/{$prefix}/{$publishedSlug}")->assertOk();
            }
        }

        $projectProps = $this->inertiaPage($this->get('/en/projects/published-project'))['props']['project'];
        $this->assertSame('Planned', $projectProps['status']);
    }

    public function test_valid_contact_inquiry_is_persisted_with_reference_and_hashed_ip(): void
    {
        $email = $this->uniqueEmail('contact-valid');
        $ip = '203.0.113.10';

        $response = $this->post('/ar/contact/inquiry', [
            'category' => 'general',
            'name' => 'Test Sender',
            'email' => $email,
            'subject' => 'Website test',
            'message' => 'This is a sufficiently long test message.',
            'consent' => '1',
            'website' => '',
        ], [
            'REMOTE_ADDR' => $ip,
            'User-Agent' => 'PCDR-Test/1.0',
        ]);

        $response->assertRedirect();
        $record = ContactMessage::query()->where('email', $email)->firstOrFail();

        $this->assertDatabaseCount('contact_messages', 1);
        $this->assertTrue(Str::isUlid($record->reference));
        $this->assertTrue((bool) $record->consent);
        $this->assertSame('ar', $record->locale);
        $this->assertSame('new', $record->status);
        $this->assertSame('PCDR-Test/1.0', $record->user_agent);
        $this->assertSame(hash('sha256', $ip.'|'.config('app.key')), $record->ip_hash);
        $this->assertSame(64, strlen((string) $record->ip_hash));
        $this->assertStringNotContainsString($ip, (string) $record->ip_hash);
        $this->assertArrayNotHasKey('ip', $record->getAttributes());
        $response->assertSessionHas('flash.success', __('website.forms.received', ['reference' => $record->reference], 'ar'));
    }

    public function test_valid_volunteer_inquiry_is_persisted_with_reference_and_hashed_ip(): void
    {
        $email = $this->uniqueEmail('volunteer-valid');
        $ip = '203.0.113.11';

        $response = $this->post('/en/volunteer/inquiry', [
            'name' => 'Test Volunteer',
            'email' => $email,
            'phone' => '+970 500 000 000',
            'area_of_interest' => 'Community response',
            'availability' => 'weekends',
            'message' => 'I would like to help with community activities.',
            'consent' => '1',
            'website' => '',
        ], ['REMOTE_ADDR' => $ip]);

        $response->assertRedirect();
        $record = VolunteerInquiry::query()->where('email', $email)->firstOrFail();

        $this->assertDatabaseCount('volunteer_inquiries', 1);
        $this->assertTrue(Str::isUlid($record->reference));
        $this->assertTrue((bool) $record->consent);
        $this->assertSame('en', $record->locale);
        $this->assertSame('new', $record->status);
        $this->assertSame(hash('sha256', $ip.'|'.config('app.key')), $record->ip_hash);
        $this->assertStringNotContainsString($ip, (string) $record->ip_hash);
        $response->assertSessionHas('flash.success', __('website.forms.received', ['reference' => $record->reference], 'en'));
    }

    public function test_partnership_inquiry_application_route_is_removed(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $this->post("/{$locale}/partnership/inquiry", [
                'organization' => 'Test Organization',
                'contact_name' => 'Test Contact',
                'email' => $this->uniqueEmail('partnership-removed'),
                'website' => 'https://example.org',
                'interest' => 'Coordination',
                'message' => 'We would like to discuss a possible partnership.',
                'consent' => '1',
            ])->assertNotFound();
        }

        $this->assertDatabaseCount('partnership_inquiries', 0);
    }

    #[DataProvider('inquiryFormProvider')]
    public function test_honeypot_fields_reject_inquiries_without_persisting_them(
        string $route,
        string $table,
        array $payload,
        string $honeypotField,
    ): void {
        $payload = array_merge($payload, ['email' => $this->uniqueEmail('honeypot')]);
        $payload[$honeypotField] = 'https://spam.example';

        $response = $this->post($route, $payload);

        $response->assertRedirect();
        $response->assertSessionHasErrors($honeypotField);
        $this->assertDatabaseCount($table, 0);
    }

    #[DataProvider('inquiryFormProvider')]
    public function test_missing_consent_rejects_inquiries_without_persisting_them(
        string $route,
        string $table,
        array $payload,
        string $honeypotField,
    ): void {
        $payload = array_merge($payload, [
            'email' => $this->uniqueEmail('no-consent'),
            'consent' => '0',
            $honeypotField => '',
        ]);

        $response = $this->post($route, $payload);

        $response->assertRedirect();
        $response->assertSessionHasErrors('consent');
        $this->assertDatabaseCount($table, 0);
    }

    public function test_invalid_contact_payload_is_rejected(): void
    {
        $contact = $this->post('/ar/contact/inquiry', [
            'category' => 'not-a-category',
            'name' => '',
            'email' => 'not-an-email',
            'message' => 'short',
            'consent' => '1',
            'website' => '',
        ]);
        $contact->assertRedirect();
        $contact->assertSessionHasErrors(['category', 'name', 'email', 'message']);
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_sitemap_contains_static_locale_paths_and_only_published_slugs(): void
    {
        $publishedAt = now()->subDay();

        $published = Page::create([
            'status' => ContentStatus::Published->value,
            'published_at' => $publishedAt,
        ]);
        $draft = Page::create(['status' => ContentStatus::Draft->value]);
        $this->translate($published, 'sitemap-published-page', 'Published page');
        $this->translate($draft, 'sitemap-draft-page', 'Draft page');

        $publishedArticle = Article::create([
            'status' => ContentStatus::Published->value,
            'published_at' => $publishedAt,
        ]);
        $this->translate($publishedArticle, 'sitemap-published-article', 'Published article');
        $draftArticle = Article::create(['status' => ContentStatus::Draft->value]);
        $this->translate($draftArticle, 'sitemap-draft-article', 'Draft article');

        $publishedProgram = Program::create([
            'status' => ContentStatus::Published->value,
            'published_at' => $publishedAt,
        ]);
        $this->translate($publishedProgram, 'sitemap-published-program', 'Published program');
        $draftProgram = Program::create(['status' => ContentStatus::Draft->value]);
        $this->translate($draftProgram, 'sitemap-draft-program', 'Draft program');

        $publishedPublication = Publication::create([
            'category' => 'annual_report',
            'status' => ContentStatus::Published->value,
            'published_at' => $publishedAt,
        ]);
        $this->translate($publishedPublication, 'sitemap-published-publication', 'Published publication');
        $draftPublication = Publication::create([
            'category' => 'annual_report',
            'status' => ContentStatus::Draft->value,
        ]);
        $this->translate($draftPublication, 'sitemap-draft-publication', 'Draft publication');

        $publishedPillar = StrategicPillar::create([
            'code' => 'S1',
            'status' => ContentStatus::Published->value,
            'published_at' => $publishedAt,
        ]);
        $this->translate($publishedPillar, 'sitemap-published-pillar', 'Published pillar');
        $draftPillar = StrategicPillar::create([
            'code' => 'S2',
            'status' => ContentStatus::Draft->value,
        ]);
        $this->translate($draftPillar, 'sitemap-draft-pillar', 'Draft pillar');

        $publishedProject = Project::create([
            'status' => 'planned',
            'workflow_status' => ContentStatus::Published->value,
            'published_at' => $publishedAt,
        ]);
        $this->translate($publishedProject, 'sitemap-published-project', 'Published project');
        $draftProject = Project::create([
            'status' => 'ongoing',
            'workflow_status' => ContentStatus::Draft->value,
        ]);
        $this->translate($draftProject, 'sitemap-draft-project', 'Draft project');

        $response = $this->get('/sitemap.xml');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        $xml = $response->getContent();
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"', $xml);

        foreach (['ar', 'en'] as $locale) {
            foreach (['', 'about', 'strategic-areas', 'programs', 'news', 'publications', 'transparency', 'partnerships', 'get-involved', 'contact', 'complaints'] as $path) {
                $this->assertStringContainsString(url('/'.$locale.($path ? '/'.$path : '')), $xml);
            }
        }

        foreach (['page', 'article', 'program', 'publication', 'pillar', 'project'] as $type) {
            $this->assertStringContainsString('sitemap-published-'.$type, $xml);
            $this->assertStringNotContainsString('sitemap-draft-'.$type, $xml);
        }
    }

    public function test_robots_txt_points_to_the_sitemap_and_disallows_cms(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $response->assertSee('User-agent: *');
        $response->assertSee('Allow: /ar');
        $response->assertSee('Allow: /en');
        $response->assertSee('Disallow: /cms');
        $response->assertSee('Sitemap: '.url('/sitemap.xml'));
    }

    #[DataProvider('homepageLogoAssets')]
    public function test_approved_homepage_logo_assets_are_served_with_expected_content_type(string $path, string $mime): void
    {
        $absolutePath = public_path($path);

        $this->assertFileExists($absolutePath);
        $this->assertNotFalse(file_get_contents($absolutePath));

        // Static files are normally served by the web server. Register a
        // test-only route so the feature test can exercise the real file
        // response and its content type through the HTTP kernel.
        Route::get('/'.$path, fn () => response()->file($absolutePath));

        $this->get('/'.$path)
            ->assertOk()
            ->assertHeader('Content-Type', $mime);
    }

    public function test_arabic_and_english_website_language_files_have_complete_matching_keys(): void
    {
        $ar = $this->flattenTranslations(require lang_path('ar/website.php'));
        $en = $this->flattenTranslations(require lang_path('en/website.php'));

        $this->assertIsArray($ar);
        $this->assertIsArray($en);
        $this->assertGreaterThan(200, count($ar));
        $this->assertEqualsCanonicalizing(array_keys($en), array_keys($ar));

        foreach (['ar' => $ar, 'en' => $en] as $locale => $translations) {
            foreach ($translations as $key => $value) {
                $this->assertIsString($value, "{$locale}.{$key} must be a string.");
                $this->assertNotSame('', trim($value), "{$locale}.{$key} must not be empty.");
            }
        }

        foreach (['navigation.home', 'publication.categories.annual_report', 'ui.home_hero_title', 'forms.received'] as $key) {
            $this->assertNotSame('', __('website.'.$key, [], 'ar'));
            $this->assertNotSame('', __('website.'.$key, [], 'en'));
        }
    }

    public static function localeProvider(): array
    {
        return [
            'arabic' => ['ar', 'rtl'],
            'english' => ['en', 'ltr'],
        ];
    }

    public static function homepageLogoAssets(): array
    {
        return [
            'png' => ['assets/pcdr-homepage/logo/pcdr-logo.png', 'image/png'],
            'large png' => ['assets/pcdr-homepage/logo/pcdr-logo-lg.png', 'image/png'],
            'animated svg' => ['assets/pcdr-homepage/logo/pcdr-logo-animated.svg', 'image/svg+xml'],
        ];
    }

    public static function inquiryFormProvider(): array
    {
        return [
            'contact' => [
                '/ar/contact/inquiry',
                'contact_messages',
                [
                    'category' => 'general',
                    'name' => 'Test Sender',
                    'message' => 'This is a sufficiently long test message.',
                    'consent' => '1',
                    'website' => '',
                ],
                'website',
            ],
            'volunteer' => [
                '/ar/volunteer/inquiry',
                'volunteer_inquiries',
                [
                    'name' => 'Test Volunteer',
                    'area_of_interest' => 'Community response',
                    'availability' => 'flexible',
                    'message' => 'I would like to help.',
                    'consent' => '1',
                    'website' => '',
                ],
                'website',
            ],
        ];
    }

    #[DataProvider('localeProvider')]
    public function test_all_public_destinations_render_with_the_shared_shell(string $locale, string $direction): void
    {
        $routes = [
            'about' => 'Public/About',
            'about/vision' => 'Public/About',
            'about/mission' => 'Public/About',
            'about/values' => 'Public/About',
            'about/direction' => 'Public/About',
            'about/groups' => 'Public/About',
            'about/approach' => 'Public/About',
            'strategic-areas' => 'Public/Pillars/Index',
            'programs' => 'Public/Programs/Index',
            'news' => 'Public/News/Index',
            'publications' => 'Public/Publications/Index',
            'transparency' => 'Public/Transparency',
            'partnerships' => 'Public/Partnerships',
            'get-involved' => 'Public/Involve',
            'contact' => 'Public/Contact',
            'complaints' => 'Public/Complaints',
        ];

        foreach ($routes as $path => $component) {
            $response = $this->get("/{$locale}/{$path}");
            $response->assertOk();
            $this->assertStringContainsString(
                '<html lang="'.$locale.'" dir="'.$direction.'" data-locale="'.$locale.'">',
                (string) $response->getContent(),
            );
            $this->assertSame($component, $this->inertiaPage($response)['component']);
        }
    }

    public function test_about_focus_routes_are_distinct_pages_with_focus_seo(): void
    {
        $foci = ['overview', 'vision', 'mission', 'values', 'direction', 'groups', 'approach'];
        $titles = [];

        foreach ($foci as $focus) {
            $path = $focus === 'overview' ? '/en/about' : "/en/about/{$focus}";
            $page = $this->inertiaPage($this->get($path));
            $props = $page['props'];

            $this->assertSame('Public/About', $page['component']);
            $this->assertSame($focus, $props['focus']);
            $this->assertNotEmpty($props['focusTitle']);
            $this->assertNotEmpty($props['focusDescription']);
            $this->assertSame(url($path), $props['meta']['canonical']);
            $titles[] = $props['meta']['title'];
        }

        $this->assertCount(count($foci), array_unique($titles));
    }

    public function test_public_navigation_exposes_all_destinations(): void
    {
        $this->seed(WebsiteSeeder::class);
        $props = $this->inertiaPage($this->get('/ar/about'))['props'];

        foreach (['home', 'about', 'pillars', 'programs', 'news', 'publications', 'transparency', 'partnerships', 'involve', 'contact'] as $key) {
            $this->assertArrayHasKey($key, $props['nav']);
        }

        $this->assertCount(10, $props['navigation']['primary']);

        $urls = array_column($props['navigation']['primary'], 'url');
        $this->assertContains(url('/ar/about'), $urls);
        $this->assertContains(url('/ar/strategic-areas'), $urls);
    }

    public function test_public_home_exposes_published_cms_content_only(): void
    {
        $published = Article::create([
            'status' => ContentStatus::Published,
            'published_at' => now()->subDay(),
        ]);
        $this->translate($published, 'published-home-article', 'Published home article', ['excerpt' => 'Public excerpt']);

        $draft = Article::create(['status' => ContentStatus::Draft]);
        $this->translate($draft, 'draft-home-article', 'Draft home article');

        $props = $this->inertiaPage($this->get('/en'))['props'];
        $titles = array_column($props['articles'], 'title');

        $this->assertNotEmpty($props['vision']);
        $this->assertContains('Published home article (en)', $titles);
        $this->assertNotContains('Draft home article (en)', $titles);
        $this->assertIsArray($props['pillars']);
        $this->assertIsArray($props['programs']);
        $this->assertIsArray($props['publications']);
        $this->assertIsArray($props['sections']);
    }

    /**
     * Non-home public pages consume the CMS-seeded primary menu through the public
     * navigation composable. Their shared footer uses a canonical 3-group structure
     * so it stays identical across inner public pages and locales; the homepage has
     * its own prototype footer.
     */
    public function test_public_shell_uses_seeded_primary_menu(): void
    {
        $composable = (string) file_get_contents(resource_path('js/composables/usePublicNavigation.js'));
        $this->assertStringContainsString('page.props.navigation', $composable);
        $this->assertStringContainsString('seededNavigation.value.primary', $composable);
        $this->assertStringContainsString('if (seededPrimary.value.length > 0)', $composable);
        $this->assertStringContainsString('const FOOTER_GROUPS = [', $composable);

        $header = (string) file_get_contents(resource_path('js/Components/Public/SiteHeader.vue'));
        $this->assertStringContainsString("from '../../composables/usePublicNavigation'", $header);
        $this->assertStringContainsString('const { links, cta, barEntries } = usePublicNavigation()', $header);
        $this->assertStringContainsString('v-for="(item, i) in links"', $header);
        $this->assertStringContainsString(':href="item.url"', $header);
        $this->assertStringContainsString(':href="cta.url"', $header);

        $footer = (string) file_get_contents(resource_path('js/Components/Public/SiteFooter.vue'));
        $this->assertStringContainsString("from '../../composables/usePublicNavigation'", $footer);
        $this->assertStringContainsString('const { footerGroups: navFooterGroups } = usePublicNavigation()', $footer);
        $this->assertStringContainsString('v-for="(group, gi) in footerGroups"', $footer);
        $this->assertStringContainsString('v-for="item in group.items"', $footer);
        $this->assertStringContainsString(':href="item.url"', $footer);

        $paths = [
            "path: ''",
            "path: 'about'",
            "path: 'strategic-areas'",
            "path: 'programs'",
            "path: 'news'",
            "path: 'publications'",
            "path: 'transparency'",
            "path: 'partnerships'",
            "path: 'contact'",
            "path: 'get-involved'",
            "path: 'complaints'",
        ];

        foreach ($paths as $path) {
            $this->assertStringContainsString($path, $composable);
        }

        $labels = [
            ["ar: 'الرئيسية'", "en: 'Home'"],
            ["ar: 'عن الجمعية'", "en: 'About'"],
            ["ar: 'المحاور الاستراتيجية'", "en: 'Strategic Areas'"],
            ["ar: 'البرامج والمشاريع'", "en: 'Programs & Projects'"],
            ["ar: 'الأخبار والأنشطة'", "en: 'News & Activities'"],
            ["ar: 'التقارير والإصدارات'", "en: 'Reports & Publications'"],
            ["ar: 'الشفافية والمساءلة'", "en: 'Transparency & Accountability'"],
            ["ar: 'الشراكات'", "en: 'Partnerships'"],
            ["ar: 'تواصل معنا'", "en: 'Contact Us'"],
            ["ar: 'شارك معنا'", "en: 'Get Involved'"],
            ["ar: 'صندوق الشكاوى'", "en: 'Complaints Box'"],
        ];

        foreach ($labels as [$arabic, $english]) {
            $this->assertStringContainsString($arabic, $composable);
            $this->assertStringContainsString($english, $composable);
        }
    }

    public function test_approved_homepage_baseline_files_remain_byte_identical(): void
    {
        $expected = [
            'css/pcdr-home.css' => 'd555d276d7abbce07004fc4f73c54e4a104a5e0f9bffa26ff47df71e299dc9c9',
            'views/app.blade.php' => '2a7affa19d09acda482e3d3dbce2ef97d374c259eaaa677848145c24ba871765',
            'js/Components/LanguageSwitcher.vue' => '29f9798faee179ba36da521bd69d0e28badc73e33dcc6f51420f9215f4471537',
        ];

        foreach ($expected as $path => $hash) {
            $this->assertSame($hash, hash_file('sha256', resource_path($path)), $path.' changed from the committed baseline.');
        }
    }

    public function test_homepage_preview_links_point_to_dedicated_destinations(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Public/Home.vue'));

        $this->assertStringContainsString('alternateUrl', $source);
        $this->assertStringContainsString('class="pcdr-prototype"', $source);
        $this->assertStringContainsString('<ProPrototypeFooter :sections="props.sections" />', $source);

        $anchors = [
            'js/Pages/Public/Home.vue' => ['href="#top"', 'id="top"'],
            'js/Components/RedesignHome/ProImpact.vue' => ['id="impact"', 'id="targets"'],
            'js/Components/RedesignHome/ProProgramAxes.vue' => ['id="programs"'],
            'js/Components/RedesignHome/ProNewsTabs.vue' => ['id="news"'],
            'js/Components/RedesignHome/ProMediaGallery.vue' => ['id="albums"'],
            'js/Components/RedesignHome/ProAboutTabs.vue' => ['id="about"'],
            'js/Components/RedesignHome/ProStories.vue' => ['id="stories"'],
            'js/Components/RedesignHome/ProPrototypeFooter.vue' => ['id="contact"'],
        ];

        foreach ($anchors as $path => $markers) {
            $componentSource = (string) file_get_contents(resource_path($path));

            foreach ($markers as $marker) {
                $this->assertStringContainsString($marker, $componentSource, $path.' is missing '.$marker.'.');
            }
        }

        foreach (['routeFor(', 'localPath(', '<SiteHeader', '<SiteFooter', 'href="#main"'] as $retiredMarker) {
            $this->assertStringNotContainsString($retiredMarker, $source, $retiredMarker.' still present in the homepage.');
        }

        $this->assertStringNotContainsString('Sample report headline', $source);
        $this->assertStringNotContainsString('عنوان تقرير توضيحي', $source);
        $this->assertSame(1, substr_count($source, 'href="#top"'), 'Only the in-page skip link may target #top in Home.vue.');
    }

    /** @param array<string, mixed> $fields */
    private function translate(Model $record, string $slug, string $title, array $fields = []): void
    {
        foreach (['ar', 'en'] as $locale) {
            $record->translations()->create(array_merge([
                'locale' => $locale,
                'slug' => $slug,
                'title' => $title.' ('.$locale.')',
            ], $fields));
        }
    }

    public function test_non_home_public_pages_share_single_footer_component_without_variants(): void
    {
        $layout = (string) file_get_contents(resource_path('js/Layouts/PublicLayout.vue'));
        $home = (string) file_get_contents(resource_path('js/Pages/Public/Home.vue'));
        $footer = (string) file_get_contents(resource_path('js/Components/Public/SiteFooter.vue'));

        $this->assertStringContainsString('<SiteFooter />', $layout);
        $this->assertStringNotContainsString('SiteFooter', $home);
        $this->assertStringContainsString('<ProPrototypeFooter :sections="props.sections" />', $home);
        $this->assertStringNotContainsString('variant', $footer);
        $this->assertStringNotContainsString('minimal', $footer);
        $this->assertStringContainsString('footerGroups', $footer);
        $this->assertStringContainsString('ps-footer__group', $footer);
    }

    public function test_homepage_uses_prototype_footer_without_invented_links(): void
    {
        $home = (string) file_get_contents(resource_path('js/Pages/Public/Home.vue'));
        $footer = (string) file_get_contents(resource_path('js/Components/RedesignHome/ProPrototypeFooter.vue'));
        $composable = (string) file_get_contents(resource_path('js/composables/useHomepageContent.js'));

        $this->assertStringContainsString('<ProPrototypeFooter :sections="props.sections" />', $home);
        $this->assertStringNotContainsString('SiteFooter', $home);
        $this->assertStringContainsString('<footer class="foot" id="contact">', $footer);
        $this->assertStringContainsString('href="#about"', $footer);
        $this->assertStringContainsString('href="#contact"', $footer);
        $this->assertStringNotContainsString('mailto:', $footer);
        $this->assertStringNotContainsString('http://', $footer);
        $this->assertStringNotContainsString('https://', $footer);
        $this->assertStringContainsString('content.safeUrl(', $footer);
        $this->assertStringContainsString('<span v-else :aria-label="item.label"', $footer, 'Links without a URL must stay inert.');
        $this->assertStringContainsString("url === '#'", $composable, 'Placeholder links must never become destinations.');
    }

    /**
     * The homepage now owns a prototype footer instead of the shared SiteFooter.
     * The legacy `.pcdr-home nav` rules that hid shared footer groups must not be
     * reintroduced, and prototype styles must remain scoped under `.pcdr-prototype`.
     * Inner pages keep the shared footer's grouped <nav> elements.
     */
    public function test_homepage_styles_do_not_hide_shared_footer_navigation(): void
    {
        $legacyCss = (string) file_get_contents(resource_path('css/pcdr-home.css'));
        $prototypeCss = (string) file_get_contents(resource_path('css/pcdr-prototype.css'));

        $this->assertStringNotContainsString('.pcdr-home nav{', $legacyCss);
        $this->assertStringNotContainsString('.pcdr-home nav.open', $legacyCss);
        $this->assertStringContainsString('.pcdr-home .site-header nav{position:fixed', $legacyCss);
        $this->assertStringContainsString('.pcdr-home .site-header nav.open', $legacyCss);
        $this->assertStringContainsString('.pcdr-prototype .menu', $prototypeCss);
        $this->assertStringContainsString('.pcdr-prototype .foot', $prototypeCss);
        $this->assertStringNotContainsString('.pcdr-prototype nav{', $prototypeCss);
        $this->assertStringNotContainsString('.pcdr-prototype nav.open', $prototypeCss);

        $footer = (string) file_get_contents(resource_path('js/Components/Public/SiteFooter.vue'));
        $this->assertStringContainsString('<nav', $footer);
        $this->assertStringContainsString('ps-footer__group', $footer);

        $home = (string) file_get_contents(resource_path('js/Pages/Public/Home.vue'));
        $this->assertStringContainsString('class="pcdr-prototype"', $home);
        $this->assertStringNotContainsString('pcdr-home public-shell', $home);
        $this->assertStringNotContainsString('<SiteFooter', $home);
        $this->assertStringContainsString('<ProPrototypeFooter :sections="props.sections" />', $home);
    }

    /** @return array<string, mixed> */
    private function inertiaPage(TestResponse $response): array
    {
        $html = $response->getContent();

        $this->assertSame(
            1,
            preg_match('/<script data-page="app" type="application\/json">(.*?)<\/script>/s', $html, $matches),
            'Inertia page payload was not found in the HTML response.',
        );

        $page = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($page);

        return $page;
    }

    /** @param array<string, mixed> $translations @return array<string, string> */
    private function flattenTranslations(array $translations, string $prefix = ''): array
    {
        $flat = [];

        foreach ($translations as $key => $value) {
            $key = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $flat += $this->flattenTranslations($value, $key);
            } else {
                $flat[$key] = (string) $value;
            }
        }

        return $flat;
    }

    private function uniqueEmail(string $prefix): string
    {
        return $prefix.'-'.Str::lower(Str::random(12)).'@example.test';
    }
}
