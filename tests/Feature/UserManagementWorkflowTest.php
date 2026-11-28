<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessManagementService;
use App\Services\RbacService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class UserManagementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> */
    public static function localeProvider(): array
    {
        return [
            'arabic' => ['ar', 'rtl'],
            'english' => ['en', 'ltr'],
        ];
    }

    private function owner(): User
    {
        return User::factory()->platformOwner()->create();
    }

    /** @param array<string, mixed> $overrides */
    private function creationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Managed User',
            'email' => 'managed@example.test',
            'password' => 'managed-password-123',
            'status' => 'active',
            'locale' => 'ar',
        ], $overrides);
    }

    public function test_admin_created_user_is_verified_with_hashed_password_and_assigned_roles(): void
    {
        $owner = $this->owner();
        $role = Role::create(['key' => 'operator', 'name' => 'Operator']);

        $this->actingAs($owner)->post('/ar/admin/users', $this->creationPayload([
            'email' => '  Managed@Example.Test  ',
            'roles' => [$role->id],
        ]))->assertRedirect('/ar/admin/users')
            ->assertSessionHas('success', __('ui.user_created'));

        $user = User::where('email', 'managed@example.test')->firstOrFail();

        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('managed-password-123', (string) $user->password));
        $this->assertTrue($user->roles()->where('roles.id', $role->id)->exists());
        $this->assertSame(User::STATUS_ACTIVE, $user->status);
        $this->assertSame('ar', $user->locale);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.created',
            'subject_id' => (string) $user->id,
            'actor_id' => $owner->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.roles_changed',
            'subject_id' => (string) $user->id,
        ]);
    }

    public function test_creation_rejects_missing_duplicate_weak_and_unknown_role_payloads(): void
    {
        $owner = $this->owner();
        User::factory()->create(['email' => 'existing@example.test']);

        $this->actingAs($owner)->from('/ar/admin/users/create')->post('/ar/admin/users',
            $this->creationPayload(['name' => ''])
        )->assertSessionHasErrors('name');

        $this->actingAs($owner)->from('/ar/admin/users/create')->post('/ar/admin/users',
            $this->creationPayload(['email' => 'EXISTING@example.test'])
        )->assertSessionHasErrors('email');

        $this->actingAs($owner)->from('/ar/admin/users/create')->post('/ar/admin/users',
            $this->creationPayload(['email' => 'weak@example.test', 'password' => 'short'])
        )->assertSessionHasErrors('password');

        $this->actingAs($owner)->from('/ar/admin/users/create')->post('/ar/admin/users',
            $this->creationPayload(['email' => 'badrole@example.test', 'roles' => [999999]])
        )->assertSessionHasErrors('roles.0');

        $this->assertSame(1, User::where('email', 'existing@example.test')->count());
        $this->assertDatabaseMissing('users', ['email' => 'weak@example.test']);
        $this->assertDatabaseMissing('users', ['email' => 'badrole@example.test']);
    }

    public function test_creation_requires_create_permission_and_separate_role_assignment_permission(): void
    {
        $plain = User::factory()->create();

        $this->actingAs($plain)->post('/ar/admin/users', $this->creationPayload([
            'email' => 'denied@example.test',
        ]))->assertForbidden();

        $createRole = Role::create(['key' => 'creator', 'name' => 'Creator']);
        $permission = Permission::create([
            'key' => 'users.create',
            'name' => 'Create users',
            'module' => 'iam',
            'is_protected' => true,
        ]);
        $createRole->permissions()->attach($permission);

        $creator = User::factory()->create();
        app(AccessManagementService::class)->syncRoles($creator, [$createRole->id]);
        $assignRole = Role::create(['key' => 'assignable', 'name' => 'Assignable']);

        $this->actingAs($creator)->post('/ar/admin/users', $this->creationPayload([
            'email' => 'noassign@example.test',
            'roles' => [$assignRole->id],
        ]))->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'denied@example.test']);
        $this->assertDatabaseMissing('users', ['email' => 'noassign@example.test']);
    }

    public function test_role_assignment_failure_rolls_back_user_creation(): void
    {
        $owner = $this->owner();
        $role = Role::create(['key' => 'operator', 'name' => 'Operator']);
        DB::listen(function ($query): void {
            if (str_contains($query->sql, 'role_user') && str_starts_with(ltrim($query->sql), 'insert')) {
                throw new RuntimeException('forced role sync failure');
            }
        });

        $this->actingAs($owner)->from('/ar/admin/users/create')->post('/ar/admin/users', $this->creationPayload([
            'roles' => [$role->id],
        ]))->assertRedirect('/ar/admin/users/create')
            ->assertSessionHas('flash.error', 'forced role sync failure');

        $this->assertDatabaseMissing('users', ['email' => 'managed@example.test']);
        $this->assertDatabaseMissing('role_user', []);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'user.created']);
    }

    #[DataProvider('localeProvider')]
    public function test_newly_created_user_can_log_in_and_access_protected_pages_in_both_locales(string $locale, string $direction): void
    {
        $owner = $this->owner();
        $role = Role::create(['key' => 'operator', 'name' => 'Operator']);

        $this->actingAs($owner)->post('/'.$locale.'/admin/users', $this->creationPayload([
            'email' => 'session@example.test',
            'locale' => $locale,
            'roles' => [$role->id],
        ]))->assertRedirect('/'.$locale.'/admin/users');

        $user = User::where('email', 'session@example.test')->firstOrFail();

        $this->post('/'.$locale.'/logout')->assertRedirect('/'.$locale.'/login');
        $this->assertGuest();

        $this->post('/'.$locale.'/login', [
            'email' => '  Session@Example.Test  ',
            'password' => 'managed-password-123',
        ])->assertRedirect('/'.$locale.'/dashboard');

        $this->assertAuthenticatedAs($user);

        $this->get('/'.$locale.'/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard'));
        $this->get('/'.$locale.'/dashboard')->assertOk();
        $this->get('/'.$locale.'/dashboard/profile')->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->assertSame($direction, app()->getLocale() === 'ar' ? 'rtl' : 'ltr');
    }

    public function test_last_login_tracks_only_real_success_and_preserves_previous_login(): void
    {
        $previous = now()->subDays(3)->startOfSecond();
        $last = now()->subDay()->startOfSecond();
        $user = User::factory()->create([
            'email' => 'history@example.test',
            'email_verified_at' => now(),
            'password' => 'correct-password-123',
            'previous_login_at' => $previous,
            'last_login_at' => $last,
            'failed_login_attempts' => 2,
        ]);

        $this->from('/ar/login')->post('/ar/login', [
            'email' => $user->email,
            'password' => 'wrong-password-123',
        ])->assertSessionHasErrors('email');

        $this->assertSame(3, $user->fresh()->failed_login_attempts);
        $this->assertTrue($user->fresh()->last_login_at->equalTo($last));
        $this->assertSame(0, AuditLog::where('action', 'auth.login')->count());

        $this->post('/ar/login', [
            'email' => $user->email,
            'password' => 'correct-password-123',
        ])->assertRedirect('/ar/dashboard');

        $fresh = $user->fresh();

        $this->assertTrue($fresh->previous_login_at->equalTo($last));
        $this->assertTrue($fresh->last_login_at->greaterThan($last));
        $this->assertSame(0, $fresh->failed_login_attempts);
        $this->assertNull($fresh->locked_until);
        $this->assertSame(1, AuditLog::where('action', 'auth.login')->count());

        $this->post('/ar/logout')->assertRedirect('/ar/login');
        $this->assertGuest();
        $this->assertSame(1, AuditLog::where('action', 'auth.logout')->count());
    }

    public function test_unverified_and_inactive_users_do_not_receive_last_login_updates(): void
    {
        $unverified = User::factory()->unverified()->create([
            'email' => 'unverified@example.test',
            'password' => 'correct-password-123',
        ]);
        $inactive = User::factory()->inactive()->create([
            'email' => 'inactive@example.test',
            'password' => 'correct-password-123',
        ]);

        $this->post('/ar/login', [
            'email' => $unverified->email,
            'password' => 'correct-password-123',
        ])->assertRedirect('/ar/verify-email');

        $this->from('/ar/login')->post('/ar/login', [
            'email' => $inactive->email,
            'password' => 'correct-password-123',
        ])->assertRedirect('/ar/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull($unverified->fresh()->last_login_at);
        $this->assertNull($inactive->fresh()->last_login_at);
        $this->assertSame(0, AuditLog::where('action', 'auth.login')->count());
    }

    public function test_guests_are_redirected_and_logout_invalidates_protected_access(): void
    {
        $this->get('/ar/dashboard')->assertRedirect('/ar/login');

        $user = User::factory()->create(['email' => 'guard@example.test']);

        $this->post('/ar/login', [
            'email' => $user->email,
            'password' => 'password12345',
        ])->assertRedirect('/ar/dashboard');

        $this->get('/ar/dashboard')->assertOk();
        $this->post('/ar/logout')->assertRedirect('/ar/login');
        $this->get('/ar/dashboard')->assertRedirect('/ar/login');
        $this->assertGuest();
    }

    public function test_roles_permissions_and_institutional_authority_boundary_are_preserved(): void
    {
        $role = Role::create(['key' => 'staff', 'name' => 'Staff']);
        $viewPermission = Permission::create([
            'key' => 'users.view',
            'name' => 'View users',
            'module' => 'iam',
            'is_protected' => true,
        ]);
        $authorityPermission = Permission::create([
            'key' => 'institutional.approve',
            'name' => 'Institutional approval',
            'module' => 'general',
            'is_protected' => false,
        ]);
        $role->permissions()->attach([$viewPermission->id, $authorityPermission->id]);

        $user = User::factory()->create();
        app(AccessManagementService::class)->syncRoles($user, [$role->id]);

        $rbac = app(RbacService::class);

        $this->assertTrue($rbac->has($user, 'users.view'));
        $this->assertFalse($rbac->has($user, 'institutional.approve'));
        $this->assertNotContains('institutional.approve', $rbac->dashboardPermissionsFor($user)->all());
        $this->assertFalse($rbac->has($this->owner(), 'institutional.approve'));
    }

    public function test_user_details_page_returns_accurate_roles_and_capability_flags(): void
    {
        $owner = $this->owner();
        $role = Role::create(['key' => 'operator', 'name' => 'Operator']);

        $this->actingAs($owner)->post('/ar/admin/users', $this->creationPayload([
            'email' => 'details@example.test',
            'roles' => [$role->id],
        ]))->assertRedirect('/ar/admin/users');

        $user = User::where('email', 'details@example.test')->firstOrFail();

        $this->actingAs($owner)->get('/ar/admin/users/'.$user->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Show')
            ->where('target.id', $user->id)
            ->where('target.email', 'details@example.test')
            ->where('target.roles.0.key', 'operator')
            ->where('isSelf', false)
            ->where('can.update', true)
            ->where('can.assignRoles', true));

        $this->actingAs($owner)->get('/ar/admin/users/'.$owner->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Show')
            ->where('isSelf', true)
            ->where('isProtected', true));
    }

    public function test_advisory_copy_is_exact_bilingual_and_translation_backed(): void
    {
        $english = (string) __('ui.users_page.technical_note', [], 'en');
        $arabic = (string) __('ui.users_page.technical_note', [], 'ar');

        $this->assertSame(
            'Technical access only. Assigning roles does not grant institutional approval authority.',
            $english,
        );
        $this->assertSame('وصول تقني فقط. إسناد الأدوار لا يمنح سلطة الموافقة المؤسسية.', $arabic);

        $source = (string) file_get_contents(base_path('resources/js/Pages/Admin/Users/Create.vue'));

        $this->assertStringContainsString("t('users_page.technical_note')", $source);
        $this->assertStringContainsString('pc-advisory', $source);
        $this->assertStringNotContainsString('role="alert"', $source);
    }

    public function test_auth_pages_do_not_mount_admin_toast_events_and_success_requires_a_flash(): void
    {
        $layout = (string) file_get_contents(base_path('resources/js/Layouts/AuthLayout.vue'));
        $notifications = (string) file_get_contents(base_path('resources/js/composables/useAdminNotifications.js'));

        $this->assertStringNotContainsString('AdminToastProvider', $layout);
        $this->assertStringNotContainsString("notify.action('updated')", $notifications);
        $this->assertStringContainsString('readFlash(flash, visitId', $notifications);
    }

    public function test_search_inputs_share_logical_icon_padding_in_both_directions(): void
    {
        $css = (string) file_get_contents(base_path('resources/css/dashboard.css'));

        $this->assertMatchesRegularExpression(
            '/\.pc-search input\s*\{[^}]*padding-inline:\s*38px 12px/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.pc-nav-find input\s*\{[^}]*padding-inline:\s*38px 12px/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.pc-content-filters__search input\s*\{[^}]*padding-inline:\s*36px 12px/s',
            $css,
        );
        $this->assertStringNotContainsString("[dir='rtl'] .pc-search input", $css);
        $this->assertStringNotContainsString("[dir='rtl'] .pc-nav-find input", $css);
        $this->assertStringNotContainsString("[dir='rtl'] .pc-content-filters__search input", $css);

        $access = (string) file_get_contents(base_path('resources/js/Pages/Admin/Access/Index.vue'));

        $this->assertStringContainsString('pc-search pc-access-search-field', $access);
        $this->assertStringNotContainsString('<label class="pc-access-search"', $access);
        $this->assertStringNotContainsString('class="pc-access-search pc-access-search--full"', $access);
    }

    public function test_user_pages_use_contained_table_scrolling_and_mobile_wrapping(): void
    {
        $table = (string) file_get_contents(base_path('resources/js/Components/Dashboard/DataTable.vue'));
        $css = (string) file_get_contents(base_path('resources/css/dashboard.css'));

        $mobileStart = strpos($css, "@media (max-width: 560px) {\n    .pc-topbar__context span,");
        $this->assertIsInt($mobileStart);
        $nextMedia = strpos($css, '@media', $mobileStart + 6);
        $mobileCss = $nextMedia === false
            ? substr($css, $mobileStart)
            : substr($css, $mobileStart, $nextMedia - $mobileStart);

        $this->assertStringContainsString('class="pc-table-wrap"', $table);
        $this->assertMatchesRegularExpression('/\.pc-table-wrap\s*\{[^}]*overflow-x:\s*auto/s', $css);
        $this->assertMatchesRegularExpression('/\.pc-app\s*\{[^}]*overflow-x:\s*clip/s', $css);
        $this->assertStringContainsString('.pc-page-actions {', $mobileCss);
        $this->assertStringContainsString('inline-size: 100%;', $mobileCss);
        $this->assertStringContainsString('.pc-table__actions {', $mobileCss);
        $this->assertStringContainsString('flex-wrap: wrap;', $mobileCss);
    }

    public function test_index_lists_existing_users_with_accurate_inertia_props(): void
    {
        $owner = $this->owner();
        User::factory()->create(['name' => 'Indexed First', 'email' => 'indexed-first@example.test']);
        User::factory()->create(['name' => 'Indexed Second', 'email' => 'indexed-second@example.test']);

        $this->actingAs($owner)->get('/ar/admin/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Admin/Users/Index')
                ->where('users.total', 3)
                ->where('users.per_page', 15)
                ->where('users.data', fn ($rows): bool => count($rows) === 3
                    && collect($rows)->pluck('name')->contains('Indexed First')
                    && collect($rows)->pluck('name')->contains('Indexed Second'))
                ->where('filters', fn ($filters): bool => $filters['search'] === ''
                    && $filters['status'] === ''
                    && $filters['role'] === ''
                    && $filters['sort'] === 'created_at'
                    && $filters['direction'] === 'desc')
                ->where('counts.total', 3)
                ->where('counts.active', 3)
                ->where('statusOptions', fn ($options): bool => count($options) === 3));

        $table = (string) file_get_contents(base_path('resources/js/Components/Dashboard/DataTable.vue'));
        $source = (string) file_get_contents(base_path('resources/js/Pages/Admin/Users/Index.vue'));

        $this->assertStringContainsString('v-if="empty && $slots.empty"', $table);
        $this->assertStringContainsString(':empty="!resultCount"', $source);
        $this->assertStringContainsString('emptyState.title', $source);
    }

    public function test_index_search_filters_sort_and_paginate_without_losing_records(): void
    {
        $owner = $this->owner();
        $role = Role::create(['key' => 'operator', 'name' => 'Operator']);

        for ($number = 1; $number <= 18; $number++) {
            $status = $number % 3 === 0 ? User::STATUS_SUSPENDED
                : ($number % 2 === 0 ? User::STATUS_INACTIVE : User::STATUS_ACTIVE);

            $user = User::factory()->create([
                'name' => sprintf('Indexed %02d', $number),
                'email' => sprintf('indexed%02d@example.test', $number),
                'status' => $status,
                'created_at' => now()->subDays(30 - $number),
            ]);

            if ($number <= 3) {
                $user->roles()->attach($role);
            }
        }

        $this->actingAs($owner)->get('/ar/admin/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('users.total', 19)
                ->where('users.current_page', 1)
                ->where('users.last_page', 2)
                ->where('users.data', fn ($rows): bool => count($rows) === 15));

        $this->actingAs($owner)->get('/ar/admin/users?page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('users.total', 19)
                ->where('users.current_page', 2)
                ->where('users.data', fn ($rows): bool => count($rows) === 4));

        $this->actingAs($owner)->get('/ar/admin/users?search=Indexed%2018')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('users.total', 1)
                ->where('filters.search', 'Indexed 18')
                ->where('users.data.0.email', 'indexed18@example.test'));

        $this->actingAs($owner)->get('/ar/admin/users?search=indexed07@example.test')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('users.total', 1)
                ->where('users.data.0.name', 'Indexed 07'));

        $this->actingAs($owner)->get('/ar/admin/users?status=suspended')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('users.total', 6)
                ->where('filters.status', 'suspended'));

        $this->actingAs($owner)->get('/ar/admin/users?role=operator')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('users.total', 3)
                ->where('filters.role', 'operator'));

        $this->actingAs($owner)->get('/ar/admin/users?search=Indexed&sort=name&direction=asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('users.total', 18)
                ->where('users.data.0.name', 'Indexed 01')
                ->where('filters.sort', 'name')
                ->where('filters.direction', 'asc'));

        $this->actingAs($owner)->get('/ar/admin/users?search=Indexed&sort=name&direction=desc')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('users.data.0.name', 'Indexed 18'));
    }

    public function test_index_empty_states_distinguish_no_matches_filters_and_invalid_pages(): void
    {
        $owner = $this->owner();
        $role = Role::create(['key' => 'operator', 'name' => 'Operator']);

        foreach (['Alpha One', 'Alpha Two', 'Beta Three'] as $name) {
            $user = User::factory()->create(['name' => $name, 'email' => Str::slug($name).'@example.test']);
            $user->roles()->attach($role);
        }

        $this->actingAs($owner)->get('/ar/admin/users?search=does-not-exist')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('users.total', 0)
                ->where('users.data', []));

        $this->actingAs($owner)->get('/ar/admin/users?role=operator&search=does-not-exist')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('users.total', 0)
                ->where('filters.role', 'operator'));

        $this->actingAs($owner)->get('/ar/admin/users?role=operator&status=suspended')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('users.total', 0)
                ->where('filters.status', 'suspended')
                ->where('filters.role', 'operator'));

        $this->actingAs($owner)->get('/ar/admin/users?page=9')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('users.total', 4)
                ->where('users.current_page', 9)
                ->where('users.data', []));

        $this->actingAs($owner)->get('/ar/admin/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('users.total', 4)
                ->where('users.data', fn ($rows): bool => count($rows) === 4));

        foreach ([
            'no_users_title',
            'search_no_results',
            'filter_no_results',
            'invalid_page',
            'reset_filters',
        ] as $key) {
            $this->assertNotSame('', (string) __("ui.users_page.{$key}", [], 'ar'));
            $this->assertNotSame('', (string) __("ui.users_page.{$key}", [], 'en'));
        }
    }

    public function test_index_excludes_soft_deleted_users_and_preserves_authorization(): void
    {
        $owner = $this->owner();
        $removed = User::factory()->create(['name' => 'Removed Account', 'email' => 'removed@example.test']);
        $expired = User::factory()->create(['name' => 'Expired Role', 'email' => 'expired@example.test']);
        $role = Role::create(['key' => 'temporary', 'name' => 'Temporary']);
        $expired->roles()->attach($role, ['expires_at' => now()->subDay()]);
        $removed->delete();

        $this->actingAs($owner)->get('/ar/admin/users?search=Removed%20Account')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('users.total', 0)
                ->where('users.data', []));

        $this->actingAs($owner)->get('/ar/admin/users?search=Expired%20Role')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('users.total', 1)
                ->where('users.data.0.roles', []));

        $this->assertDatabaseHas('users', ['id' => $removed->id]);
        $this->assertNotNull($removed->fresh()->deleted_at);

        $this->actingAs(User::factory()->create())->get('/ar/admin/users')->assertForbidden();
    }
}
