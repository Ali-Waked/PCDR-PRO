<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AccountActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', '12345678901234567890123456789012');

        $this->withoutVite();
    }

    public function test_sign_in_password_profile_and_avatar_events_appear_in_the_activity_paginator(): void
    {
        $user = User::factory()->create(['password' => 'current-password-123']);

        $this->audit($user, 'auth.login');
        $this->audit($user, 'account.password.changed');
        $this->audit($user, 'account.profile.updated');
        $this->audit($user, 'account.avatar.uploaded');

        $response = $this->actingAs($user)->get('/ar/dashboard/profile')->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Account/Profile/Show')
            ->where('activity.total', 4)
            ->where('activity.data', fn ($value): bool => count(array_intersect([
                'auth.login',
                'account.password.changed',
                'account.profile.updated',
                'account.avatar.uploaded',
            ], $this->actions($value))) === 4));
    }

    public function test_logout_and_session_events_appear_in_the_activity_paginator(): void
    {
        $user = User::factory()->create();

        $this->audit($user, 'auth.logout');
        $this->audit($user, 'account.session.revoked');
        $this->audit($user, 'account.sessions.revoked');

        $this->actingAs($user)->get('/ar/dashboard/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activity.total', 3)
                ->where('activity.data', fn ($value): bool => count(array_intersect(
                    ['auth.logout', 'account.session.revoked', 'account.sessions.revoked'],
                    $this->actions($value),
                )) === 3));
    }

    public function test_failed_login_is_included_by_subject_only_for_the_target_account(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'failure@example.test', 'password' => 'current-password-123']);
        $other = User::factory()->create(['email' => 'other-failure@example.test', 'password' => 'current-password-123']);

        $this->post('/ar/login', ['email' => $user->email, 'password' => 'wrong-password-123']);
        $this->post('/ar/login', ['email' => $other->email, 'password' => 'wrong-password-123']);

        $this->actingAs($user)->get('/ar/dashboard/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activity.total', 1)
                ->where('activity.data', fn ($value): bool => $this->actions($value) === ['auth.login_failed']));

        $this->assertSame(
            1,
            DB::table('audit_logs')
                ->where('action', 'auth.login_failed')
                ->where('subject_id', (string) $user->id)
                ->count()
        );
    }

    public function test_activity_paginator_never_includes_another_users_events(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->audit($user, 'account.profile.updated');
        $this->audit($other, 'security.other_user_marker');

        $response = $this->actingAs($user)->get('/ar/dashboard/profile')->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->where('activity.total', 1)
            ->where('activity.data', fn ($value): bool => $this->actions($value) === ['account.profile.updated']));

        $this->assertStringNotContainsString(
            'security.other_user_marker',
            str_replace('\\/', '/', (string) $response->getContent())
        );
    }

    public function test_activity_props_never_expose_context_passwords_tokens_or_raw_request_data(): void
    {
        $user = User::factory()->create();

        AuditLog::query()->create([
            'actor_id' => $user->id,
            'action' => 'account.profile.updated',
            'subject_type' => 'users',
            'subject_id' => (string) $user->id,
            'context' => [
                'password' => 'CONTEXT-PASSWORD-MARKER',
                'token' => 'CONTEXT-TOKEN-MARKER',
                'ip' => '203.0.113.199',
                'user_agent' => 'CONTEXT-USER-AGENT-MARKER',
            ],
            'before' => ['password' => 'BEFORE-PASSWORD-MARKER'],
            'after' => ['token' => 'AFTER-TOKEN-MARKER'],
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/ar/dashboard/profile')->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->where('activity.data', fn ($value): bool => collect($value)->every(fn ($event): bool => ! array_key_exists('context', $event)
                && ! array_key_exists('before', $event)
                && ! array_key_exists('after', $event)
                && ! array_key_exists('password', $event)
                && ! array_key_exists('token', $event))));

        $payload = str_replace('\\/', '/', (string) $response->getContent());
        foreach ([
            'CONTEXT-PASSWORD-MARKER',
            'CONTEXT-TOKEN-MARKER',
            'CONTEXT-USER-AGENT-MARKER',
            'BEFORE-PASSWORD-MARKER',
            'AFTER-TOKEN-MARKER',
            '203.0.113.199',
        ] as $marker) {
            $this->assertStringNotContainsString($marker, $payload);
        }
    }

    public function test_last_activity_timestamp_is_throttled_between_requests(): void
    {
        Cache::flush();

        $user = User::factory()->create(['last_activity_at' => now()->subDay()]);
        $old = '2020-01-02T03:04:05+00:00';
        DB::table('users')->where('id', $user->id)->update(['last_activity_at' => $old]);

        $start = now();

        $this->actingAs($user)->get('/ar/dashboard/profile')->assertOk();

        $firstTouch = Carbon::parse((string) $user->fresh()->last_activity_at);
        $this->assertGreaterThanOrEqual($start->getTimestamp(), $firstTouch->getTimestamp());

        DB::table('users')->where('id', $user->id)->update(['last_activity_at' => $old]);

        $this->actingAs($user)->get('/ar/dashboard/profile')->assertOk();

        $throttled = Carbon::parse((string) $user->fresh()->last_activity_at);
        $this->assertSame(Carbon::parse($old)->getTimestamp(), $throttled->getTimestamp());
    }

    public function test_activity_labels_are_localized_and_empty_state_is_available(): void
    {
        $user = User::factory()->create();
        $this->audit($user, 'auth.login');

        $arabic = $this->actingAs($user)->get('/ar/dashboard/profile')->assertOk();
        $english = $this->actingAs($user)->get('/en/dashboard/profile')->assertOk();

        $arabicLabels = [];
        $arabic->assertInertia(function (Assert $page) use (&$arabicLabels): void {
            $page->where('activity.data', function ($events) use (&$arabicLabels): bool {
                $arabicLabels = $this->labels($events);

                return true;
            });
        });

        $englishLabels = [];
        $english->assertInertia(function (Assert $page) use (&$englishLabels): void {
            $page->where('activity.data', function ($events) use (&$englishLabels): bool {
                $englishLabels = $this->labels($events);

                return true;
            });
        });

        $this->assertCount(1, $arabicLabels);
        $this->assertCount(1, $englishLabels);
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $arabicLabels[0]);
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $englishLabels[0]);

        $emptyUser = User::factory()->create();

        $this->actingAs($emptyUser)->get('/ar/dashboard/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activity.total', 0)
                ->where('activity.data', [])
                ->where('activity.links', fn ($value): bool => is_array($value) || is_object($value))
                ->where('activity.from', null)
                ->where('activity.to', null));
    }

    /** @param list<string> $actions */
    private function audit(User $user, string $action, ?string $subjectId = null): AuditLog
    {
        return AuditLog::query()->create([
            'actor_id' => $user->id,
            'action' => $action,
            'subject_type' => 'users',
            'subject_id' => $subjectId ?? (string) $user->id,
            'context' => [],
            'created_at' => now(),
        ]);
    }

    /** @return list<string> */
    private function actions(mixed $events): array
    {
        return collect($events)
            ->pluck('action')
            ->map(fn ($value): string => $this->normalizeAction((string) $value))
            ->all();
    }

    private function normalizeAction(string $action): string
    {
        if (str_contains($action, '.')) {
            return $action;
        }

        $known = [
            'auth_login' => 'auth.login',
            'auth_logout' => 'auth.logout',
            'auth_login_failed' => 'auth.login_failed',
            'auth_email_verified' => 'auth.email_verified',
            'account_profile_updated' => 'account.profile.updated',
            'account_password_changed' => 'account.password.changed',
            'account_email_change_requested' => 'account.email_change.requested',
            'account_email_change_confirmed' => 'account.email_change.confirmed',
            'account_email_change_cancelled' => 'account.email_change.cancelled',
            'account_avatar_uploaded' => 'account.avatar.uploaded',
            'account_avatar_removed' => 'account.avatar.removed',
            'account_session_revoked' => 'account.session.revoked',
            'account_sessions_revoked' => 'account.sessions.revoked',
        ];

        return $known[$action] ?? $action;
    }

    /** @return list<string> */
    private function labels(mixed $events): array
    {
        return collect($events)
            ->pluck('label')
            ->map(fn ($value): string => (string) $value)
            ->all();
    }
}
