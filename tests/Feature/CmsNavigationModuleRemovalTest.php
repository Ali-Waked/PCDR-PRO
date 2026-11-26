<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessManagementService;
use Database\Seeders\WebsiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CmsNavigationModuleRemovalTest extends TestCase
{
    use RefreshDatabase;

    private const REMOVED_ADMIN_FRAGMENTS = [
        'Cms/Navigation/Index',
        'NavigationMenuEditor',
        '/cms/navigation',
        'navigation.view',
        'navigation.update',
        'cms.navigation.index',
        'cms.navigation.update',
        'cms_navigation',
        'ui.admin_nav.navigation',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_navigation_routes_assets_and_legacy_urls_are_removed(): void
    {
        $this->assertFalse(Route::has('cms.navigation.index'));
        $this->assertFalse(Route::has('cms.navigation.update'));

        $this->assertFileDoesNotExist(app_path('Http/Controllers/Cms/NavigationController.php'));
        $this->assertFileDoesNotExist(resource_path('js/Pages/Cms/Navigation/Index.vue'));
        $this->assertFileDoesNotExist(resource_path('js/Components/Cms/NavigationMenuEditor.vue'));

        $owner = User::factory()->platformOwner()->create();

        foreach (['ar', 'en'] as $locale) {
            $this->actingAs($owner)->get("/{$locale}/cms/navigation")->assertNotFound();
            $this->actingAs($owner)->put("/{$locale}/cms/navigation/1")->assertNotFound();
        }

        $this->actingAs($owner)->get('/cms/navigation')->assertNotFound();
    }

    public function test_code_sources_no_longer_reference_the_admin_navigation_module(): void
    {
        foreach ($this->sourceFiles() as $path) {
            $source = (string) File::get($path);

            foreach (self::REMOVED_ADMIN_FRAGMENTS as $fragment) {
                $this->assertStringNotContainsString($fragment, $source, $path);
            }
        }
    }

    public function test_code_owned_navigation_permissions_and_exclusive_translations_are_removed(): void
    {
        foreach (['navigation.view', 'navigation.update'] as $key) {
            $this->assertArrayNotHasKey($key, (array) config('permissions', []));
            $this->assertArrayNotHasKey($key, (array) config('cms.permissions', []));
        }

        $navigationKeys = [];
        foreach ((array) config('admin_navigation.groups', []) as $group) {
            foreach ((array) ($group['items'] ?? []) as $item) {
                $navigationKeys[] = $item['key'] ?? null;
            }
        }

        $this->assertNotContains('navigation', $navigationKeys);
        $this->assertSame('ui.admin_nav.navigation', __('ui.admin_nav.navigation', [], 'en'));
        $this->assertSame('ui.admin_nav.navigation', __('ui.admin_nav.navigation', [], 'ar'));
        $this->assertSame('website.ui.cms_navigation', __('website.ui.cms_navigation', [], 'en'));
        $this->assertSame('website.ui.cms_navigation', __('website.ui.cms_navigation', [], 'ar'));
    }

    public function test_seeded_navigation_is_still_shared_in_arabic_and_english(): void
    {
        $this->seed(WebsiteSeeder::class);

        foreach (['ar' => 'الرئيسية', 'en' => 'Home'] as $locale => $homeLabel) {
            $this->get("/{$locale}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('locale', $locale)
                    ->where('navigation.primary', function ($items) use ($homeLabel): bool {
                        $items = is_array($items) ? $items : $items->all();

                        return count($items) === 10
                            && collect($items)->pluck('label')->contains($homeLabel)
                            && collect($items)->pluck('url')->isNotEmpty();
                    })
                    ->where('navigation.footer', function ($items): bool {
                        $items = is_array($items) ? $items : $items->all();

                        return count($items) === 10;
                    }));

            $props = $this->inertiaProps($this->get("/{$locale}"));
            $urls = array_column($props['navigation']['primary'], 'url');

            $this->assertContains(url("/{$locale}"), $urls);
            $this->assertNotContains(url('/ar/en'), $urls);
            $this->assertNotContains(url('/en/ar'), $urls);

            foreach (['about', 'strategic-areas', 'programs', 'news', 'publications', 'contact'] as $removedPath) {
                $this->assertStringNotContainsString('/'.$removedPath, implode(' ', $urls));
            }
        }
    }

    public function test_admin_shell_mobile_navigation_remains_wired(): void
    {
        $cmsLayout = (string) File::get(resource_path('js/Layouts/CmsLayout.vue'));
        $this->assertStringContainsString('mobileNavigationOpen', $cmsLayout);
        $this->assertStringContainsString('admin-nav-scrim', $cmsLayout);

        $dashboardChrome = (string) File::get(resource_path('js/Components/Dashboard/DashboardChrome.vue'));
        $this->assertStringContainsString('mobileOpen', $dashboardChrome);
        $this->assertStringContainsString('pc-scrim', $dashboardChrome);
    }

    public function test_other_cms_modules_remain_accessible_without_navigation_management(): void
    {
        $user = $this->userWith([
            'pillars.view', 'news.view', 'publications.view', 'media.view', 'homepage.view',
        ]);

        foreach (['cms.dashboard', 'cms.pillars.index', 'cms.news.index', 'cms.publications.index', 'cms.homepage.index'] as $route) {
            $this->actingAs($user)->get(route($route, ['locale' => 'ar']))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('adminNavigation', function ($groups): bool {
                        foreach ($groups as $group) {
                            foreach ((array) ($group['items'] ?? []) as $item) {
                                if (($item['key'] ?? null) === 'navigation') {
                                    return false;
                                }
                            }
                        }

                        return true;
                    }));
        }
    }

    public function test_unified_rbac_boundaries_and_historical_permission_rows_are_preserved(): void
    {
        $owner = User::factory()->platformOwner()->create();

        $this->actingAs($owner)->get('/ar/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('authority.is_platform_owner', true)
                ->where('authority.institutional_approval.granted', false)
                ->where('adminNavigation.0.key', 'overview'));

        $historical = Permission::query()->create([
            'key' => 'navigation.view',
            'name' => 'View navigation menus',
            'module' => 'website',
            'is_retired' => true,
        ]);
        $role = Role::query()->create(['key' => 'legacy_navigation_auditor', 'name' => 'Legacy navigation auditor']);
        $role->permissions()->attach($historical);
        $user = User::factory()->create();
        app(AccessManagementService::class)->syncRoles($user, [$role->getKey()]);

        $this->artisan('pcdr:permissions:sync')->assertSuccessful();

        $this->assertModelExists($historical);
        $this->assertTrue((bool) $historical->fresh()->is_retired);
        $this->assertDatabaseHas('permission_role', [
            'role_id' => $role->getKey(),
            'permission_id' => $historical->getKey(),
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'permissions.synced']);
    }

    /** @param list<string> $keys */
    private function userWith(array $keys): User
    {
        $role = Role::query()->create([
            'key' => 'navigation-removal-'.count($keys),
            'name' => 'Navigation removal test user',
        ]);

        foreach ($keys as $key) {
            $definition = (array) config('permissions.'.$key, []);
            $permission = Permission::query()->firstOrCreate(['key' => $key], [
                'name' => $definition['label'] ?? $key,
                'module' => $definition['module'] ?? 'website',
                'is_protected' => false,
            ]);
            $role->permissions()->attach($permission);
        }

        $user = User::factory()->create();
        app(AccessManagementService::class)->syncRoles($user, [$role->getKey()]);

        return $user;
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

    /** @return list<string> */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (['app', 'config', 'database', 'lang', 'resources', 'routes'] as $directory) {
            foreach (File::allFiles(base_path($directory)) as $file) {
                if (! in_array(strtolower((string) $file->getExtension()), ['php', 'vue', 'js'], true)) {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
