<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HomepageSection;
use App\Models\HomepageSectionItem;
use App\Models\NavigationItem;
use App\Models\StrategicPillar;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\HomepageService;
use App\Services\SiteSettings;
use Database\Seeders\HomepageContentSeeder;
use Database\Seeders\SiteSettingsSeeder;
use Database\Seeders\WebsiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_public_home_receives_localized_sections_mission_and_strategic_targets(): void
    {
        $this->seedHomepage();

        $this->get('/ar')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Home')
            ->where('mission', config('website.mission.ar'))
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
            ->where('mission', config('website.mission.en'))
            ->where('sections.5.title', 'Where we aim to be by the end of 2027')
            ->where('sections.5.items.0.title', 'Families targeted for humanitarian interventions')
            ->where('meta.locale', 'en')
            ->where('meta.og.image', null));
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
        $this->assertCount(19, $settings);

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
            'social.youtube', 'seo.og_image',
        ] as $key) {
            $this->assertNull($settings->get($key)?->value, "{$key} must not be invented.");
        }

        foreach (['footer.copyright', 'footer.legal_note'] as $key) {
            $this->assertNull($settings->get($key)?->t('value', 'ar'));
            $this->assertNull($settings->get($key)?->t('value', 'en'));
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
            '<ProPillHeader />',
            'props.sections',
        ] as $marker) {
            $this->assertStringContainsString($marker, $source, $marker.' marker missing.');
        }

        foreach ([
            'class="pcdr-home public-shell"',
            '<SiteHeader',
            '<SiteFooter',
            'routeFor(',
            'localPath(',
            'href="#main"',
            'const content = {',
            'navOpen',
        ] as $retiredMarker) {
            $this->assertStringNotContainsString($retiredMarker, $source, $retiredMarker.' still present.');
        }

        $this->assertSame(1, substr_count($source, 'href="#top"'));

        $templateOrder = [
            '<main id="top">',
            '<ProHeroCarousel :sections="props.sections" />',
            '<ProTicker :sections="props.sections" />',
            '<ProImpact :sections="props.sections" />',
            '<ProProgramAxes :sections="props.sections" />',
            '<ProNewsTabs :sections="props.sections" />',
            '<ProMediaGallery :sections="props.sections" />',
            '<ProAboutTabs :sections="props.sections" />',
            '<ProStories :sections="props.sections" />',
            '<ProPrototypeFooter :sections="props.sections" />',
        ];

        $previousPosition = -1;
        foreach ($templateOrder as $marker) {
            $position = strpos($source, $marker);
            $this->assertNotFalse($position, "Missing prototype marker: {$marker}");
            $this->assertGreaterThan($previousPosition, $position, "Out-of-order prototype marker: {$marker}");
            $previousPosition = $position;
        }

        $anchors = [
            'ProImpact.vue' => ['id="impact"', 'id="targets"'],
            'ProProgramAxes.vue' => ['id="programs"'],
            'ProNewsTabs.vue' => ['id="news"'],
            'ProMediaGallery.vue' => ['id="albums"'],
            'ProAboutTabs.vue' => ['id="about"'],
            'ProStories.vue' => ['id="stories"'],
            'ProPrototypeFooter.vue' => ['id="contact"'],
        ];

        foreach ($anchors as $component => $markers) {
            $componentSource = (string) file_get_contents(resource_path('js/Components/RedesignHome/'.$component));

            foreach ($markers as $marker) {
                $this->assertStringContainsString($marker, $componentSource, $component.' is missing '.$marker.'.');
            }
        }
    }

    public function test_admin_can_review_and_update_inline_homepage_items(): void
    {
        $this->seedHomepage();

        $editor = User::factory()->create([
            'email' => self::BOOTSTRAP_EMAIL,
            'password' => Hash::make('password12345'),
        ]);

        $this->actingAs($editor)->get(route('cms.homepage.index', ['locale' => 'ar']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cms/Homepage/Index')
                ->where('sections.5.key', 'targets')
                ->where('sections.5.items.0.itemKey', 'families'));

        $section = $this->section('targets');
        $payload = [
            'sections' => [[
                'id' => $section->id,
                'is_enabled' => true,
                'display_order' => $section->display_order,
                'translations' => [
                    'ar' => [
                        'eyebrow' => $section->t('eyebrow', 'ar'),
                        'title' => $section->t('title', 'ar'),
                        'body' => $section->t('body', 'ar'),
                        'cta_label' => $section->t('cta_label', 'ar'),
                        'cta_link' => $section->t('cta_link', 'ar'),
                    ],
                    'en' => [
                        'eyebrow' => $section->t('eyebrow', 'en'),
                        'title' => $section->t('title', 'en'),
                        'body' => $section->t('body', 'en'),
                        'cta_label' => $section->t('cta_label', 'en'),
                        'cta_link' => $section->t('cta_link', 'en'),
                    ],
                ],
                'items' => $section->items->map(function (HomepageSectionItem $item): array {
                    return [
                        'id' => $item->id,
                        'item_type' => $item->item_type,
                        'item_id' => $item->item_id,
                        'item_key' => $item->item_key,
                        'is_published' => $item->is_published,
                        'numeric_value' => $item->numeric_value,
                        'translations' => [
                            'ar' => [
                                'label' => $item->t('label', 'ar'),
                                'title' => $item->t('title', 'ar') === 'الأسر المستهدفة بالتدخلات الإنسانية'
                                    ? 'صياغة مخصصة من المسؤول'
                                    : $item->t('title', 'ar'),
                                'body' => $item->t('body', 'ar'),
                                'display_value' => $item->t('display_value', 'ar'),
                                'url' => $item->t('url', 'ar'),
                            ],
                            'en' => [
                                'label' => $item->t('label', 'en'),
                                'title' => $item->t('title', 'en'),
                                'body' => $item->t('body', 'en'),
                                'display_value' => $item->t('display_value', 'en'),
                                'url' => $item->t('url', 'en'),
                            ],
                        ],
                    ];
                })->all(),
            ]],
        ];

        $this->actingAs($editor)->put(route('cms.homepage.update', ['locale' => 'ar']), $payload)
            ->assertRedirect();

        $this->assertSame('صياغة مخصصة من المسؤول', $this->item('targets', 'families')->t('title', 'ar'));
        $this->assertSame(5, $this->section('targets')->items()->count());
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
