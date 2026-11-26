<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HomepageSection;
use App\Services\HomepageService;
use Database\Seeders\HomepagePrototypeContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PrototypeHomepageTest extends TestCase
{
    use RefreshDatabase;

    private const SECTION_KEYS = [
        'hero',
        'ticker',
        'impact',
        'programs',
        'news',
        'albums',
        'about',
        'stories',
        'footer',
    ];

    public function test_seeder_creates_the_approved_sections_in_prototype_order(): void
    {
        $this->seed(HomepagePrototypeContentSeeder::class);

        $this->assertSame(
            self::SECTION_KEYS,
            HomepageSection::query()->whereIn('key', self::SECTION_KEYS)->orderBy('display_order')->pluck('key')->all(),
        );

        $this->assertDatabaseHas('homepage_sections', [
            'key' => 'hero',
            'is_enabled' => true,
            'status' => 'published',
        ]);
    }

    public function test_public_homepage_receives_only_enabled_prototype_sections(): void
    {
        $this->seed(HomepagePrototypeContentSeeder::class);

        $this->get('/ar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Home')
                ->where('locale', 'ar')
                ->where('direction', 'rtl')
                ->has('sections', count(self::SECTION_KEYS))
                ->where('sections.0.key', 'hero')
                ->where('sections.1.key', 'ticker')
                ->where('sections.2.key', 'impact')
                ->where('sections.3.key', 'programs')
                ->where('sections.4.key', 'news')
                ->where('sections.5.key', 'albums')
                ->where('sections.6.key', 'about')
                ->where('sections.7.key', 'stories')
                ->where('sections.8.key', 'footer')
            );
    }

    public function test_public_api_exposes_prototype_metadata_without_invented_links(): void
    {
        $this->seed(HomepagePrototypeContentSeeder::class);

        $response = $this->getJson('/api/public/homepage?locale=ar');

        $response->assertOk()
            ->assertJsonPath('data.locale', 'ar')
            ->assertJsonPath('data.dir', 'rtl');

        $sections = collect($response->json('data.sections'))->keyBy('key');

        $this->assertSame('أهم الأخبار', $sections->get('ticker')['tickerLabel']);
        $this->assertSame('m-sun', $sections->get('hero')['items'][0]['motifKey']);
        $this->assertSame('#news', $sections->get('ticker')['items'][0]['url']);

        $impact = $sections->get('impact');
        $this->assertSame(
            'مستهدفات تخطيطية أولية لعام 2027، تخضع للمراجعة بعد تحديد التمويل المتاح والقدرات التنفيذية.',
            $impact['body'],
        );
        $this->assertSame('11', collect($impact['items'])->firstWhere('key', 'stat-axes')['display']);
        $this->assertSame('محوراً', collect($impact['items'])->firstWhere('key', 'stat-axes')['unit']);
        $this->assertCount(8, collect($impact['items'])->filter(fn (array $item): bool => str_starts_with((string) $item['key'], 'target-')));

        $news = $sections->get('news');
        $this->assertSame('أخبار وأنشطة', $news['tabLabels']['news']);
        $this->assertTrue(collect($news['items'])->contains(fn (array $item): bool => $item['tab'] === 'publications' && $item['featured'] === true));
        $this->assertTrue(collect($news['items'])->contains(fn (array $item): bool => filled($item['dateLabel'] ?? null)));

        $about = $sections->get('about');
        $this->assertSame('رسالتنا', $about['tabLabels']['mission']);
        $this->assertSame('أهدافنا', $about['tabLabels']['objectives']);
        $this->assertCount(11, collect($about['items'])->filter(fn (array $item): bool => str_starts_with((string) $item['key'], 'objective-')));
        $this->assertTrue(collect($about['items'])->contains(fn (array $item): bool => $item['tab'] === 'objectives'));
        $this->assertCount(10, collect($about['items'])->filter(fn (array $item): bool => str_starts_with((string) $item['key'], 'value-')));

        foreach ($sections->get('news')['items'] as $item) {
            $this->assertNotSame('#', $item['url'] ?? null);
        }
    }

    public function test_disabled_section_is_hidden_from_public_html_and_api(): void
    {
        $this->seed(HomepagePrototypeContentSeeder::class);

        HomepageSection::query()->where('key', 'stories')->update(['is_enabled' => false]);
        app(HomepageService::class)->flush();

        $this->get('/ar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('sections', count(self::SECTION_KEYS) - 1)
                ->missing('sections.8')
            );

        $keys = collect($this->getJson('/api/public/homepage?locale=ar')->json('data.sections'))->pluck('key')->all();
        $this->assertNotContains('stories', $keys);
    }

    public function test_source_assets_and_public_components_exist_for_the_prototype(): void
    {
        $source = (string) file_get_contents(resource_path('../docs/redesign/source-homepage/index.html'));

        foreach ([
            'بصمتنا في المجتمع',
            'محاور عملنا',
            'آخر الأخبار',
            'ألبوم الصور',
            'رسالتنا ورؤيتنا',
            'قصص نجاح',
            'الفيلم التعريفي بالجمعية',
            'مستهدفات تخطيطية أولية لعام 2027، تخضع للمراجعة بعد تحديد التمويل المتاح والقدرات التنفيذية.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }

        $home = (string) file_get_contents(resource_path('js/Pages/Public/Home.vue'));

        foreach ([
            'ProTopStrip',
            'ProPillHeader',
            'ProHeroCarousel',
            'ProTicker',
            'ProImpact',
            'ProProgramAxes',
            'ProNewsTabs',
            'ProMediaGallery',
            'ProAboutTabs',
            'ProStories',
            'ProPrototypeFooter',
        ] as $component) {
            $this->assertStringContainsString($component, $home);
        }

        $this->assertFileExists(public_path('assets/pcdr-homepage/logo/pcdr-prototype-logo.jpg'));
        $this->assertFileExists(resource_path('css/pcdr-prototype.css'));
    }
}
