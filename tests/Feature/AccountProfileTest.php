<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', '12345678901234567890123456789012');

        $this->withoutVite();
    }

    public function test_guest_is_redirected_to_the_localized_login_page(): void
    {
        $this->get('/ar/dashboard/profile')
            ->assertRedirect(route('login.show', ['locale' => 'ar']));

        $this->get('/en/dashboard/profile')
            ->assertRedirect(route('login.show', ['locale' => 'en']));
    }

    public function test_profile_page_exposes_localized_props_without_secrets(): void
    {
        $reference = bin2hex(random_bytes(16));
        $rawSessionId = 'RAWSESSIONIDMARKER1234567890';

        $user = User::factory()->create([
            'name' => 'Profile User',
            'email' => 'profile@example.test',
            'locale' => 'ar',
            'password' => 'secret-marker-password-123',
            'remember_token' => 'REMEMBERTOKENMARKER',
            'last_login_at' => now()->subHours(2),
        ]);

        $this->seedSession($user, $reference, $rawSessionId);
        $this->seedSession($user, bin2hex(random_bytes(16)), bin2hex(random_bytes(20)));

        $response = $this->actingAs($user)
            ->withSession(['account.session_reference' => $reference])
            ->get('/ar/dashboard/profile')
            ->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Account/Profile/Show')
            ->where('profile.id', $user->id)
            ->where('profile.name', 'Profile User')
            ->where('profile.email', 'profile@example.test')
            ->where('profile.locale', 'ar')
            ->where('profile.status', User::STATUS_ACTIVE)
            ->where('profile.email_verified', true)
            ->where('profile.email_verified_at', fn ($value): bool => is_string($value) && $value !== '')
            ->where('profile.created_at', fn ($value): bool => is_string($value) && $value !== '')
            ->where('profile.last_login_at', fn ($value): bool => is_string($value) && $value !== '')
            ->where('profile.last_activity_at', fn ($value): bool => is_string($value) && $value !== '')
            ->where('profile.avatar_url', null)
            ->where('profile.roles', fn ($value): bool => is_array($value) || $value instanceof Arrayable)
            ->where('profile.is_platform_owner', false)
            ->missing('profile.password')
            ->missing('profile.remember_token')
            ->missing('profile.token')
            ->missing('tokens')
            ->where('pendingEmail', null)
            ->where('sessions.total', 2)
            ->where('sessions.current_reference', $reference)
            ->where('sessions.data', fn ($value): bool => collect($value)->count() === 2
                && collect($value)->contains(fn ($session): bool => ($session['reference'] ?? null) === $reference
                    && ($session['is_current'] ?? false) === true)
                && collect($value)->every(fn ($session): bool => ! array_key_exists('payload', (array) $session)
                    && ! array_key_exists('user_agent', (array) $session)
                    && ! array_key_exists('ip_address', (array) $session)))
            ->where('activity', fn ($value): bool => $this->asArray($value)['total'] === 0
                && $this->asArray($value)['data'] === []
                && array_key_exists('links', $this->asArray($value))
                && array_key_exists('from', $this->asArray($value))
                && array_key_exists('to', $this->asArray($value)))
            ->where('passwordPolicy.min_length', 12));

        $payload = $this->pagePayload($response);
        $this->assertStringNotContainsString($rawSessionId, $payload);
        $this->assertStringNotContainsString('REMEMBERTOKENMARKER', $payload);
        $this->assertStringNotContainsString((string) $user->password, $payload);

        $englishUser = User::factory()->create(['locale' => 'en']);

        $this->actingAs($englishUser)->get('/en/dashboard/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Profile/Show')
                ->where('profile.locale', 'en'));
    }

    public function test_profile_theme_is_exposed_persisted_and_audited(): void
    {
        $user = User::factory()->create([
            'name' => 'Theme Profile User',
            'locale' => 'en',
            'theme' => User::THEME_LIGHT,
        ]);

        $this->actingAs($user)->get('/en/dashboard/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('theme', User::THEME_LIGHT)
                ->where('auth.user.theme', User::THEME_LIGHT)
                ->where('profile.theme', User::THEME_LIGHT)
            );

        $this->actingAs($user)->put('/en/dashboard/profile', [
            'theme' => User::THEME_DARK,
        ])->assertRedirect();

        $this->assertSame(User::THEME_DARK, $user->fresh()->theme);

        $audit = AuditLog::query()->where('action', 'account.profile.updated')->latest('id')->firstOrFail();
        $this->assertSame(User::THEME_LIGHT, $audit->before['theme'] ?? null);
        $this->assertSame(User::THEME_DARK, $audit->after['theme'] ?? null);

        $this->actingAs($user)->get('/en/dashboard/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('theme', User::THEME_DARK)
                ->where('profile.theme', User::THEME_DARK)
            );
    }

    public function test_profile_update_changes_name_and_locale_and_writes_audit(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'locale' => 'ar']);

        $this->actingAs($user)->put('/ar/dashboard/profile', [
            'name' => 'New Localized Name',
            'locale' => 'en',
        ])->assertRedirect();

        $fresh = $user->fresh();
        $this->assertSame('New Localized Name', $fresh->name);
        $this->assertSame('en', $fresh->locale);

        $audit = AuditLog::query()->where('action', 'account.profile.updated')->latest('id')->firstOrFail();
        $this->assertSame($user->id, (int) $audit->actor_id);
        $this->assertSame((string) $user->id, (string) $audit->subject_id);
    }

    public function test_profile_update_cannot_change_protected_fields(): void
    {
        $role = Role::query()->create(['key' => 'protected-profile-role', 'name' => 'Protected profile role']);

        $user = User::factory()->platformOwner()->create([
            'email' => 'owner@example.test',
            'status' => User::STATUS_ACTIVE,
            'locale' => 'ar',
            'remember_token' => 'REMEMBERTOKENMARKER',
        ]);
        $user->roles()->attach($role);

        $original = [
            'email' => $user->email,
            'status' => $user->status,
            'locale' => $user->locale,
            'remember_token' => $user->remember_token,
            'is_platform_owner' => $user->isPlatformOwner(),
            'roles' => $user->roles()->pluck('roles.id')->all(),
        ];

        $this->actingAs($user)->put('/ar/dashboard/profile', [
            'name' => 'Legit New Name',
            'email' => 'attacker@example.test',
            'status' => User::STATUS_SUSPENDED,
            'locale' => 'en',
            'roles' => [999999],
            'permissions' => ['users.update'],
            'role' => 'platform-owner',
            'user_id' => 999999,
            'remember_token' => 'OVERWRITTEN12345',
            'is_platform_owner' => false,
        ])->assertRedirect();

        $fresh = $user->fresh();
        $this->assertSame('Legit New Name', $fresh->name);
        $this->assertSame($original['email'], $fresh->email);
        $this->assertSame($original['status'], $fresh->status);
        $this->assertSame('en', $fresh->locale);
        $this->assertSame($original['remember_token'], $fresh->remember_token);
        $this->assertTrue($fresh->isPlatformOwner());
        $this->assertSame($original['roles'], $fresh->roles()->pluck('roles.id')->all());
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.test']);
    }

    public function test_profile_update_validates_name_and_locale_without_persisting_changes(): void
    {
        $user = User::factory()->create(['name' => 'Stable Name', 'locale' => 'ar']);

        $this->actingAs($user)->put('/ar/dashboard/profile', [
            'name' => '',
            'locale' => 'fr',
        ])->assertRedirect()->assertSessionHasErrors(['name', 'locale']);

        $this->actingAs($user)->put('/ar/dashboard/profile', [
            'name' => str_repeat('x', 300),
            'locale' => 'ar',
        ])->assertRedirect()->assertSessionHasErrors('name');

        $fresh = $user->fresh();
        $this->assertSame('Stable Name', $fresh->name);
        $this->assertSame('ar', $fresh->locale);
    }

    public function test_inactive_and_suspended_accounts_cannot_view_or_update_profile(): void
    {
        foreach ([User::factory()->inactive(), User::factory()->suspended()] as $state) {
            $user = $state->create(['name' => 'Blocked User']);

            $this->actingAs($user)->get('/ar/dashboard/profile')
                ->assertRedirect(route('login.show', ['locale' => 'ar']));

            $this->actingAs($user)->put('/ar/dashboard/profile', ['name' => 'Unauthorized Name'])
                ->assertRedirect(route('login.show', ['locale' => 'ar']));

            $this->assertSame('Blocked User', $user->fresh()->name);
        }
    }

    public function test_profile_audit_entry_never_contains_credentials_or_tokens(): void
    {
        $user = User::factory()->create(['password' => 'audit-secret-password-123']);

        $this->actingAs($user)->put('/ar/dashboard/profile', ['name' => 'Audited Name'])
            ->assertRedirect();

        $serialized = json_encode(AuditLog::query()->get(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('audit-secret-password-123', $serialized);
        $this->assertStringNotContainsString('remember_token', $serialized);
        $this->assertStringNotContainsString('account.session_reference', $serialized);
    }

    private function seedSession(User $user, string $reference, string $rawId): void
    {
        DB::table((string) config('session.table', 'sessions'))->insert([
            'id' => $rawId,
            'user_id' => $user->id,
            'ip_address' => '203.0.113.42',
            'user_agent' => self::USER_AGENT,
            'payload' => base64_encode(json_encode(['account.session_reference' => $reference], JSON_THROW_ON_ERROR)),
            'last_activity' => time(),
            'public_reference' => $reference,
            'created_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function asArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        return (array) $value;
    }

    private function pagePayload(TestResponse $response): string
    {
        return str_replace('\\/', '/', (string) $response->getContent());
    }
}
