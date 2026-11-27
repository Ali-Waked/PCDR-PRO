<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessManagementService;
use App\Services\RbacService;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class UnifiedRolesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('pcdr:permissions:sync');
    }

    public function test_supported_catalog_is_an_explicit_code_owned_allowlist(): void
    {
        $supported = PermissionCatalog::supported();

        $this->assertCount(74, $supported);
        $this->assertSame('content', config('permissions')['inquiries.update']['module']);

        foreach (['users.view', 'roles.view', 'inquiries.update', 'news.publish'] as $key) {
            $this->assertTrue(PermissionCatalog::isSupported($key), $key);
        }

        foreach ([
            'directory_partners.view', 'directory_partners.create', 'directory_partners.update',
            'directory_partners.delete', 'directory_partners.publish', 'directory_partners.unpublish',
        ] as $key) {
            $this->assertTrue(PermissionCatalog::isSupported($key), $key);
        }

        foreach ([
            'homepage.view', 'homepage.update',
            'complaints.view_all', 'complaints.view_assigned', 'complaints.view_contact',
            'complaints.update', 'complaints.assign', 'complaints.add_internal_note', 'complaints.resolve',
        ] as $key) {
            $this->assertTrue(PermissionCatalog::isSupported($key), $key);
        }

        $this->assertFalse(PermissionCatalog::isSupported('partners.view'));

        foreach ([
            'permissions.create', 'permissions.update', 'permissions.delete',
            'roles.grant_protected', 'pages.view', 'content.review',
            'locations.view', 'workflows.create', 'dashboards.view',
            'custom.local_permission',
        ] as $key) {
            $this->assertFalse(PermissionCatalog::isSupported($key), $key);
        }

        $records = Permission::query()
            ->whereIn('key', PermissionCatalog::supportedKeys())
            ->get()
            ->keyBy('key')
            ->all();
        $groups = PermissionCatalog::groups($records);

        $this->assertSame(['identity_access', 'platform_administration', 'website'], array_column($groups, 'key'));

        foreach ($groups as $group) {
            $this->assertArrayHasKey('label', $group);
            $this->assertNotEmpty($group['items']);

            foreach ($group['items'] as $item) {
                $this->assertTrue(PermissionCatalog::isSupported($item['key']), $item['key']);
                $this->assertTrue($item['assignable'], $item['key']);
            }
        }

        $rbac = app(RbacService::class);

        foreach (PermissionCatalog::supportedKeys() as $key) {
            $this->assertFalse($rbac->isAuthorityPermission($key), $key);
        }
    }

    public function test_sync_creates_only_supported_rows_and_never_retires_historical_rows(): void
    {
        $legacy = Permission::create([
            'key' => 'legacy.access',
            'name' => 'Legacy access',
            'module' => 'legacy',
        ]);
        $retired = Permission::create([
            'key' => 'legacy.retired',
            'name' => 'Retired legacy access',
            'module' => 'legacy',
            'is_retired' => true,
        ]);

        Artisan::call('pcdr:permissions:sync');
        Artisan::call('pcdr:permissions:sync');

        $supportedKeys = PermissionCatalog::supportedKeys();

        $this->assertSame(count($supportedKeys), Permission::whereIn('key', $supportedKeys)->count());
        $this->assertDatabaseHas('permissions', ['key' => $legacy->key, 'is_retired' => false]);
        $this->assertDatabaseHas('permissions', ['key' => $retired->key, 'is_retired' => true]);
        $this->assertDatabaseMissing('permissions', ['key' => 'pages.view']);
        $this->assertDatabaseMissing('permissions', ['key' => 'permissions.create']);
        $this->assertSame('View users', Permission::where('key', 'users.view')->value('name'));
    }

    public function test_unified_page_exposes_roles_catalog_and_backend_derived_stats(): void
    {
        Artisan::call('db:seed', ['--class' => 'CmsRolesSeeder']);

        $owner = User::factory()->platformOwner()->create();

        $this->actingAs($owner)->get('/en/admin/access')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Access/Index')
                ->where('stats.total_roles', Role::count())
                ->where('stats.system_roles', Role::where('is_system', true)->count())
                ->where('stats.custom_roles', Role::where('is_system', false)->count())
                ->where('stats.available_permissions', count(PermissionCatalog::supportedKeys()))
                ->has('roles')
                ->where('catalog', function ($catalog): bool {
                    $keys = collect($catalog)->flatMap(fn ($group): array => $group['items'])
                        ->pluck('key')
                        ->all();

                    $this->assertSame(PermissionCatalog::supportedKeys(), $keys);

                    return true;
                })
                ->where('auth.permissions', function ($permissions): bool {
                    $this->assertContains('*', $permissions);
                    $this->assertNotContains('permissions.create', $permissions);
                    $this->assertNotContains('locations.view', $permissions);
                    $this->assertNotContains('institutional.approve', $permissions);

                    return true;
                }));
    }

    public function test_legacy_destinations_redirect_to_the_unified_page(): void
    {
        $owner = User::factory()->platformOwner()->create();
        $role = Role::create(['key' => 'legacy_link', 'name' => 'Legacy link']);

        $this->actingAs($owner)->get('/ar/admin/roles')
            ->assertRedirect('/ar/admin/access');

        $this->actingAs($owner)->get('/ar/admin/permissions')
            ->assertRedirect('/ar/admin/access');

        $this->actingAs($owner)->get("/ar/admin/roles/{$role->id}/edit")
            ->assertRedirect('/ar/admin/access?role='.$role->id.'&edit=1');
    }

    public function test_permission_crud_routes_are_not_available(): void
    {
        foreach ([
            'admin.permissions.create',
            'admin.permissions.store',
            'admin.permissions.edit',
            'admin.permissions.update',
            'admin.permissions.destroy',
        ] as $route) {
            $this->assertFalse(Route::has($route), $route);
        }

        $this->assertTrue(Route::has('admin.access.index'));
        $this->assertTrue(Route::has('admin.permissions.index'));

        $owner = User::factory()->platformOwner()->create();

        $this->actingAs($owner)->get('/ar/admin/permissions/create')->assertNotFound();
        $this->actingAs($owner)->post('/ar/admin/permissions', [
            'key' => 'custom.dashboard_permission',
            'name' => 'Custom dashboard permission',
            'module' => 'custom',
        ])->assertNotFound();
        $this->actingAs($owner)->put('/ar/admin/permissions/1', [
            'key' => 'users.view.changed',
            'name' => 'Changed',
            'module' => 'iam',
        ])->assertNotFound();
        $this->actingAs($owner)->delete('/ar/admin/permissions/1')->assertNotFound();
    }

    public function test_role_crud_accepts_only_supported_permissions(): void
    {
        $owner = User::factory()->platformOwner()->create();
        $newsId = (int) Permission::where('key', 'news.view')->value('id');
        $programId = (int) Permission::where('key', 'programs.view')->value('id');
        $custom = Permission::create([
            'key' => 'custom.dashboard_only',
            'name' => 'Dashboard-only custom permission',
            'module' => 'custom',
        ]);

        $this->actingAs($owner)->post('/en/admin/roles', [
            'key' => 'field_editor',
            'name' => 'Field editor',
            'description' => 'Edits authorized website content.',
            'permissions' => [$newsId, $custom->id],
        ])->assertSessionHasErrors('permissions.1');

        $this->assertDatabaseMissing('roles', ['key' => 'field_editor']);

        $this->actingAs($owner)->post('/en/admin/roles', [
            'key' => 'field_editor',
            'name' => 'Field editor',
            'description' => 'Edits authorized website content.',
            'permissions' => [$newsId],
        ])->assertRedirect('/en/admin/access');

        $role = Role::where('key', 'field_editor')->firstOrFail();

        $this->assertSame([$newsId], $role->permissions()->pluck('permissions.id')->all());

        $this->actingAs($owner)->put("/en/admin/roles/{$role->id}", [
            'key' => 'field_editor',
            'name' => 'Field editor updated',
            'description' => 'Updated description.',
            'permissions' => [$programId],
        ])->assertRedirect();

        $this->assertSame([$programId], $role->permissions()->pluck('permissions.id')->all());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'role.permissions_changed',
            'subject_type' => 'roles',
            'subject_id' => (string) $role->id,
        ]);

        $this->actingAs($owner)->delete("/en/admin/roles/{$role->id}")
            ->assertRedirect('/en/admin/access');

        $this->assertSoftDeleted('roles', ['id' => $role->id]);
    }

    public function test_system_roles_cannot_be_deleted_from_the_unified_flow(): void
    {
        $owner = User::factory()->platformOwner()->create();
        $role = Role::create([
            'key' => 'protected_system_role',
            'name' => 'Protected system role',
            'is_system' => true,
        ]);

        $this->actingAs($owner)->delete("/en/admin/roles/{$role->id}")
            ->assertStatus(422);

        $this->assertNotSoftDeleted('roles', ['id' => $role->id]);
    }

    public function test_non_owner_cannot_escalate_through_unified_role_endpoints(): void
    {
        $editor = $this->userWithPermissions(['roles.create', 'roles.update']);
        $protected = Permission::where('key', 'users.view')->firstOrFail();
        $custom = Permission::create([
            'key' => 'custom.escalation',
            'name' => 'Custom escalation',
            'module' => 'custom',
        ]);

        $this->actingAs($editor)->post('/en/admin/roles', [
            'key' => 'escalated_role',
            'name' => 'Escalated role',
            'permissions' => [$protected->id],
        ])->assertForbidden();

        $this->assertDatabaseMissing('roles', ['key' => 'escalated_role']);

        $target = Role::create(['key' => 'sync_target', 'name' => 'Sync target']);

        $this->actingAs($editor)->put("/en/admin/roles/{$target->id}/permissions", [
            'permissions' => [$custom->id],
        ])->assertSessionHasErrors('permissions.0');

        $this->assertSame(0, $target->permissions()->count());
    }

    public function test_platform_owner_has_technical_control_but_no_institutional_approval_authority(): void
    {
        $owner = User::factory()->platformOwner()->create();
        $rbac = app(RbacService::class);

        $this->assertTrue(Gate::forUser($owner)->allows('access.view'));
        $this->assertTrue(Gate::forUser($owner)->allows('roles.create'));
        $this->assertTrue(Gate::forUser($owner)->allows('roles.update'));
        $this->assertTrue($rbac->has($owner, 'news.publish'));

        foreach (['institutional.approve', 'content.approve', 'governance.signoff'] as $ability) {
            $this->assertTrue($rbac->isAuthorityPermission($ability), $ability);
            $this->assertFalse($rbac->has($owner, $ability), $ability);
            $this->assertFalse(Gate::forUser($owner)->allows($ability), $ability);
        }

        $dashboardPermissions = $rbac->dashboardPermissionsFor($owner)->all();

        $this->assertContains('*', $dashboardPermissions);
        $this->assertNotContains('institutional.approve', $dashboardPermissions);
    }

    public function test_frontend_source_contract_keeps_one_unified_page_and_a_read_only_catalog(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/Pages/Admin/Access/Index.vue'));

        foreach (['roles', 'catalog', 'stats'] as $prop) {
            $this->assertStringContainsString($prop, $source);
        }

        foreach ([
            'data-testid="permission-catalog"',
            'data-testid="permission-catalog-item"',
            'data-testid="role-drawer"',
            'pc-access-tabs',
            '@media (max-width: 820px)',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $source);
        }

        foreach ([
            'permissions.create',
            'permissions.store',
            'Permissions/Index',
            'Roles/Edit',
            'admin/permissions',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, $source);
        }

        /** @var array{groups: list<array{items: list<array{path: string}>}>} $navigation */
        $navigation = require config_path('admin_navigation.php');
        $paths = collect($navigation['groups'])
            ->flatMap(fn (array $group): array => $group['items'])
            ->pluck('path')
            ->all();

        $this->assertSame(1, count(array_keys($paths, '/admin/access', true)));
        $this->assertNotContains('/admin/roles', $paths);
        $this->assertNotContains('/admin/permissions', $paths);
    }

    /** @param list<string> $keys */
    private function userWithPermissions(array $keys): User
    {
        $role = Role::create([
            'key' => 'granter_'.Str::lower(Str::random(10)),
            'name' => 'Test granter',
        ]);

        foreach ($keys as $key) {
            $permission = Permission::where('key', $key)->firstOrFail();
            $role->permissions()->attach($permission);
        }

        $user = User::factory()->create();
        app(AccessManagementService::class)->syncRoles($user, [$role->id]);

        return $user;
    }
}
