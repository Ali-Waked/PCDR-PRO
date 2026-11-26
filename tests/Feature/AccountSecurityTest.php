<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', '12345678901234567890123456789012');

        $this->withoutVite();
    }

    public function test_password_change_succeeds_revokes_other_sessions_and_rotates_remember_token(): void
    {
        $user = User::factory()->create([
            'email' => 'password@example.test',
            'password' => 'old-password-12345',
            'remember_token' => 'OLDREMEMBERTOKEN',
        ]);
        $currentReference = $this->seedSession($user, bin2hex(random_bytes(16)));
        $otherReference = $this->seedSession($user, bin2hex(random_bytes(16)));

        $this->actingAs($user)
            ->withSession(['account.session_reference' => $currentReference])
            ->post('/ar/dashboard/profile/password', [
                'current_password' => 'old-password-12345',
                'password' => 'brand-new-password-123',
                'password_confirmation' => 'brand-new-password-123',
            ])->assertRedirect();

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('brand-new-password-123', $fresh->password));
        $this->assertFalse(Hash::check('old-password-12345', $fresh->password));
        $this->assertNotSame('OLDREMEMBERTOKEN', $fresh->remember_token);
        $this->assertSame(1, $this->sessionCount($user, $currentReference));
        $this->assertSame(0, $this->sessionCount($user, $otherReference));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.password.changed',
            'actor_id' => $user->id,
            'subject_id' => (string) $user->id,
        ]);

        $this->assertStringNotContainsString(
            'brand-new-password-123',
            json_encode(AuditLog::query()->get(), JSON_THROW_ON_ERROR)
        );
    }

    public function test_password_change_rejects_invalid_current_password(): void
    {
        $user = User::factory()->create([
            'email' => 'wrong-current@example.test',
            'password' => 'password-that-works-123',
        ]);

        $this->actingAs($user)->post('/ar/dashboard/profile/password', [
            'current_password' => 'not-the-password',
            'password' => 'another-password-123',
            'password_confirmation' => 'another-password-123',
        ])->assertRedirect()->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password-that-works-123', $user->fresh()->password));
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'account.password.changed',
            'actor_id' => $user->id,
        ]);
    }

    public function test_password_change_rejects_weak_and_unconfirmed_passwords(): void
    {
        $user = User::factory()->create([
            'email' => 'weak-password@example.test',
            'password' => 'original-password-123',
        ]);

        $this->actingAs($user)->post('/ar/dashboard/profile/password', [
            'current_password' => 'original-password-123',
            'password' => 'short123',
            'password_confirmation' => 'short123',
        ])->assertRedirect()->assertSessionHasErrors('password');

        $this->actingAs($user)->post('/ar/dashboard/profile/password', [
            'current_password' => 'original-password-123',
            'password' => 'a-strong-password-123',
            'password_confirmation' => 'not-the-same-123',
        ])->assertRedirect()->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('original-password-123', $user->fresh()->password));
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'account.password.changed',
            'actor_id' => $user->id,
        ]);
    }

    public function test_session_index_only_lists_own_sessions_without_raw_identifiers(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $currentReference = $this->seedSession($user, bin2hex(random_bytes(16)), '203.0.113.11');
        $otherOwnReference = $this->seedSession($user, bin2hex(random_bytes(16)), '203.0.113.12');
        $otherUserReference = $this->seedSession($other, bin2hex(random_bytes(16)));

        $response = $this->actingAs($user)
            ->withSession(['account.session_reference' => $currentReference])
            ->getJson('/ar/dashboard/profile/sessions')
            ->assertOk()
            ->assertJsonStructure(['total']);

        $list = $response->json('data') ?? $response->json('sessions');
        $this->assertIsArray($list);
        $this->assertNotEmpty($list);
        $this->assertSame([
            'reference',
            'is_current',
            'ip_masked',
            'device',
            'browser',
            'platform',
            'first_seen_at',
            'last_activity_at',
        ], array_keys((array) $list[0]));

        $references = collect($list)->pluck('reference')->all();
        $this->assertContains($currentReference, $references);
        $this->assertContains($otherOwnReference, $references);
        $this->assertNotContains($otherUserReference, $references);
        $this->assertSame(2, $response->json('total'));

        $serialized = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(self::USER_AGENT, $serialized);
        $this->assertStringNotContainsString('203.0.113.11', $serialized);
        $this->assertStringNotContainsString('account.session_reference', $serialized);
        $this->assertStringNotContainsString('payload', $serialized);
    }

    public function test_user_cannot_revoke_another_users_session(): void
    {
        $user = User::factory()->create(['password' => 'current-password-123']);
        $other = User::factory()->create();
        $currentReference = $this->seedSession($user, bin2hex(random_bytes(16)));
        $victimReference = $this->seedSession($other, bin2hex(random_bytes(16)));

        $response = $this->actingAs($user)
            ->withSession(['account.session_reference' => $currentReference])
            ->delete('/ar/dashboard/profile/sessions/'.$victimReference, [
                'current_password' => 'current-password-123',
            ]);

        self::assertNotSame(200, $response->getStatusCode());
        $this->assertSame(1, $this->sessionCount($other, $victimReference));
        $this->assertSame(1, $this->sessionCount($user, $currentReference));
    }

    public function test_invalid_session_reference_cannot_revoke_a_session(): void
    {
        $user = User::factory()->create(['password' => 'current-password-123']);
        $reference = $this->seedSession($user, bin2hex(random_bytes(16)));

        $response = $this->actingAs($user)->delete('/ar/dashboard/profile/sessions/not-a-reference', [
            'current_password' => 'current-password-123',
        ]);

        self::assertNotSame(200, $response->getStatusCode());
        $this->assertSame(1, $this->sessionCount($user, $reference));
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'account.session.revoked',
            'actor_id' => $user->id,
        ]);
    }

    public function test_current_password_is_required_to_revoke_a_session(): void
    {
        $user = User::factory()->create(['password' => 'current-password-123']);
        $reference = $this->seedSession($user, bin2hex(random_bytes(16)));

        $this->actingAs($user)->delete('/ar/dashboard/profile/sessions/'.$reference)
            ->assertRedirect()
            ->assertSessionHasErrors('current_password');

        $this->assertSame(1, $this->sessionCount($user, $reference));
    }

    public function test_revoke_other_sessions_preserves_current_and_leaves_other_users_untouched(): void
    {
        $user = User::factory()->create(['password' => 'current-password-123']);
        $other = User::factory()->create();
        $currentReference = $this->seedSession($user, bin2hex(random_bytes(16)));
        $otherOwnReference = $this->seedSession($user, bin2hex(random_bytes(16)));
        $otherUserReference = $this->seedSession($other, bin2hex(random_bytes(16)));

        $this->actingAs($user)
            ->withSession(['account.session_reference' => $currentReference])
            ->delete('/ar/dashboard/profile/sessions', ['current_password' => 'current-password-123'])
            ->assertRedirect();

        $this->assertSame(1, $this->sessionCount($user, $currentReference));
        $this->assertSame(0, $this->sessionCount($user, $otherOwnReference));
        $this->assertSame(1, $this->sessionCount($other, $otherUserReference));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.sessions.revoked',
            'actor_id' => $user->id,
        ]);
    }

    public function test_single_session_revocation_writes_audit_without_leaking_session_data(): void
    {
        $user = User::factory()->create(['password' => 'current-password-123']);
        $currentReference = $this->seedSession($user, bin2hex(random_bytes(16)));
        $revokedReference = $this->seedSession($user, bin2hex(random_bytes(16)), '203.0.113.99');

        $this->actingAs($user)
            ->withSession(['account.session_reference' => $currentReference])
            ->delete('/ar/dashboard/profile/sessions/'.$revokedReference, [
                'current_password' => 'current-password-123',
            ])->assertRedirect();

        $this->assertSame(0, $this->sessionCount($user, $revokedReference));
        $audit = AuditLog::query()->where('action', 'account.session.revoked')->firstOrFail();
        $this->assertSame($user->id, (int) $audit->actor_id);
        $serialized = json_encode($audit, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('203.0.113.99', $serialized);
        $this->assertStringNotContainsString(self::USER_AGENT, $serialized);
        $this->assertStringNotContainsString('payload', $serialized);
    }

    public function test_sensitive_routes_are_throttled(): void
    {
        $user = User::factory()->create(['password' => 'current-password-123']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($user)->post('/ar/dashboard/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'a-strong-password-123',
                'password_confirmation' => 'a-strong-password-123',
            ])->assertSessionHasErrors('current_password');
        }

        $this->actingAs($user)->post('/ar/dashboard/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'a-strong-password-123',
            'password_confirmation' => 'a-strong-password-123',
        ])->assertStatus(429);
    }

    private function seedSession(
        User $user,
        string $reference,
        string $ip = '203.0.113.42',
    ): string {
        DB::table((string) config('session.table', 'sessions'))->insert([
            'id' => bin2hex(random_bytes(20)),
            'user_id' => $user->id,
            'ip_address' => $ip,
            'user_agent' => self::USER_AGENT,
            'payload' => base64_encode(json_encode(['account.session_reference' => $reference], JSON_THROW_ON_ERROR)),
            'last_activity' => time(),
            'public_reference' => $reference,
            'created_at' => now(),
        ]);

        return $reference;
    }

    private function sessionCount(User $user, string $reference): int
    {
        return DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('public_reference', $reference)
            ->count();
    }
}
