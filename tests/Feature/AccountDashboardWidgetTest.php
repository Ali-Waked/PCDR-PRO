<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AccountDashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', '12345678901234567890123456789012');
        config()->set('dashboard_features.hidden_modules_access', false);

        $this->withoutVite();
    }

    public function test_account_widget_reports_only_the_authenticated_users_real_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Widget User',
            'email' => 'widget@example.test',
            'last_login_at' => now()->subHours(3),
            'last_activity_at' => now(),
        ]);
        $other = User::factory()->create([
            'name' => 'Other Widget User',
            'email' => 'other-widget@example.test',
            'last_login_at' => now()->subDays(4),
            'last_activity_at' => now()->subDays(4),
        ]);

        $ownReference = $this->seedSession($user);
        $this->seedSession($user);
        $otherReference = $this->seedSession($other);

        $login = $this->audit($user, 'auth.login');
        $profileUpdate = $this->audit($user, 'account.profile.updated');
        $foreignEvent = $this->audit($other, 'security.other_widget_marker');

        $requestStart = now();

        $response = $this->actingAs($user)
            ->withSession(['account.session_reference' => $ownReference])
            ->get('/ar/dashboard')
            ->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('overview.account_activity.last_activity_at', fn ($value): bool => is_string($value)
                && Carbon::parse($value)->getTimestamp() >= $requestStart->getTimestamp())
            ->where('overview.account_activity.last_login_at', $user->last_login_at->toIso8601String())
            ->where('overview.account_activity.active_sessions', 2)
            ->where('overview.account_activity.security_status', 'protected')
            ->where('overview.account_activity.profile_url', fn ($value): bool => is_string($value)
                && str_contains($value, '/ar/dashboard/profile'))
            ->where('overview.account_activity.security_url', fn ($value): bool => is_string($value)
                && str_contains($value, '/ar/dashboard/profile')
                && str_contains($value, '#security'))
            ->where('overview.account_activity.events', fn ($value): bool => count(array_intersect(
                [$login->id, $profileUpdate->id],
                $this->eventIds($value),
            )) === 2
                && ! in_array($foreignEvent->id, $this->eventIds($value), true))
            ->where('overview.activity', []));

        $serialized = str_replace('\\/', '/', (string) $response->getContent());
        $this->assertStringNotContainsString('other-widget@example.test', $serialized);
        $this->assertStringNotContainsString('Other Widget User', $serialized);
        $this->assertStringNotContainsString('security.other_widget_marker', $serialized);
        $this->assertStringNotContainsString($otherReference, $serialized);
        $this->assertStringNotContainsString(self::USER_AGENT, $serialized);
    }

    public function test_widget_has_no_fabricated_values_for_an_empty_account(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'empty-widget@example.test',
            'last_login_at' => null,
            'last_activity_at' => null,
        ]);

        // Prevent the activity tracker from changing the timestamp we are
        // asserting against; the throttle is part of the account contract.
        Cache::put('account:activity:'.$user->id, true, 60);

        $this->actingAs($user)->get('/ar/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.account_activity.last_activity_at', null)
                ->where('overview.account_activity.last_login_at', null)
                ->where('overview.account_activity.active_sessions', 0)
                ->where('overview.account_activity.security_status', 'review')
                ->where('overview.account_activity.events', []));
    }

    public function test_user_without_audit_permission_still_sees_their_own_widget(): void
    {
        $user = User::factory()->create(['email' => 'no-audit@example.test']);
        $this->audit($user, 'account.password.changed');

        $this->actingAs($user)->get('/ar/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.permissions.audit_logs', false)
                ->where('overview.activity', [])
                ->where('overview.account_activity.events', fn ($value): bool => $this->actions($value) === ['account.password.changed'])
                ->where('overview.account_activity.active_sessions', 0));
    }

    public function test_widget_urls_follow_the_requested_locale(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/en/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.account_activity.profile_url', fn ($value): bool => is_string($value)
                    && str_contains($value, '/en/dashboard/profile'))
                ->where('overview.account_activity.security_url', fn ($value): bool => is_string($value)
                    && str_contains($value, '#security')));
    }

    private function seedSession(User $user): string
    {
        $reference = bin2hex(random_bytes(16));

        DB::table((string) config('session.table', 'sessions'))->insert([
            'id' => bin2hex(random_bytes(20)),
            'user_id' => $user->id,
            'ip_address' => '203.0.113.44',
            'user_agent' => self::USER_AGENT,
            'payload' => base64_encode(json_encode(['account.session_reference' => $reference], JSON_THROW_ON_ERROR)),
            'last_activity' => time(),
            'public_reference' => $reference,
            'created_at' => now(),
        ]);

        return $reference;
    }

    private function audit(User $user, string $action): AuditLog
    {
        return AuditLog::query()->create([
            'actor_id' => $user->id,
            'action' => $action,
            'subject_type' => 'users',
            'subject_id' => (string) $user->id,
            'context' => [],
            'created_at' => now(),
        ]);
    }

    /** @return list<int> */
    private function eventIds(mixed $events): array
    {
        return collect($events)
            ->pluck('id')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }

    /** @return list<string> */
    private function actions(mixed $events): array
    {
        return collect($events)
            ->pluck('action')
            ->map(fn ($value): string => (string) $value)
            ->all();
    }
}
