<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessManagementService;
use App\Services\RbacService;
use FilesystemIterator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

final class AdminNavigationReorganizationTest extends TestCase
{
    use RefreshDatabase;

    private const DISCLAIMER_EN = 'Technical administrator only; institutional authority is not granted.';

    private const DISCLAIMER_AR = 'مدير تقني فقط؛ لا تُمنح صلاحية مؤسسية.';

    private const GROUP_KEYS = ['overview', 'content', 'communications', 'identity', 'system'];

    private const GROUP_LABEL_KEYS = ['overview', 'content_website', 'communications', 'identity_security', 'system'];

    private const ROUTE_NAMES = [
        'dashboard' => 'dashboard',
        'cms_dashboard' => 'cms.dashboard',
        'content_management' => 'cms.content-management.index',
        'pillars' => 'cms.pillars.index',
        'programs' => 'cms.programs.index',
        'projects' => 'cms.projects.index',
        'news' => 'cms.news.index',
        'publications' => 'cms.publications.index',
        'partner_directory' => 'cms.partner-directory.index',
        'media' => 'cms.media.index',
        'cms_settings' => 'cms.settings.index',
        'inquiries' => 'cms.inquiries.index',
        'email_templates' => 'cms.email-templates.index',
        'roles' => 'admin.access.index',
        'users' => 'admin.users.index',
        'audit_logs' => 'admin.audit-logs.index',
        'audit_archives' => 'admin.audit-archives.index',
        'platform_settings' => 'admin.settings.index',
    ];

    public function test_configured_navigation_matches_the_five_target_groups_and_item_order(): void
    {
        /** @var array{groups: list<array{key: string, label: string, feature?: string, items: list<array{key: string, label: string, path: string, permission: ?string}>}>} $navigation */
        $navigation = require config_path('admin_navigation.php');
        $groups = $navigation['groups'];

        $this->assertSame(self::GROUP_KEYS, array_column($groups, 'key'));
        $this->assertSame(self::GROUP_LABEL_KEYS, array_column($groups, 'label'));
        $this->assertSame(['overview', 'content', 'engagement', 'access', 'access'], array_column($groups, 'feature'));

        $expectedItems = [
            'overview' => [
                ['dashboard', '/dashboard'],
            ],
            'content' => [
                ['cms_dashboard', '/cms'],
                ['content_management', '/cms/content-management'],
                ['pillars', '/cms/pillars'],
                ['programs', '/cms/programs'],
                ['projects', '/cms/projects'],
                ['news', '/cms/news'],
                ['publications', '/cms/publications'],
                ['partner_directory', '/cms/partner-directory'],
                ['media', '/cms/media'],
                ['cms_settings', '/cms/settings'],
            ],
            'communications' => [
                ['inquiries', '/cms/inquiries'],
                ['email_templates', '/cms/email-templates'],
            ],
            'identity' => [
                ['roles', '/admin/access'],
                ['users', '/admin/users'],
                ['audit_logs', '/admin/audit-logs'],
                ['audit_archives', '/admin/audit-archives'],
            ],
            'system' => [
                ['platform_settings', '/admin/settings'],
            ],
        ];

        $allKeys = [];
        $allPaths = [];

        foreach ($groups as $group) {
            $actual = array_map(
                fn (array $item): array => [$item['key'], $item['path']],
                $group['items'],
            );

            $this->assertSame($expectedItems[$group['key']], $actual, $group['key']);

            foreach ($group['items'] as $item) {
                $allKeys[] = $item['key'];
                $allPaths[] = $item['path'];
            }
        }

        $this->assertCount(18, $allKeys);
        $this->assertSame($allKeys, array_values(array_unique($allKeys)));
        $this->assertSame($allPaths, array_values(array_unique($allPaths)));
    }

    public function test_every_navigation_destination_matches_an_existing_localized_route(): void
    {
        /** @var array{groups: list<array{items: list<array{key: string, path: string}>}>} $navigation */
        $navigation = require config_path('admin_navigation.php');

        foreach ($navigation['groups'] as $group) {
            foreach ($group['items'] as $item) {
                $routeName = self::ROUTE_NAMES[$item['key']] ?? null;

                $this->assertNotNull($routeName, $item['key']);
                $this->assertTrue(Route::has($routeName), $routeName);

                foreach (['ar', 'en'] as $locale) {
                    $this->assertSame(
                        "/{$locale}{$item['path']}",
                        parse_url((string) route($routeName, ['locale' => $locale]), PHP_URL_PATH),
                        "{$routeName} ({$locale})",
                    );
                }
            }
        }
    }

