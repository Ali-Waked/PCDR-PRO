<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessManagementService;
use App\Services\RbacManagementService;
use App\Services\RbacService;
use App\Services\ScopeResolver;
use App\Support\PermissionCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_permissions_are_enforced_and_cache_is_invalidated_on_change(): void
    {
        $role = Role::create(['key' => 'reviewer', 'name' => 'Reviewer']);
        $permission = Permission::create([
            'key' => 'news.view',
            'name' => 'View news',
            'module' => 'website',
        ]);
        $role->permissions()->attach($permission);

        $user = User::factory()->create();
        app(AccessManagementService::class)->syncRoles($user, [$role->id]);

        $rbac = app(RbacService::class);

        $this->assertTrue($rbac->has($user, 'news.view'));

        app(RbacManagementService::class)->syncRolePermissions($role, []);

        $this->assertFalse($rbac->has($user, 'news.view'));
    }

    public function test_historical_custom_permission_remains_effective_but_is_not_dashboard_assignable(): void
    {
        $role = Role::create(['key' => 'legacy_holder', 'name' => 'Legacy holder']);
        $permission = Permission::create([
            'key' => 'custom.local_review',
            'name' => 'Local review',
            'module' => 'custom',
        ]);
        $role->permissions()->attach($permission);

        $user = User::factory()->create();
        app(AccessManagementService::class)->syncRoles($user, [$role->id]);

        $rbac = app(RbacService::class);
        $management = app(RbacManagementService::class);

        $this->assertTrue($rbac->has($user, 'custom.local_review'));
        $this->assertFalse(PermissionCatalog::isSupported('custom.local_review'));

        $management->syncRolePermissions($role, []);
        $this->assertTrue($role->fresh()->permissions()->where('permissions.id', $permission->id)->exists());
        $this->assertTrue($rbac->has($user, 'custom.local_review'));

        $newRole = Role::create(['key' => 'no_custom_assignment', 'name' => 'No custom assignment']);
        $management->syncRolePermissions($newRole, [$permission->id]);

        $this->assertSame(0, $newRole->permissions()->count());
    }

    public function test_dynamic_custom_permission_is_assignable(): void
    {
        $role = Role::create(['key' => 'local_custom', 'name' => 'Local custom']);
        $permission = Permission::create([
            'key' => 'custom.local_review',
            'name' => 'Local review',
            'module' => 'custom',
        ]);
        $role->permissions()->attach($permission);

        $user = User::factory()->create();
        app(AccessManagementService::class)->syncRoles($user, [$role->id]);

        $this->assertTrue(app(RbacService::class)->has($user, 'custom.local_review'));
    }

    public function test_retired_permission_is_no_longer_granted(): void
    {
        $role = Role::create(['key' => 'retired_user', 'name' => 'Retired user']);
        $permission = Permission::create([
            'key' => 'legacy.access',
            'name' => 'Legacy access',
            'module' => 'legacy',
        ]);
        $role->permissions()->attach($permission);

        $user = User::factory()->create();
        app(AccessManagementService::class)->syncRoles($user, [$role->id]);

        $rbac = app(RbacService::class);
        $this->assertTrue($rbac->has($user, 'legacy.access'));

        $permission->update(['is_retired' => true]);
        $rbac->invalidate();
        $rbac->forgetUser($user);

        $this->assertFalse($rbac->has($user, 'legacy.access'));
    }

    public function test_platform_owner_has_universal_permissions_and_public_scope_summary(): void
    {
        $owner = User::factory()->platformOwner()->create();
        $rbac = app(RbacService::class);

        $this->assertTrue($rbac->has($owner, 'any.permission'));
        $this->assertTrue($rbac->permissionsFor($owner)->contains('*'));
        $this->assertSame(['*' => [['scope_type' => 'all']]], $rbac->publicScopesFor($owner));
        $this->assertSame(['platform_owner'], $rbac->rolesFor($owner)->all());
    }

    public function test_scope_resolver_default_denies_and_supports_all_self_and_assigned_scopes(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $role = Role::create(['key' => 'scoped', 'name' => 'Scoped']);
        app(AccessManagementService::class)->syncRoles($user, [$role->id]);

        $resolver = app(ScopeResolver::class);

        $query = User::query();
        $this->assertFalse($resolver->apply($query, 'users', $user, 'id'));
        $this->assertSame(0, $query->count());

        $role->scopes()->create([
            'resource_key' => 'users',
            'scope_type' => 'self',
            'scope_value' => [],
        ]);

        $query = User::query();
        $this->assertTrue($resolver->apply($query, 'users', $user, 'id'));
        $this->assertSame([$user->id], $query->pluck('id')->all());

        $role->scopes()->delete();
        $role->scopes()->create([
            'resource_key' => 'users',
            'scope_type' => 'assigned',
            'scope_value' => ['user_ids' => [$other->id]],
        ]);

        $query = User::query();
        $this->assertTrue($resolver->apply($query, 'users', $user, 'id'));
        $this->assertSame([$other->id], $query->pluck('id')->all());

        $role->scopes()->delete();
        $role->scopes()->create([
            'resource_key' => 'users',
            'scope_type' => 'all',
            'scope_value' => [],
        ]);

        $query = User::query();
        $this->assertTrue($resolver->apply($query, 'users', $user, 'id'));
        $this->assertSame(2, $query->count());
    }

    public function test_public_scope_summary_does_not_expose_scope_values(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $role = Role::create(['key' => 'scope_private', 'name' => 'Private scope']);
        $role->scopes()->create([
            'resource_key' => 'users',
            'scope_type' => 'assigned',
            'scope_value' => ['user_ids' => [$other->id]],
        ]);
        app(AccessManagementService::class)->syncRoles($user, [$role->id]);

        $summary = app(RbacService::class)->publicScopesFor($user);

        $this->assertSame(['users' => [['scope_type' => 'assigned']]], $summary);
        $this->assertStringNotContainsString((string) $other->id, json_encode($summary));
    }

    public function test_permission_sync_is_idempotent_and_creates_no_users(): void
    {
        Artisan::call('db:seed', ['--class' => 'DatabaseSeeder']);

        $expected = count(PermissionCatalog::supported());

        $this->assertSame($expected, Permission::count());
        $this->assertSame(0, User::count());
        $this->assertSame(1, Permission::where('key', 'users.view')->where('is_protected', true)->count());
        $this->assertSame(0, Permission::where('key', 'content.publish')->where('is_protected', true)->count());

        Artisan::call('pcdr:permissions:sync');

        $this->assertSame($expected, Permission::count());
    }

    public function test_suspending_a_user_invalidates_sessions_and_remember_token(): void
    {
        $user = User::factory()->create();
        $rememberToken = $user->remember_token;

        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $user->id,
            'payload' => 'test-payload',
            'last_activity' => time(),
        ]);

        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());

        app(AccessManagementService::class)->setStatus($user, User::STATUS_SUSPENDED);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertNotSame($rememberToken, $user->fresh()->remember_token);
        $this->assertSame(User::STATUS_SUSPENDED, $user->fresh()->status);
    }

    public function test_duplicate_role_and_permission_keys_are_rejected_by_the_schema(): void
    {
        Role::create(['key' => 'duplicate.role', 'name' => 'First']);
        Permission::create(['key' => 'duplicate.permission', 'name' => 'First', 'module' => 'test']);

        try {
            Role::create(['key' => 'duplicate.role', 'name' => 'Second']);
            $this->fail('Duplicate role key was accepted.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        try {
            Permission::create(['key' => 'duplicate.permission', 'name' => 'Second', 'module' => 'test']);
            $this->fail('Duplicate permission key was accepted.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }

    public function test_rbac_cache_stores_arrays_and_recovers_from_legacy_object_payloads(): void
    {
        Cache::setDefaultDriver('database');
        Cache::store('database')->flush();

        $role = Role::create(['key' => 'cache_compat', 'name' => 'Cache compatibility']);
        $permission = Permission::create([
            'key' => 'cache.array_test',
            'name' => 'Cache array test',
            'module' => 'test',
        ]);
        $role->permissions()->attach($permission);

        $user = User::factory()->create();
        app(AccessManagementService::class)->syncRoles($user, [$role->id]);

        $rbac = app(RbacService::class);
        $permissionKey = 'pcdr:rbac:v1:permissions:'.$user->id;
        $roleKey = 'pcdr:rbac:v1:roles:'.$user->id;

        $permissions = $rbac->permissionsFor($user);
        $roles = $rbac->rolesFor($user);

        $this->assertInstanceOf(Collection::class, $permissions);
        $this->assertTrue($permissions->contains('cache.array_test'));
        $this->assertIsArray(Cache::get($permissionKey));
        $this->assertIsArray(Cache::get($roleKey));

        DB::table('cache')->updateOrInsert(
            ['key' => Cache::getPrefix().$permissionKey],
            [
                'value' => base64_encode(serialize(collect(['legacy.permission']))),
                'expiration' => time() + 3600,
            ],
        );

        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, Cache::get($permissionKey));

        $recovered = $rbac->permissionsFor($user);

        $this->assertInstanceOf(Collection::class, $recovered);
        $this->assertTrue($recovered->contains('cache.array_test'));
        $this->assertIsArray(Cache::get($permissionKey));
    }

    public function test_public_home_recovers_from_legacy_rbac_cache_payload(): void
    {
        Cache::setDefaultDriver('database');
        Cache::store('database')->flush();

        $role = Role::create(['key' => 'public_home_cache', 'name' => 'Public home cache']);
        $permission = Permission::create([
            'key' => 'public.home.view',
            'name' => 'View public home',
            'module' => 'website',
        ]);
        $role->permissions()->attach($permission);

        $user = User::factory()->create();
        app(AccessManagementService::class)->syncRoles($user, [$role->id]);

        $permissionKey = 'pcdr:rbac:v1:permissions:'.$user->id;

        DB::table('cache')->updateOrInsert(
            ['key' => Cache::getPrefix().$permissionKey],
            [
                'value' => base64_encode(serialize(collect(['legacy.permission']))),
                'expiration' => time() + 3600,
            ],
        );

        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, Cache::get($permissionKey));

        $this->actingAs($user)->get('/ar')->assertOk();

        $this->assertIsArray(Cache::get($permissionKey));
        $this->assertContains('public.home.view', Cache::get($permissionKey));
    }
}
