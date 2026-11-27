<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HomepageSectionItem;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\WebsitePage;
use App\Services\HomepageService;
use App\Services\NavigationService;
use App\Services\SiteSettings;
use Database\Seeders\CmsStructureSeeder;
use Database\Seeders\HomepagePrototypeContentSeeder;
use Database\Seeders\SiteSettingsSeeder;
use Database\Seeders\WebsiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class PublicChildPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<array{path: string, component: string, page: string, group: string, sibling: string, labels: array{ar: string, en: string}, contentKeys: list<string>}>
     */
    private const PAGES = [
        [
            'path' => '/about/vision',
            'component' => 'Public/About/Vision',
            'page' => 'about_vision',
            'group' => 'about',
            'sibling' => '/about/objectives',
            'labels' => ['ar' => 'رؤيتنا', 'en' => 'Our vision'],
            'contentKeys' => ['vision', 'mission', 'body', 'image'],
        ],
        [
            'path' => '/about/objectives',
            'component' => 'Public/About/Objectives',
            'page' => 'about_objectives',
            'group' => 'about',
            'sibling' => '/about/vision',
            'labels' => ['ar' => 'أهدافنا الاستراتيجية', 'en' => 'Our strategic objectives'],
            'contentKeys' => ['objectives', 'values', 'pillars'],
        ],
        [
            'path' => '/about/achievements',
            'component' => 'Public/About/Achievements',
            'page' => 'about_achievements',
            'group' => 'about',
            'sibling' => '/about/board',
            'labels' => ['ar' => 'إنجازاتنا بالأرقام', 'en' => 'Our achievements in numbers'],
            'contentKeys' => ['achievements', 'pillars'],
        ],
        [
            'path' => '/about/board',
            'component' => 'Public/About/Board',
            'page' => 'about_board',
            'group' => 'about',
            'sibling' => '/about/achievements',
            'labels' => ['ar' => 'مجلس الإدارة', 'en' => 'Board of directors'],
            'contentKeys' => ['board'],
        ],
        [
            'path' => '/media/news',
            'component' => 'Public/Media/News',
            'page' => 'media_news',
            'group' => 'media',
            'sibling' => '/media/albums',
            'labels' => ['ar' => 'الأخبار والأنشطة', 'en' => 'News and activities'],
            'contentKeys' => ['news', 'activities'],
        ],
        [
            'path' => '/media/albums',
            'component' => 'Public/Media/Albums',
            'page' => 'media_albums',
            'group' => 'media',
            'sibling' => '/media/news',
            'labels' => ['ar' => 'ألبوم الصور', 'en' => 'Photo album'],
            'contentKeys' => ['albums'],
        ],
        [
            'path' => '/media/stories',
            'component' => 'Public/Media/Stories',
            'page' => 'media_stories',
            'group' => 'media',
            'sibling' => '/media/news',
            'labels' => ['ar' => 'قصص نجاح', 'en' => 'Success stories'],
            'contentKeys' => ['stories'],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_each_child_page_is_directly_accessible_in_both_locales_with_its_own_payload(): void
    {
        $this->seedSite();

        foreach (self::PAGES as $definition) {
            foreach (['ar', 'en'] as $locale) {
                $path = '/'.$locale.$definition['path'];
                $props = $this->inertiaProps($this->get($path)->assertOk());

                $this->assertSame($definition['component'], $props['component'] ?? null);
                $this->assertSame($locale, $props['locale'] ?? null);
                $this->assertSame($definition['contentKeys'], array_keys($props['content'] ?? []));
                $this->assertStringContainsString(
                    $definition['labels'][$locale],
                    (string) ($props['meta']['og']['title'] ?? ''),
                );
                $this->assertSame($locale, $props['meta']['locale'] ?? null);
                $this->assertSame(url($path), $props['meta']['canonical'] ?? null);
            }
        }
    }

    public function test_child_page_visibility_is_independent_and_mirrored_in_navigation(): void
    {
        $this->seedSite();

        foreach (self::PAGES as $definition) {
            WebsitePage::query()->where('key', $definition['page'])->firstOrFail()
                ->update(['is_enabled' => false]);
            app(NavigationService::class)->flush();

            $this->get('/en'.$definition['path'])->assertNotFound();
            $this->get('/en'.$definition['sibling'])->assertOk();

            $props = $this->inertiaProps($this->get('/en/contact')->assertOk());
            $group = collect($props['navigation']['primary'] ?? [])->firstWhere('key', $definition['group']);

            $this->assertNull(
                collect($group['children'] ?? [])->firstWhere('key', $definition['page']),
                $definition['page'].' is still present in navigation.',
            );

            WebsitePage::query()->where('key', $definition['page'])->firstOrFail()
                ->update(['is_enabled' => true]);
            app(NavigationService::class)->flush();
        }
    }

    public function test_navigation_exposes_only_indirect_child_urls_without_hash_fragments(): void
    {
        $this->seedSite();

        foreach (['ar', 'en'] as $locale) {
            $props = $this->inertiaProps($this->get('/'.$locale.'/contact')->assertOk());
            $items = collect($props['navigation']['primary'] ?? []);
            $about = collect($items->firstWhere('key', 'about')['children'] ?? []);
            $media = collect($items->firstWhere('key', 'media')['children'] ?? []);

            $this->assertSame(
                ['about_vision', 'about_objectives', 'about_achievements', 'about_board'],
                $about->pluck('key')->all(),
            );
            $this->assertSame(
                ['media_news', 'media_albums', 'media_stories'],
                $media->pluck('key')->all(),
            );

            $about->concat($media)->each(function (array $item) use ($locale): void {
                $this->assertStringStartsWith(url('/'.$locale.'/'), $item['url']);
                $this->assertStringNotContainsString('#', $item['url']);
            });
        }
    }

    public function test_stored_legacy_hash_links_resolve_to_localized_child_pages(): void
    {
        $this->seedSite();

        $item = HomepageSectionItem::query()->where('item_key', 'ticker-1')->firstOrFail();
        $map = [
            '#about' => '/en/about/vision',
            '#vision' => '/en/about/vision',
            '#objectives' => '/en/about/objectives',
            '#achievements' => '/en/about/achievements',
            '#board' => '/en/about/board',
            '#news' => '/en/media/news',
            '#albums' => '/en/media/albums',
            '#stories' => '/en/media/stories',
            '#contact' => '/en/contact',
        ];

        foreach ($map as $hash => $expected) {
            $item->translations()->updateOrCreate(['locale' => 'en'], ['url' => $hash]);
            app(HomepageService::class)->flush();

            $ticker = collect(app(HomepageService::class)->sections('en'))->firstWhere('key', 'ticker');
            $serialized = collect($ticker['items'] ?? [])->firstWhere('key', 'ticker-1');

            $this->assertSame($expected, $serialized['url'] ?? null, $hash.' was not normalized.');
        }

        $item->translations()->updateOrCreate(['locale' => 'en'], ['url' => '#targets']);
        app(HomepageService::class)->flush();

        $ticker = collect(app(HomepageService::class)->sections('en'))->firstWhere('key', 'ticker');
        $serialized = collect($ticker['items'] ?? [])->firstWhere('key', 'ticker-1');

        $this->assertSame('#targets', $serialized['url'] ?? null, 'Genuine in-page anchors must survive.');
    }

    public function test_unprefixed_child_paths_redirect_to_the_active_locale_and_keep_the_query(): void
    {
        $this->seedSite();

        foreach (['ar', 'en'] as $locale) {
            foreach (self::PAGES as $definition) {
                $this->withCookie('pcdr_locale', $locale)
                    ->withHeaders(['Accept-Language' => $locale])
                    ->get($definition['path'].'?ref=child-page')
                    ->assertRedirect('/'.$locale.$definition['path'].'?ref=child-page');
            }
        }
    }

    public function test_legacy_child_menu_keys_still_resolve_to_independent_pages(): void
    {
        $this->seedSite();

        $menu = NavigationMenu::query()->where('key', 'primary')->firstOrFail();
        $about = NavigationItem::query()
            ->where('navigation_menu_id', $menu->id)
            ->whereNull('parent_id')
            ->where('link_value', 'about')
            ->firstOrFail();

        $legacy = NavigationItem::query()->create([
            'navigation_menu_id' => $menu->id,
            'parent_id' => $about->id,
            'link_type' => 'internal',
            'link_value' => 'vision',
            'is_enabled' => true,
            'display_order' => 99,
        ]);
        $legacy->translations()->create(['locale' => 'en', 'label' => 'Legacy vision']);
        app(NavigationService::class)->flush();

        $props = $this->inertiaProps($this->get('/en/contact')->assertOk());
        $children = collect($props['navigation']['primary'])
            ->firstWhere('key', 'about')['children'] ?? [];

        $this->assertSame(
            url('/en/about/vision'),
            collect($children)->firstWhere('label', 'Legacy vision')['url'] ?? null,
        );
    }

    private function seedSite(): void
    {
        $this->seed(SiteSettingsSeeder::class);
        $this->seed(WebsiteSeeder::class);
        $this->seed(HomepagePrototypeContentSeeder::class);
        $this->seed(CmsStructureSeeder::class);

        app(SiteSettings::class)->flush();
        app(HomepageService::class)->flush();
        app(NavigationService::class)->flush();
    }

    /** @return array<string, mixed> */
    private function inertiaProps(TestResponse $response): array
    {
        $html = str_replace('\\/', '/', (string) $response->getContent());

        $this->assertSame(
            1,
            preg_match('/<script data-page="app" type="application\/json">(.*?)<\/script>/s', $html, $matches),
            'Inertia page payload was not found in the HTML response.',
        );

        $page = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($page);

        return array_merge(
            (array) ($page['props'] ?? []),
            ['component' => $page['component'] ?? null],
        );
    }
}
