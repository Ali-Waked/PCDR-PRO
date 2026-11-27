<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CmsPagesModuleRemovalAndStrategicContentTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_ARABIC_NOTICE = 'الأرقام الواردة من الخطة الاستراتيجية مستهدفات تخطيطية أولية، ولا تمثل نتائج محققة.';

    private const OLD_ENGLISH_NOTICE = 'Figures from the strategic plan are initial planning targets and do not represent achieved results.';

    private const STRATEGIC_STATEMENT = [
        'ar' => 'نعمل بروح الشراكة والمشاركة المجتمعية لتنسيق الاستجابة الإنسانية، وتمكين الأسر والفئات الأكثر هشاشة، والمساهمة في بناء مجتمع فلسطيني أكثر قدرة على الصمود والتعافي، انطلاقاً من شعارنا: من الاستجابة الإنسانية إلى التعافي والتنمية المستدامة.',
        'en' => 'Through partnership and community participation, we coordinate humanitarian response, empower the most vulnerable families and groups, and contribute to a Palestinian society better able to withstand crises and recover, guided by our strategic slogan: from humanitarian response to recovery and sustainable development.',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_page_routes_and_page_permissions_are_removed(): void
    {
        foreach (['index', 'create', 'store', 'edit', 'update', 'destroy', 'status'] as $action) {
            $this->assertFalse(Route::has("cms.pages.{$action}"));
        }

        foreach (['view', 'create', 'update', 'delete', 'publish', 'unpublish'] as $action) {
            $this->assertArrayNotHasKey("pages.{$action}", (array) config('permissions', []));
            $this->assertArrayNotHasKey("pages.{$action}", (array) config('cms.permissions', []));
        }

        $navigationKeys = [];
        foreach ((array) config('admin_navigation.groups', []) as $group) {
            foreach ((array) ($group['items'] ?? []) as $item) {
                $navigationKeys[] = $item['key'] ?? null;
            }
        }

        $this->assertNotContains('pages', $navigationKeys);

        foreach (['ar', 'en'] as $locale) {
            $this->get("/{$locale}/cms/pages")->assertNotFound();
            $this->get("/{$locale}/cms/pages/create")->assertNotFound();
        }
    }

    public function test_other_cms_modules_remain_accessible_without_pages_navigation(): void
    {
        $user = $this->userWith([
            'pillars.view', 'news.view', 'publications.view', 'media.view',
        ]);

        foreach (['cms.dashboard', 'cms.pillars.index', 'cms.news.index', 'cms.publications.index'] as $route) {
            $response = $this->actingAs($user)->get(route($route, ['locale' => 'ar']));
            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->where('adminNavigation', function ($groups): bool {
                    foreach ($groups as $group) {
                        foreach ((array) ($group['items'] ?? []) as $item) {
                            if (($item['key'] ?? null) === 'pages') {
                                return false;
                            }
                        }
                    }

                    return true;
                }));
        }
    }

    public function test_removed_legacy_public_paths_stay_absent_after_admin_module_removal(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $this->get(route('public.home', ['locale' => $locale]))
                ->assertOk()
                ->assertViewIs('app');
        }

        foreach (['about', 'programs', 'media', 'partners', 'contact', 'complaints'] as $path) {
            foreach (['ar', 'en'] as $locale) {
                $this->get('/'.$locale.'/'.$path)
                    ->assertOk()
                    ->assertViewIs('app');
            }
        }

        foreach (['strategic-areas', 'news', 'publications', 'transparency', 'get-involved'] as $path) {
            foreach (['ar', 'en'] as $locale) {
                $this->get('/'.$locale.'/'.$path)->assertNotFound();
            }
        }
    }

    public function test_obsolete_disclaimer_is_absent_and_strategic_content_is_present_in_both_locales(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $response = $this->get(route('public.home', ['locale' => $locale]));
            $response->assertOk();

            $html = (string) $response->getContent();
            $this->assertStringNotContainsString(self::OLD_ARABIC_NOTICE, $html);
            $this->assertStringNotContainsString(self::OLD_ENGLISH_NOTICE, $html);
            $this->assertStringNotContainsString('planning_targets_notice', $html);

            $response->assertInertia(fn (Assert $page) => $page
                ->where('ui.strategic_statement', self::STRATEGIC_STATEMENT[$locale])
                ->where('ui.strategic_programs_intro', fn ($value): bool => is_string($value) && $value !== '')
                ->where('ui.strategic_targets_intro', fn ($value): bool => is_string($value) && $value !== '')
                ->where('ui.strategic_partnerships_intro', fn ($value): bool => is_string($value) && $value !== ''));
        }
    }

    /** @param list<string> $keys */
    private function userWith(array $keys): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'key' => 'cms-removal-test-'.$user->id,
            'name' => 'CMS removal test user',
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

        app(AccessManagementService::class)->syncRoles($user, [$role->getKey()]);

        return $user;
    }
}
