<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\HomepageSection;
use App\Services\HomepageService;
use Database\Seeders\HomepageContentSeeder;
use Database\Seeders\SiteSettingsSeeder;
use Database\Seeders\WebsiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class PublicLinkCrawlTest extends TestCase
{
    use RefreshDatabase;

    private const REMOVED_PREFIXES = [
        'about', 'strategic-areas', 'programs', 'projects', 'news', 'publications',
        'transparency', 'partnerships', 'get-involved', 'contact',
        'complaints', 'pages',
    ];

    public function test_seeded_home_links_never_point_to_removed_public_pages(): void
    {
        $this->seed(SiteSettingsSeeder::class);
        $this->seed(WebsiteSeeder::class);
        $this->seed(HomepageContentSeeder::class);

        foreach (['ar', 'en'] as $locale) {
            $props = $this->inertiaProps($this->get('/'.$locale));
            $links = $this->collectLinkValues($props['sections'] ?? []);

            $this->assertNotEmpty($links);
            $this->assertContains(null, $links, 'Dead stored links must be neutralized.');

            foreach ($links as $link) {
                if ($link === null) {
                    continue;
                }

                $this->assertFalse($this->isRemovedPath($link), $link.' points to a removed page.');
            }
        }
    }

    public function test_seeded_partner_links_point_to_the_public_directory(): void
    {
        $this->seed(SiteSettingsSeeder::class);
        $this->seed(WebsiteSeeder::class);
        $this->seed(HomepageContentSeeder::class);

        foreach (['ar', 'en'] as $locale) {
            $links = $this->collectLinkValues($this->inertiaProps($this->get('/'.$locale))['sections'] ?? []);

            $this->assertContains('/'.$locale.'/partners', $links);
            $this->assertFalse($this->isRemovedPath('/'.$locale.'/partners'));
        }
    }

    public function test_new_stored_partner_links_are_preserved_before_rendering(): void
    {
        $this->seed(SiteSettingsSeeder::class);
        $this->seed(WebsiteSeeder::class);
        $this->seed(HomepageContentSeeder::class);

        $section = HomepageSection::query()->create([
            'key' => 'partner-link-probe',
            'type' => 'intro',
            'status' => ContentStatus::Published->value,
            'published_at' => now()->subDay(),
            'is_enabled' => true,
            'display_order' => 99,
        ]);
        $section->translations()->create([
            'locale' => 'ar',
            'title' => 'رابط الشركاء',
            'cta_link' => '/ar/partners',
        ]);
        app(HomepageService::class)->flush();

        $props = $this->inertiaProps($this->get('/ar'));
        $probe = collect($props['sections'])->firstWhere('key', 'partner-link-probe');

        $this->assertIsArray($probe);
        $this->assertSame('/ar/partners', $section->fresh()->t('cta_link', 'ar'));
        $this->assertSame('/ar/partners', $probe['ctaLink']);
    }

    public function test_new_stored_dead_links_are_neutralized_before_rendering(): void
    {
        $this->seed(SiteSettingsSeeder::class);
        $this->seed(WebsiteSeeder::class);
        $this->seed(HomepageContentSeeder::class);

        $section = HomepageSection::query()->create([
            'key' => 'crawl-probe',
            'type' => 'intro',
            'status' => ContentStatus::Published->value,
            'published_at' => now()->subDay(),
            'is_enabled' => true,
            'display_order' => 99,
        ]);
        $section->translations()->create([
            'locale' => 'ar',
            'title' => 'فحص الروابط',
            'cta_link' => '/ar/about',
        ]);
        app(HomepageService::class)->flush();

        $props = $this->inertiaProps($this->get('/ar'));
        $probe = collect($props['sections'])->firstWhere('key', 'crawl-probe');

        $this->assertIsArray($probe);
        $this->assertSame('/ar/about', $section->fresh()->t('cta_link', 'ar'));
        $this->assertNull($probe['ctaLink']);
    }

    public function test_not_found_suggestions_only_point_to_home(): void
    {
        $this->assertSame(
            [['label' => 'home', 'route' => 'public.home']],
            config('website.not_found_suggestions'),
        );

        $html = (string) $this->get('/ar/about')->getContent();

        $this->assertStringContainsString('href="'.route('public.home', ['locale' => 'ar']).'"', $html);
        $this->assertStringNotContainsString('href="'.url('/ar/about').'"', $html);
    }

    /** @return list<mixed> */
    private function collectLinkValues(mixed $value): array
    {
        $links = [];

        if (! is_array($value)) {
            return $links;
        }

        foreach ($value as $key => $item) {
            if (in_array($key, ['ctaLink', 'videoUrl', 'url'], true)) {
                $links[] = $item;
            }

            $links = array_merge($links, $this->collectLinkValues($item));
        }

        return $links;
    }

    private function isRemovedPath(string $link): bool
    {
        $path = (string) (parse_url($link, PHP_URL_PATH) ?? '');
        $path = '/'.ltrim($path, '/');

        if (preg_match('#^/(ar|en)/?$#', $path) === 1) {
            return false;
        }

        $path = (string) preg_replace('#^/(ar|en)(?=/)#', '', $path);

        foreach (self::REMOVED_PREFIXES as $prefix) {
            if ($path === '/'.$prefix || str_starts_with($path, '/'.$prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function inertiaProps(TestResponse $response): array
    {
        $html = (string) $response->getContent();

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
