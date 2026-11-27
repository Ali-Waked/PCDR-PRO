<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\HomepageSection;
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
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PublicFooterIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_public_pages_share_one_filtered_footer_and_one_navigation_source(): void
    {
        $this->seedSite();

        foreach (['ar', 'en'] as $locale) {
            $this->get("/{$locale}/contact")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('footer', fn ($sections): bool => $this->footerPayloadIsValid(
                        is_array($sections) ? $sections : $sections->all(),
                    ))
                    ->where('navigation.primary', fn ($items): bool => $this->primaryNavigationIsValid(
                        is_array($items) ? $items : $items->all(),
                        $locale,
                    )));
        }
    }

    public function test_about_submenu_footer_links_track_navigation_changes(): void
    {
        $this->seedSite();

        $menu = NavigationMenu::query()->where('key', 'primary')->firstOrFail();
        $about = NavigationItem::query()
            ->where('navigation_menu_id', $menu->id)
            ->whereNull('parent_id')
            ->where('link_value', 'about')
            ->firstOrFail();

        $link = NavigationItem::query()->firstOrNew([
            'navigation_menu_id' => $menu->id,
            'parent_id' => $about->id,
            'link_type' => 'internal',
            'link_value' => 'board',
        ]);
        $link->forceFill(['is_enabled' => true, 'display_order' => 99])->save();
        $link->translations()->updateOrCreate(['locale' => 'en'], ['label' => 'Updated board link']);
        app(NavigationService::class)->flush();

        $props = $this->inertiaProps($this->get('/en/contact')->assertOk());
        $children = collect($props['navigation']['primary'])
            ->firstWhere('key', 'about')['children'] ?? [];

        $this->assertCount(5, $children);
        $this->assertSame(
            ['Updated board link'],
            collect($children)->where('key', 'board')->pluck('label')->values()->all(),
        );
        $this->assertStringContainsString('#board', collect($children)->firstWhere('key', 'board')['url']);
    }

    public function test_hidden_about_page_is_removed_from_navigation_but_footer_content_is_preserved(): void
    {
        $this->seedSite();

        WebsitePage::query()->where('key', 'about')->firstOrFail()->update(['is_enabled' => false]);
        app(NavigationService::class)->flush();

        $this->get('/en/about')->assertNotFound();

        $props = $this->inertiaProps($this->get('/en/contact')->assertOk());
        $this->assertNull(collect($props['navigation']['primary'])->firstWhere('key', 'about'));
        $this->assertSame('footer', $props['footer'][0]['key'] ?? null);
        $this->assertTrue(HomepageSectionItem::query()->where('item_key', 'footer-about')->exists());
    }

    public function test_hidden_footer_section_and_item_do_not_leak_into_shared_props(): void
    {
        $this->seedSite();

        HomepageSectionItem::query()->where('item_key', 'footer-contact')->firstOrFail()
            ->update(['is_published' => false]);
        app(HomepageService::class)->flush();

        $props = $this->inertiaProps($this->get('/en/contact')->assertOk());
        $items = collect($props['footer'][0]['items'] ?? []);
        $this->assertFalse($items->pluck('key')->contains('footer-contact'));
        $this->assertTrue($items->pluck('key')->contains('footer-about'));

        HomepageSection::query()->where('key', 'footer')->firstOrFail()->update([
            'status' => ContentStatus::Draft,
            'is_enabled' => false,
        ]);
        app(HomepageService::class)->flush();

        $this->get('/en/contact')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('footer', []));
        $this->assertTrue(HomepageSectionItem::query()->where('item_key', 'footer-contact')->exists());
    }

    public function test_hidden_complaints_page_is_guarded_and_absent_from_navigation(): void
    {
        $this->seedSite();

        WebsitePage::query()->where('key', 'complaints')->firstOrFail()->update(['is_enabled' => false]);
        app(NavigationService::class)->flush();

        $this->get('/en/complaints')->assertNotFound();
        $this->post(route('public.complaints.store', ['locale' => 'en']), [
            'category' => 'service_delivery',
            'subject' => 'Complaints page is hidden',
            'body' => 'This valid submission proves the hidden page guard runs before intake.',
            'locale' => 'en',
            'is_anonymous' => true,
        ])->assertNotFound();

        $props = $this->inertiaProps($this->get('/en/contact')->assertOk());
        $this->assertNull(collect($props['navigation']['primary'])->firstWhere('key', 'complaints'));
    }

    public function test_footer_component_consumes_only_central_cms_and_navigation_sources(): void
    {
        $source = (string) File::get(resource_path('js/Components/RedesignHome/ProPrototypeFooter.vue'));

        foreach ([
            'page.props.footer',
            'page.props.navigation?.primary',
            'content.visibleSection(\'footer\')',
            'site.value?.contact',
            'site.value?.social',
            'site.value?.footerCopyright',
            'site.value?.footerLegalNote',
            'videoSource(',
            'SocialPlatformIcon',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source, $needle);
        }

        foreach (['const chrome', 'SOCIAL_KINDS', 'v-html', "localPath('about')", "localPath('complaints')"] as $needle) {
            $this->assertStringNotContainsString($needle, $source, $needle);
        }
    }

    public function test_footer_section_is_a_typed_cms_section_with_dedicated_editor_fields(): void
    {
        $this->assertSame('footer', config('cms_structure.sections.footer.content'));
        $this->assertContains('footer-', config('cms_structure.item_prefixes.footer'));

        $source = (string) File::get(resource_path('js/Pages/Cms/ContentManagement/Section.vue'));
        $this->assertStringContainsString("props.section.key === 'footer'", $source);
        $this->assertStringContainsString("body: ['slides', 'stories', 'objectives'].includes(props.section.key) || isFooter.value", $source);
        $this->assertStringContainsString("caption: props.section.key === 'albums' || isFooter.value", $source);
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

    /** @param array<int, mixed> $sections */
    private function footerPayloadIsValid(array $sections): bool
    {
        if (count($sections) !== 1 || ($sections[0]['key'] ?? null) !== 'footer') {
            return false;
        }

        $keys = collect($sections[0]['items'] ?? [])->pluck('key');

        return $keys->contains('footer-about')
            && $keys->contains('footer-contact')
            && $keys->contains('footer-video');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function primaryNavigationIsValid(array $items, string $locale): bool
    {
        $keys = collect($items)->pluck('key');
        $expected = ['home', 'about', 'programs', 'media', 'partners', 'contact', 'complaints'];
        if (count($items) !== count($expected) || ! collect($expected)->diff($keys)->isEmpty()) {
            return false;
        }

        $about = collect($items)->firstWhere('key', 'about');
        $aboutChildren = collect($about['children'] ?? []);
        $expectedChildren = ['about', 'vision', 'objectives', 'achievements', 'board'];

        return $aboutChildren->count() === count($expectedChildren)
            && collect($expectedChildren)->diff($aboutChildren->pluck('key'))->isEmpty()
            && collect($items)->pluck('url')->contains(url("/{$locale}/media"))
            && collect($items)->pluck('url')->contains(url("/{$locale}/programs"));
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

        return (array) ($page['props'] ?? []);
    }
}
