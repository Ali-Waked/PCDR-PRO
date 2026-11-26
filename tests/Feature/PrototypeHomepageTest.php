<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HomepageSection;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessManagementService;
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

    public function test_authorized_admin_can_create_update_and_delete_prototype_sections(): void
    {
        $editor = User::factory()->create();
        $this->grantPermission($editor, 'homepage.update');

        $this->actingAs($editor)
            ->post(route('cms.homepage.sections.store', ['locale' => 'ar']), [
                'key' => 'prototype-test',
                'type' => 'prototype-test',
            ])
            ->assertRedirect();

        $section = HomepageSection::query()->where('key', 'prototype-test')->firstOrFail();

        $this->actingAs($editor)
            ->put(route('cms.homepage.update', ['locale' => 'ar']), [
                'sections' => [[
                    'id' => $section->id,
                    'is_enabled' => true,
                    'display_order' => 99,
                    'translations' => [
                        'ar' => [
                            'title' => 'اختبار',
                            'tickerLabel' => 'أهم الأخبار',
                            'tabLabels' => ['news' => 'أخبار', 'publications' => 'إصدارات', 'projects' => 'مشاريع'],
                        ],
                    ],
                    'items' => [[
                        'item_type' => 'inline',
                        'item_key' => 'prototype-item',
                        'is_published' => true,
                        'sort_order' => 1,
                        'tab' => 'news',
                        'featured' => true,
                        'motifKey' => 'm-sun',
                        'translations' => [
                            'ar' => [
                                'title' => 'عنوان',
                                'body' => 'نص',
                                'display' => '11',
                                'url' => '#news',
                                'unit' => 'محوراً',
                                'dateLabel' => '2027',
                                'ctaLabel' => 'أكمل القراءة',
                                'caption' => 'وصف',
                            ],
                        ],
                    ]],
                ]],
            ])
            ->assertRedirect();

        $this->assertSame('أهم الأخبار', $section->fresh()->t('ticker_label', 'ar'));
        $this->assertSame(['news' => 'أخبار', 'publications' => 'إصدارات', 'projects' => 'مشاريع'], $section->fresh()->t('tab_labels', 'ar'));

        $item = $section->items()->firstOrFail();
        $this->assertSame('m-sun', $item->motif_key);
        $this->assertSame('news', $item->tab);
        $this->assertTrue((bool) $item->is_featured);
        $this->assertSame('محوراً', $item->t('unit', 'ar'));

        $this->actingAs($editor)
            ->delete(route('cms.homepage.sections.destroy', ['locale' => 'ar', 'homepageSection' => $section]))
            ->assertRedirect();

        $this->assertDatabaseMissing('homepage_sections', ['id' => $section->id]);
    }

    public function test_admin_endpoints_require_homepage_permission(): void
    {
        $section = HomepageSection::query()->create([
            'key' => 'protected-section',
            'type' => 'protected-section',
            'status' => 'published',
            'is_enabled' => true,
            'display_order' => 0,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('cms.homepage.sections.store', ['locale' => 'ar']), ['key' => 'denied', 'type' => 'denied'])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('cms.homepage.sections.destroy', ['locale' => 'ar', 'homepageSection' => $section]))
            ->assertForbidden();

        $this->assertDatabaseHas('homepage_sections', ['key' => 'protected-section']);
        $this->assertDatabaseMissing('homepage_sections', ['key' => 'denied']);
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

    private function grantPermission(User $user, string $permission): void
    {
        $role = Role::create(['key' => 'prototype-'.$permission, 'name' => 'Prototype '.$permission]);
        $role->permissions()->attach(Permission::create([
            'key' => $permission,
            'name' => $permission,
            'module' => 'website',
        ]));

        app(AccessManagementService::class)->syncRoles($user, [$role->id]);
    }
}
