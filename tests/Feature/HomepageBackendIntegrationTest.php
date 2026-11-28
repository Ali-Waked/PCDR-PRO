<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HomepageSection;
use App\Models\HomepageSectionItem;
use App\Models\NavigationItem;
use App\Models\StrategicPillar;
use App\Models\WebsiteSetting;
use App\Services\HomepageService;
use App\Services\SiteSettings;
use App\Support\SiteSettingsCatalog;
use Database\Seeders\HomepageContentSeeder;
use Database\Seeders\SiteSettingsSeeder;
use Database\Seeders\WebsiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class HomepageBackendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const BOOTSTRAP_EMAIL = 'homepage-integration@example.test';

    /** @var list<string> */
    private const SECTION_KEYS = [
        'hero', 'intro', 'areas', 'framework', 'programs',
        'targets', 'community', 'partners', 'knowledge', 'cta',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', '12345678901234567890123456789012');
        config()->set('cms.bootstrap.email', self::BOOTSTRAP_EMAIL);
        config()->set('cms.local_auth_enabled', true);
        $this->withoutVite();
    }

    public function test_seeded_sections_match_the_fixed_homepage_render_order_and_inline_sources(): void
    {
        $this->seedHomepage();

        $this->assertSame(
            self::SECTION_KEYS,
            HomepageSection::query()->orderBy('display_order')->pluck('key')->all(),
        );
        $this->assertSame(32, HomepageSectionItem::query()->count());

        $targets = $this->section('targets');
        $labels = $targets->items->map(fn (HomepageSectionItem $item): ?string => $item->t('title', 'ar'))->all();

        $this->assertContains('الأسر المستهدفة بالتدخلات الإنسانية', $labels);
        $this->assertContains('الأسر المستهدفة في الأمن الغذائي', $labels);

        $families = $this->item('targets', 'families');
        $this->assertSame(3000.0, (float) $families->numeric_value);
        $this->assertSame('3,000', $families->t('display_value', 'ar'));
        $this->assertSame('3,000', $families->t('display_value', 'en'));

        $this->assertSame(
            8,
            $this->section('programs')->items()->count(),
            'The authoritative homepage program list has eight inline areas.',
        );
    }

    public function test_seeders_are_idempotent_and_preserve_administrator_customizations(): void
    {
        $this->seedHomepage();

        $this->section('intro')->translations()->updateOrCreate(
            ['locale' => 'ar'],
            ['title' => 'عنوان مخصص من المسؤول'],
        );
        $this->item('targets', 'families')->translations()->updateOrCreate(
            ['locale' => 'ar'],
            ['display_value' => '٩٩٩'],
        );
        WebsiteSetting::query()->where('key', 'organization.name')->firstOrFail()->translations()
            ->updateOrCreate(['locale' => 'ar'], ['value' => 'اسم مخصص من المسؤول']);
        NavigationItem::query()->where('link_value', 'home')->firstOrFail()->translations()
            ->updateOrCreate(['locale' => 'ar'], ['label' => 'الرئيسية المخصصة']);
        StrategicPillar::query()->where('code', '01')->firstOrFail()->translations()
            ->updateOrCreate(['locale' => 'ar'], ['title' => 'محور مخصص من المسؤول']);

        $counts = [
            'sections' => HomepageSection::query()->count(),
            'items' => HomepageSectionItem::query()->count(),
            'settings' => WebsiteSetting::query()->count(),
            'navigation' => NavigationItem::query()->count(),
            'pillars' => StrategicPillar::query()->count(),
        ];

        $this->seedHomepage();

        $this->assertSame($counts['sections'], HomepageSection::query()->count());
        $this->assertSame($counts['items'], HomepageSectionItem::query()->count());
        $this->assertSame($counts['settings'], WebsiteSetting::query()->count());
        $this->assertSame($counts['navigation'], NavigationItem::query()->count());
        $this->assertSame($counts['pillars'], StrategicPillar::query()->count());

        $this->assertSame('عنوان مخصص من المسؤول', $this->section('intro')->t('title', 'ar'));
        $this->assertSame('٩٩٩', $this->item('targets', 'families')->t('display_value', 'ar'));
        $this->assertSame(
            'اسم مخصص من المسؤول',
            WebsiteSetting::query()->where('key', 'organization.name')->firstOrFail()->t('value', 'ar'),
        );
        $this->assertSame(
            'الرئيسية المخصصة',
            NavigationItem::query()->where('link_value', 'home')->firstOrFail()->t('label', 'ar'),
        );
        $this->assertSame(
            'محور مخصص من المسؤول',
            StrategicPillar::query()->where('code', '01')->firstOrFail()->t('title', 'ar'),
        );
    }

    public function test_known_legacy_homepage_copy_is_replaced_without_overwriting_custom_fields(): void
    {
        HomepageSection::query()->create(['key' => 'intro', 'type' => 'intro', 'display_order' => 1]);
        $section = $this->section('intro');
        $section->translations()->create([
            'locale' => 'ar',
            'eyebrow' => 'عن الجمعية',
            'title' => 'تعريف مؤسسي',
            'body' => (string) config('website.vision.ar'),
            'cta_link' => '/ar/about',
        ]);

        $this->seed(HomepageContentSeeder::class);

        $this->assertSame('من نحن', $section->fresh()->t('eyebrow', 'ar'));
        $this->assertSame('الإنسان أولاً... والمجتمع شريك في التغيير', $section->fresh()->t('title', 'ar'));
        $this->assertStringContainsString('نعمل بروح الشراكة', (string) $section->fresh()->t('body', 'ar'));
    }

    public function test_public_home_receives_localized_sections_and_meta_only(): void
    {
        $this->seedHomepage();

        $this->get('/ar')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Home')
            ->missing('mission')
            ->missing('vision')
            ->missing('pillars')
            ->missing('programs')
            ->missing('publications')
            ->where('sections.0.key', 'hero')
            ->where('sections.1.key', 'intro')
            ->where('sections.5.key', 'targets')
            ->where('sections.5.title', 'ما نستهدف الوصول إليه بنهاية 2027')
            ->where('sections.5.items.0.key', 'families')
            ->where('sections.5.items.0.display', '3,000')
            ->where('sections.5.items.0.title', 'الأسر المستهدفة بالتدخلات الإنسانية')
            ->where('sections.9.key', 'cta')
            ->where('meta.locale', 'ar'));

        $this->get('/en')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Home')
            ->missing('mission')
            ->where('sections.5.title', 'Where we aim to be by the end of 2027')
            ->where('sections.5.items.0.title', 'Families targeted for humanitarian interventions')
            ->where('meta.locale', 'en')
            ->where('meta.og.image', null));
    }

    public function test_dead_stored_homepage_links_are_neutralized_in_the_public_payload(): void
    {
        $this->seedHomepage();

        $this->section('intro')->translations()->where('locale', 'ar')
            ->update(['cta_link' => '/ar/strategic-areas']);
        app(HomepageService::class)->flush();

        $this->get('/ar')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Home')
            ->where('sections.1.ctaLink', null));

        // About is now a real public page, so stored links to it survive.
        $this->section('intro')->translations()->where('locale', 'ar')
            ->update(['cta_link' => '/ar/about']);
        app(HomepageService::class)->flush();

        $this->get('/ar')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Home')
            ->where('sections.1.ctaLink', '/ar/about'));
    }

    public function test_public_home_hides_disabled_sections_but_cms_preview_keeps_them(): void
    {
        $this->seedHomepage();

        $hero = $this->section('hero');
        $hero->update(['is_enabled' => false, 'status' => 'draft']);
        app(HomepageService::class)->flush();

        $publicKeys = array_column(app(HomepageService::class)->sections('ar'), 'key');
        $previewKeys = array_column(app(HomepageService::class)->sections('ar', false, true), 'key');

        $this->assertNotContains('hero', $publicKeys);
        $this->assertContains('intro', $publicKeys);
        $this->assertContains('hero', $previewKeys);

        $this->get('/ar')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Home')
            ->where('sections.0.key', 'intro')
            ->where('sections.0.title', 'الإنسان أولاً... والمجتمع شريك في التغيير'));
    }

    public function test_homepage_service_orders_sections_and_filters_visibility_and_status(): void
    {
        HomepageSection::query()->create([
            'key' => 'hidden', 'type' => 'inline', 'status' => 'published',
            'published_at' => now(), 'is_enabled' => false, 'display_order' => 0,
        ]);
        HomepageSection::query()->create([
            'key' => 'draft', 'type' => 'inline', 'status' => 'draft',
            'is_enabled' => true, 'display_order' => 1,
        ]);
        HomepageSection::query()->create([
            'key' => 'visible-second', 'type' => 'inline', 'status' => 'published',
            'published_at' => now(), 'is_enabled' => true, 'display_order' => 3,
        ]);
        HomepageSection::query()->create([
            'key' => 'visible-first', 'type' => 'inline', 'status' => 'published',
            'published_at' => now(), 'is_enabled' => true, 'display_order' => 2,
        ]);

        $service = app(HomepageService::class);
        $this->assertSame(
            ['visible-first', 'visible-second'],
            array_column($service->sections('ar'), 'key'),
        );
        $this->assertSame(
            ['hidden', 'draft', 'visible-first', 'visible-second'],
            array_column($service->sections('ar', false, true), 'key'),
        );
    }

    public function test_partners_links_survive_public_homepage_link_filtering(): void
    {
        $section = HomepageSection::query()->create([
            'key' => 'hero', 'type' => 'hero', 'status' => 'published',
            'published_at' => now(), 'is_enabled' => true, 'display_order' => 0,
        ]);
        $section->translations()->create([
            'locale' => 'en', 'title' => 'Partners', 'cta_link' => '/en/partners',
        ]);
        $item = $section->items()->create([
            'item_type' => 'inline', 'item_key' => 'partner-link',
            'is_published' => true, 'sort_order' => 1,
        ]);
        $item->translations()->create(['locale' => 'en', 'label' => 'Partners', 'url' => '/partners']);

        $sections = app(HomepageService::class)->sections('en');
        $this->assertSame('/en/partners', $sections[0]['ctaLink']);
        $this->assertSame('/partners', $sections[0]['items'][0]['url']);

        $this->get('/en')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sections.0.ctaLink', '/en/partners')
            ->where('sections.0.items.0.url', '/partners'));
    }

    public function test_unpublished_inline_items_are_excluded_from_public_home_content(): void
    {
        $this->seedHomepage();

        $this->item('targets', 'families')->update(['is_published' => false]);

        $sections = app(HomepageService::class)->sections('ar', false);
        $targets = collect($sections)->firstWhere('key', 'targets');
        $itemKeys = array_column($targets['items'], 'key');

        $this->assertNotContains('families', $itemKeys);
        $this->assertContains('food-security', $itemKeys);

        $this->get('/ar')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sections.5.items.0.key', 'food-security')
            ->missing('sections.5.items.0.families'));
    }

    public function test_strategic_figures_remain_explicit_planning_targets(): void
    {
        $this->seedHomepage();

        $targets = $this->section('targets');
        $arabicBody = (string) $targets->t('body', 'ar');
        $englishBody = (string) $targets->t('body', 'en');

        $this->assertStringContainsString('مستهدفات الخطة الاستراتيجية 2027', (string) $targets->t('eyebrow', 'ar'));
        $this->assertStringContainsString('Strategic Plan Targets 2027', (string) $targets->t('eyebrow', 'en'));
        $this->assertStringContainsString('نستهدف', $arabicBody);
        $this->assertStringNotContainsString('نتائج محققة', $arabicBody);
        $this->assertStringContainsString('targets', strtolower($englishBody));
        $this->assertStringNotContainsString('achieved results', strtolower($englishBody));

        $seeder = (string) file_get_contents(database_path('seeders/HomepageContentSeeder.php'));
        $this->assertStringContainsString('STRATEGIC_OPERATIONAL_PLAN_2027_2029', $seeder);
    }

    public function test_public_settings_seeder_initializes_only_cms_keys_without_secrets_or_unverified_details(): void
    {
        $this->seed(SiteSettingsSeeder::class);

        $settings = WebsiteSetting::query()->get()->keyBy('key');
        $this->assertCount(count(SiteSettingsCatalog::keys()), $settings);

        $this->assertSame(
            'جمعية العمل التشاركي في الأزمات والكوارث الخيرية – قطاع غزة',
            $settings->get('organization.name')?->t('value', 'ar'),
        );
        $this->assertSame(config('website.name.en'), $settings->get('organization.name')?->t('value', 'en'));
        $this->assertSame(
            '/assets/pcdr-homepage/logo/pcdr-logo.png',
            $settings->get('organization.logo')?->value,
        );
        $this->assertSame('complaints@pal-arc.org', $settings->get('contact.complaints_email')?->value);
        $this->assertSame('complaints@pal-arc.org', app(SiteSettings::class)->complaintsEmail('ar'));
        $this->assertSame('complaints@pal-arc.org', app(SiteSettings::class)->complaintsEmail('en'));

        foreach ([
            'contact.email', 'contact.phone', 'contact.address', 'contact.hours',
            'social.facebook', 'social.x', 'social.instagram', 'social.linkedin',
            'social.youtube', 'social.tiktok', 'social.snapchat', 'social.whatsapp', 'seo.og_image',
        ] as $key) {
            $this->assertNull($settings->get($key)?->value, "{$key} must not be invented.");
        }

        foreach (['footer.copyright', 'footer.legal_note'] as $key) {
            $this->assertNull($settings->get($key)?->t('value', 'ar'));
            $this->assertNull($settings->get($key)?->t('value', 'en'));
        }

        foreach ([
            'audit_automation.enabled', 'audit_automation.interval_months',
            'audit_automation.execution_day', 'audit_automation.execution_time',
            'audit_automation.timezone', 'audit_automation.notifications_enabled',
            'audit_automation.lookback_months',
        ] as $key) {
            $setting = $settings->get($key);
            $this->assertNotNull($setting, "{$key} must be initialized.");
            $this->assertFalse((bool) $setting->is_public, "{$key} must remain private.");
        }

        $source = (string) file_get_contents(database_path('seeders/SiteSettingsSeeder.php'));
        $this->assertDoesNotMatchRegularExpression('/(?:password|api[_-]?key|secret|token)\s*=>/i', $source);
        $this->assertStringNotContainsString('@example', $source);
        $this->assertStringNotContainsString('mailto:', $source);
    }

    public function test_homepage_source_uses_prototype_structure_and_anchor_map(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Public/Home.vue'));

        foreach ([
            'class="pcdr-prototype"',
            "import '../../../css/pcdr-prototype.css'",
            '<ProPrototypeSprite />',
            'class="skip" href="#top"',
            '<ProTopStrip />',
            '<ProPillHeader',
            'props.sections',
        ] as $marker) {
            $this->assertStringContainsString($marker, $source, $marker.' marker missing.');
        }

        foreach ([
            'class="pcdr-home public-shell"',
            '<SiteHeader',
            '<SiteFooter',
            'routeFor(',
            'href="#main"',
            'const content = {',
            'navOpen',
        ] as $retiredMarker) {
            $this->assertStringNotContainsString($retiredMarker, $source, $retiredMarker.' still present.');
        }

        $this->assertStringNotContainsString('href="/ar/', $source, 'Internal links must not be hardcoded for Arabic.');
        $this->assertStringNotContainsString('href="/en/', $source, 'Internal links must not be hardcoded for English.');

        $this->assertSame(1, substr_count($source, 'href="#top"'));

        $sectionOrder = [
            'hero: ProHeroCarousel',
            'ticker: ProTicker',
            'impact: ProImpact',
            'programs: ProProgramAxes',
            'news: ProNewsTabs',
            'albums: ProMediaGallery',
            'about: ProAboutTabs',
            'stories: ProStories',
            'footer: ProPrototypeFooter',
        ];

        $previousPosition = -1;
        foreach ($sectionOrder as $marker) {
            $position = strpos($source, $marker);
            $this->assertNotFalse($position, "Missing section mapping: {$marker}");
            $this->assertGreaterThan($previousPosition, $position, "Out-of-order section mapping: {$marker}");
            $previousPosition = $position;
        }

        $mainPosition = strpos($source, '<main id="top">');
        $this->assertNotFalse($mainPosition, 'Missing prototype main landmark.');

        $componentPosition = strpos($source, ':sections="props.sections"', $mainPosition);
        $this->assertNotFalse($componentPosition, 'Main sections must be rendered dynamically.');
        $this->assertGreaterThan($mainPosition, $componentPosition, 'Main sections must render inside the main landmark.');

        $mainClosePosition = strpos($source, '</main>', $componentPosition);
        $this->assertNotFalse($mainClosePosition, 'Missing prototype main landmark close.');

        $footerPosition = strpos($source, ':anchor-base="localPath()"', $mainClosePosition);
        $this->assertNotFalse($footerPosition, 'Footer section must be rendered dynamically.');
        $this->assertGreaterThan($mainClosePosition, $footerPosition, 'Footer section must render outside the main landmark.');

        $anchors = [
            'ProImpact.vue' => ['id="impact"', 'id="targets"'],
            'ProProgramAxes.vue' => ['id="programs"'],
            'ProNewsTabs.vue' => ['id="news"'],
            'ProMediaGallery.vue' => ['id="albums"'],
            'ProAboutTabs.vue' => ['id="about"'],
            'ProStories.vue' => ['id="stories"'],
        ];

        foreach ($anchors as $component => $markers) {
            $componentSource = (string) file_get_contents(resource_path('js/Components/RedesignHome/'.$component));

            foreach ($markers as $marker) {
                $this->assertStringContainsString($marker, $componentSource, $component.' is missing '.$marker.'.');
            }
        }

        $footerSource = (string) file_get_contents(resource_path('js/Components/RedesignHome/ProPrototypeFooter.vue'));
        $this->assertStringContainsString('page.props.footer', $footerSource, 'Footer must use the shared filtered CMS sections.');
        $this->assertStringContainsString('page.props.navigation?.primary', $footerSource, 'Footer must use the shared navigation source.');
        $this->assertStringContainsString("navigationItem('about')", $footerSource, 'Footer About links must come from navigation.');
        $this->assertStringNotContainsString("localPath('about')", $footerSource, 'Footer must not hardcode the About URL.');
        $this->assertStringNotContainsString("localPath('complaints')", $footerSource, 'Footer must not hardcode the complaints URL.');
    }

    private function seedHomepage(): void
    {
        $this->seed(SiteSettingsSeeder::class);
        $this->seed(WebsiteSeeder::class);
        $this->seed(HomepageContentSeeder::class);
    }

    private function section(string $key): HomepageSection
    {
        return HomepageSection::query()->where('key', $key)->firstOrFail();
    }

    private function item(string $sectionKey, string $itemKey): HomepageSectionItem
    {
        return $this->section($sectionKey)->items()->where('item_key', $itemKey)->firstOrFail();
    }
}