    public function test_owner_can_reach_every_configured_navigation_destination(): void
    {
        $owner = User::factory()->platformOwner()->create();

        /** @var array{groups: list<array{items: list<array{key: string, path: string}>}>} $navigation */
        $navigation = require config_path('admin_navigation.php');

        foreach ($navigation['groups'] as $group) {
            foreach ($group['items'] as $item) {
                $this->actingAs($owner)
                    ->get('/ar'.$item['path'])
                    ->assertOk($item['key']);
            }
        }
    }

    public function test_owner_sees_localized_group_and_item_labels_in_both_locales(): void
    {
        $owner = User::factory()->platformOwner()->create();

        $expectations = [
            'ar' => [
                'direction' => 'rtl',
                'groups' => ['نظرة عامة', 'المحتوى والموقع', 'التواصل', 'الهوية والأمان', 'النظام'],
                'items' => [
                    'overview' => ['لوحة التحكم'],
                    'content' => ['لوحة المحتوى', 'إدارة محتوى الموقع', 'المحاور الاستراتيجية', 'البرامج', 'المشاريع', 'الأخبار', 'الإصدارات', 'دليل الشركاء', 'مكتبة الوسائط', 'إعدادات الموقع'],
                    'communications' => ['الاستفسارات', 'قوالب البريد الإلكتروني'],
                    'identity' => ['الأدوار والصلاحيات', 'المستخدمون', 'سجلات التدقيق', 'أرشيف سجلات التدقيق'],
                    'system' => ['إعدادات المنصة'],
                ],
            ],
            'en' => [
                'direction' => 'ltr',
                'groups' => ['Overview', 'Content & Website', 'Communications', 'Identity & Security', 'System'],
                'items' => [
                    'overview' => ['Dashboard'],
                    'content' => ['Content dashboard', 'Content Management', 'Strategic pillars', 'Programmes', 'Projects', 'News', 'Publications', 'Partners directory', 'Media library', 'Site settings'],
                    'communications' => ['Inquiries', 'Email templates'],
                    'identity' => ['Roles & Permissions', 'Users', 'Audit logs', 'Audit archives'],
                    'system' => ['Platform settings'],
                ],
            ],
        ];

        foreach ($expectations as $locale => $expected) {
            $this->actingAs($owner)->get("/{$locale}/dashboard")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('locale', $locale)
                    ->where('direction', $expected['direction'])
                    ->where('adminNavigation', function ($groups) use ($expected): bool {
                        if ($groups->count() !== 5 || $groups->pluck('key')->all() !== self::GROUP_KEYS) {
                            return false;
                        }

                        if ($groups->pluck('label')->all() !== $expected['groups']) {
                            return false;
                        }

                        foreach ($expected['items'] as $groupKey => $labels) {
                            $group = $groups->firstWhere('key', $groupKey);

                            if (! is_array($group) || array_column($group['items'], 'label') !== $labels) {
                                return false;
                            }
                        }

                        return true;
                    }));
        }
    }

    public function test_navigation_translation_keys_are_available_in_english_and_arabic(): void
    {
        $labels = [
            'overview' => ['Overview', 'نظرة عامة'],
            'content_website' => ['Content & Website', 'المحتوى والموقع'],
            'communications' => ['Communications', 'التواصل'],
            'identity_security' => ['Identity & Security', 'الهوية والأمان'],
            'system' => ['System', 'النظام'],
            'roles_permissions' => ['Roles & Permissions', 'الأدوار والصلاحيات'],
            'cms_dashboard' => ['Content dashboard', 'لوحة المحتوى'],
            'media' => ['Media library', 'مكتبة الوسائط'],
            'cms_settings' => ['Site settings', 'إعدادات الموقع'],
        ];

        foreach ($labels as $key => [$english, $arabic]) {
            $this->assertSame($english, __("ui.admin_nav.{$key}", [], 'en'));
            $this->assertSame($arabic, __("ui.admin_nav.{$key}", [], 'ar'));
        }
    }

    public function test_permission_filtering_preserves_authorization_boundaries(): void
    {
        $role = Role::create(['key' => 'cms_user_viewer', 'name' => 'CMS user viewer']);
        $permission = Permission::create(['key' => 'users.view', 'name' => 'View users', 'module' => 'iam']);
        $role->permissions()->attach($permission);

        $user = User::factory()->create();
        app(AccessManagementService::class)->syncRoles($user, [$role->id]);

        $this->actingAs($user)->get('/ar/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('authority.is_platform_owner', false)
                ->where('authority.institutional_approval.granted', false)
                ->where('adminNavigation', function ($groups): bool {
                    if ($groups->count() !== 2 || $groups->pluck('key')->all() !== ['overview', 'identity']) {
                        return false;
                    }

                    return array_column($groups->firstWhere('key', 'identity')['items'], 'key') === ['users'];
                }));
    }

    public function test_sidebar_renderers_preserve_active_state_and_mobile_behavior(): void
    {
        $sidebars = [
            resource_path('js/Layouts/CmsLayout.vue'),
            resource_path('js/Components/Dashboard/DashboardChrome.vue'),
        ];

        foreach ($sidebars as $path) {
            $source = (string) file_get_contents($path);

            foreach (['item.active', 'aria-current', 'is-active', 'startsWith(`${href}/`)', 'matchMedia(\'(min-width: 1100px)\')'] as $fragment) {
                $this->assertStringContainsString($fragment, $source, $path);
            }
        }

        $cmsLayout = (string) file_get_contents(resource_path('js/Layouts/CmsLayout.vue'));
        $this->assertStringContainsString('mobileNavigationOpen', $cmsLayout);
        $this->assertStringContainsString('admin-nav-scrim', $cmsLayout);

        $dashboardChrome = (string) file_get_contents(resource_path('js/Components/Dashboard/DashboardChrome.vue'));
        $this->assertStringContainsString('mobileOpen', $dashboardChrome);
        $this->assertStringContainsString('pc-scrim', $dashboardChrome);

        $this->assertStringContainsString('overflow-x: hidden', (string) file_get_contents(resource_path('css/app.css')));
        $this->assertStringContainsString('overflow-x: hidden', (string) file_get_contents(resource_path('css/dashboard.css')));
        $this->assertStringContainsString('@media (min-width: 1100px)', (string) file_get_contents(resource_path('css/app.css')));
        $this->assertStringContainsString('@media (max-width: 1100px)', (string) file_get_contents(resource_path('css/dashboard.css')));
    }

    public function test_disclaimer_is_absent_from_user_facing_sources(): void
    {
        $sources = $this->userFacingSources();
        $this->assertNotEmpty($sources);

        foreach ($sources as $path) {
            $source = (string) file_get_contents($path);

            $this->assertStringNotContainsString(self::DISCLAIMER_EN, $source, $path);
            $this->assertStringNotContainsString(self::DISCLAIMER_AR, $source, $path);
            $this->assertStringNotContainsString('dashboard_owner_note', $source, $path);
        }

        $owner = User::factory()->platformOwner()->create();

        $this->actingAs($owner)->get('/en/dashboard')
            ->assertOk()
            ->assertDontSee(self::DISCLAIMER_EN, false)
            ->assertInertia(fn (Assert $page) => $page->missing('ui.dashboard_owner_note'));
    }

    public function test_governance_and_rbac_boundaries_remain_unchanged(): void
    {
        $owner = User::factory()->platformOwner()->create();
        $rbac = app(RbacService::class);

        $authorityKeys = [
            'content.approve',
            'institutional.appoint',
            'governance.ratify',
            'documents.signoff_request',
            'future.module.nomination',
        ];

        foreach ($authorityKeys as $permission) {
            $this->assertTrue($rbac->isAuthorityPermission($permission), $permission);
            $this->assertFalse($rbac->has($owner, $permission), $permission);
        }

        $this->assertContains('institutional.', (array) config('governance.authority.permission_prefixes'));
        $this->assertContains('.approve', (array) config('governance.authority.permission_suffixes'));
        foreach (['admin.permissions.create', 'admin.permissions.store', 'admin.permissions.edit', 'admin.permissions.update', 'admin.permissions.destroy'] as $route) {
            $this->assertFalse(Route::has($route), $route);
        }

        $this->actingAs($owner)->get('/ar/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('authority.is_platform_owner', true)
                ->where('authority.institutional_approval.granted', false)
                ->where('authority.denied_permission_patterns', fn ($patterns): bool => collect($patterns)->contains('institutional.')
                    && collect($patterns)->contains('.approve')));

        $decisions = (string) file_get_contents(base_path('docs/architecture/ARCHITECTURE_DECISIONS.md'));
        $this->assertStringContainsString('All 17 ADRs are **PROPOSED**', $decisions);
        $this->assertStringContainsString('OQ-49 is **PARTIALLY RESOLVED — PENDING FORMAL APPROVAL**', $decisions);
    }

    /** @return list<string> */
    private function userFacingSources(): array
    {
        $directories = [
            'lang/en',
            'lang/ar',
            'resources/js',
            'resources/css',
            'config',
            'database/seeders',
        ];

        $files = [];

        foreach ($directories as $directory) {
            $absolute = base_path($directory);

            if (! is_dir($absolute)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                if (! in_array(strtolower($file->getExtension()), ['php', 'vue', 'js', 'css'], true)) {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
