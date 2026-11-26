<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\EmailTemplateTranslation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\AccessManagementService;
use App\Services\RbacService;
use App\Services\SiteSettings;
use Database\Seeders\CmsRolesSeeder;
use Database\Seeders\EmailTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class WebsiteCmsScopeTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALE = 'ar';

    private const BOOTSTRAP_EMAIL = 'scope-editor@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', '12345678901234567890123456789012');
        config()->set('cms.bootstrap.email', self::BOOTSTRAP_EMAIL);
        config()->set('cms.local_auth_enabled', true);
        config()->set('dashboard_features.hidden_modules_access', false);

        $this->withoutVite();
    }

    public function test_active_navigation_contains_only_website_cms_groups(): void
    {
        $active = array_column((array) config('admin_navigation.groups', []), 'key');
        $preserved = array_column((array) config('admin_navigation_hidden.groups', []), 'key');

        $this->assertSame(['overview', 'content', 'communications', 'identity', 'system'], $active);
        $this->assertContains('operations', $preserved);
        $this->assertContains('phase5', $preserved);

        $features = (array) config('dashboard_features.features', []);

        foreach (['operations', 'phase5'] as $key) {
            $this->assertSame('hidden', $features[$key]['state'] ?? null);
        }

        foreach (['overview', 'content', 'engagement', 'access'] as $key) {
            $this->assertSame('enabled', $features[$key]['state'] ?? null);
        }
    }

    public function test_platform_owner_dashboard_hides_preserved_operational_modules(): void
    {
        $owner = User::factory()->platformOwner()->create();

        $this->actingAs($owner)->get('/'.self::LOCALE.'/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('adminNavigation', fn ($groups): bool => $groups->count() === 5
                    && $groups->pluck('key')->all() === ['overview', 'content', 'communications', 'identity', 'system']
                    && ! $groups->contains(fn (array $group): bool => in_array($group['key'], ['operations', 'phase5'], true)))
                ->where('authority.is_platform_owner', true)
                ->where('authority.institutional_approval.granted', false));
    }

    public function test_ordinary_cms_user_cannot_open_hidden_modules_by_direct_url(): void
    {
        $editor = $this->userWith(['pillars.view']);

        $this->actingAs($editor)->get('/'.self::LOCALE.'/cms')->assertOk();
        $this->actingAs($editor)->get('/'.self::LOCALE.'/operations/dashboard')->assertForbidden();
        $this->actingAs($editor)->get('/'.self::LOCALE.'/operations/phase5/dashboard')->assertForbidden();
    }

    public function test_legacy_operational_permission_preserves_regression_route_access(): void
    {
        $operator = $this->userWith(['reports.view']);

        $this->actingAs($operator)->get('/'.self::LOCALE.'/operations/dashboard')->assertOk();
    }

    public function test_seeded_website_roles_are_definitions_only_and_grant_no_approval_authority(): void
    {
        Artisan::call('pcdr:permissions:sync');
        $this->seed(CmsRolesSeeder::class);
        $this->seed(EmailTemplatesSeeder::class);

        $expected = [
            'website_administrator', 'content_editor', 'publisher_reviewer',
            'contact_manager', 'contact_support',
        ];

        foreach ($expected as $key) {
            $this->assertDatabaseHas('roles', ['key' => $key]);
        }

        $this->assertSame(0, DB::table('role_user')->count());

        $reviewer = Role::query()->where('key', 'publisher_reviewer')->firstOrFail();
        $reviewerPermissions = $reviewer->permissions()->pluck('key')->all();

        $this->assertContains('pillars.view', $reviewerPermissions);
        $this->assertNotContains('pillars.publish', $reviewerPermissions);
        $this->assertNotContains('inquiries.reply', $reviewerPermissions);
        $this->assertEmpty(array_filter(
            $reviewerPermissions,
            fn (string $permission): bool => app(RbacService::class)->isAuthorityPermission($permission),
        ));

        $this->assertTrue(collect(array_keys((array) config('permissions', [])))
            ->every(fn (string $permission): bool => ! str_contains(strtolower($permission), 'approve')));
        $this->assertArrayNotHasKey('inquiries.update_status', (array) config('permissions', []));

        $this->assertSame(
            count((array) config('email.default_templates', [])),
            EmailTemplate::query()->count(),
        );
        $this->assertSame(
            count((array) config('email.default_templates', [])) * count((array) config('website.locales', [])),
            EmailTemplateTranslation::query()->count(),
        );
    }

    public function test_public_settings_catalog_is_limited_and_root_redirect_uses_default_language(): void
    {
        $editor = $this->bootstrapUser();

        $this->actingAs($editor)->get('/'.self::LOCALE.'/cms/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cms/Settings/Index')
                ->where('settings', fn ($settings): bool => $settings->count() === 22
                    && $settings->contains(fn (array $setting): bool => $setting['key'] === 'contact.complaints_email'
                        && $setting['group'] === 'contact'
                        && $setting['type'] === 'email')
                    && $settings->every(fn (array $setting): bool => ! str_starts_with($setting['key'], 'app.')
                        && ! str_starts_with($setting['key'], 'mail.')))
                ->where('locales', ['ar', 'en']));

        WebsiteSetting::query()->create([
            'group' => 'website',
            'key' => 'website.default_language',
            'type' => 'locale',
            'is_public' => true,
            'is_localized' => false,
            'value' => 'en',
        ]);
        app(SiteSettings::class)->flush();

        $this->get('/')->assertRedirect('/en');

        WebsiteSetting::query()->where('key', 'website.default_language')->update(['value' => 'ar']);
        app(SiteSettings::class)->flush();

        $this->get('/')->assertRedirect('/ar');
    }

    private function bootstrapUser(): User
    {
        return User::factory()->create([
            'name' => 'Scope Editor',
            'email' => self::BOOTSTRAP_EMAIL,
            'password' => Hash::make('password'),
        ]);
    }

    /** @param list<string> $keys */
    private function userWith(array $keys): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'key' => 'scope-limited-'.$user->id,
            'name' => 'Scope limited user',
        ]);

        foreach ($keys as $key) {
            $definition = (array) config('permissions.'.$key, []);
            $permission = Permission::query()->firstOrCreate(['key' => $key], [
                'name' => $definition['label'] ?? $key,
                'module' => $definition['module'] ?? 'content',
                'is_protected' => false,
            ]);
            $role->permissions()->attach($permission);
        }

        app(AccessManagementService::class)->syncRoles($user, [$role->getKey()]);

        return $user;
    }
}
