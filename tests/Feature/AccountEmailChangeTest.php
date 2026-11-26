<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use ReflectionObject;
use Tests\TestCase;

final class AccountEmailChangeTest extends TestCase
{
    use RefreshDatabase;

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', '12345678901234567890123456789012');

        $this->withoutVite();
    }

    public function test_request_requires_the_current_password(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'current@example.test',
            'password' => 'current-password-123',
        ]);

        $this->actingAs($user)->post('/ar/dashboard/profile/email', [
            'email' => 'next@example.test',
        ])->assertRedirect()->assertSessionHasErrors('current_password');

        $this->actingAs($user)->post('/ar/dashboard/profile/email', [
            'email' => 'next@example.test',
            'current_password' => 'definitely-wrong',
        ])->assertRedirect()->assertSessionHasErrors('current_password');

        $this->assertSame('current@example.test', $user->fresh()->email);
        $this->assertSame(0, DB::table('user_email_changes')->where('user_id', $user->id)->count());
        Notification::assertNothingSent();
    }

    public function test_request_requires_a_valid_and_unique_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'current@example.test',
            'password' => 'current-password-123',
        ]);
        User::factory()->create(['email' => 'taken@example.test']);

        $this->actingAs($user)->post('/ar/dashboard/profile/email', [
            'email' => 'not-an-email',
            'current_password' => 'current-password-123',
        ])->assertRedirect()->assertSessionHasErrors('email');

        $this->actingAs($user)->post('/ar/dashboard/profile/email', [
            'email' => 'taken@example.test',
            'current_password' => 'current-password-123',
        ])->assertRedirect()->assertSessionHasErrors('email');

        $this->assertSame('current@example.test', $user->fresh()->email);
        $this->assertSame(0, DB::table('user_email_changes')->where('user_id', $user->id)->count());
        Notification::assertNothingSent();
    }

    public function test_pending_change_stores_only_a_hashed_token_and_keeps_the_old_email_active(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'old@example.test',
            'password' => 'current-password-123',
        ]);

        $this->actingAs($user)->post('/ar/dashboard/profile/email', [
            'email' => 'new@example.test',
            'current_password' => 'current-password-123',
        ])->assertRedirect();

        $pending = DB::table('user_email_changes')->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('new@example.test', (string) $pending->new_email);
        $this->assertSame('old@example.test', $user->fresh()->email);

        $rawToken = $this->rawTokenFromNotifications();
        $knownHashes = [
            hash('sha256', $rawToken),
            hash('sha512', $rawToken),
            md5($rawToken),
        ];
        $hasHashedToken = collect((array) $pending)->contains(
            fn ($value): bool => is_string($value)
                && $value !== ''
                && in_array($value, $knownHashes, true)
        );
        $this->assertTrue($hasHashedToken, 'The pending email-change token is not stored as a hash.');
        $this->assertStringNotContainsString($rawToken, json_encode($pending, JSON_THROW_ON_ERROR));

        $this->actingAs($user)->get('/ar/dashboard/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile.email', 'old@example.test')
                ->where('pendingEmail.new_email', 'new@example.test')
                ->where('pendingEmail.expires_at', fn ($value): bool => is_string($value) && $value !== ''));
    }

    public function test_notifications_are_sent_to_the_new_address_and_the_old_address(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'old-notify@example.test',
            'password' => 'current-password-123',
        ]);

        $this->actingAs($user)->post('/ar/dashboard/profile/email', [
            'email' => 'new-notify@example.test',
            'current_password' => 'current-password-123',
        ])->assertRedirect();

        $recipients = $this->notificationRecipients();
        $this->assertContains('new-notify@example.test', $recipients);
        $this->assertContains('old-notify@example.test', $recipients);
        $this->assertGreaterThanOrEqual(
            2,
            collect(Notification::sentNotifications())->flatten(3)->count()
        );
    }

    public function test_signed_confirmation_commits_the_email_and_marks_it_verified(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'old-confirm@example.test',
            'password' => 'current-password-123',
        ]);

        $this->actingAs($user)->post('/ar/dashboard/profile/email', [
            'email' => 'new-confirm@example.test',
            'current_password' => 'current-password-123',
        ])->assertRedirect();

        $url = $this->confirmUrl($this->rawTokenFromNotifications());

        $this->actingAs($user)->get($url)->assertRedirect();

        $fresh = $user->fresh();
        $this->assertSame('new-confirm@example.test', $fresh->email);
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.email_change.confirmed',
            'actor_id' => $user->id,
            'subject_id' => (string) $user->id,
        ]);

        $this->actingAs($fresh)->get('/ar/dashboard/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile.email', 'new-confirm@example.test')
                ->where('profile.email_verified', true)
                ->where('pendingEmail', null));
    }

    public function test_wrong_and_tampered_confirmation_links_are_rejected(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'wrong-link@example.test',
            'password' => 'current-password-123',
        ]);

        $this->actingAs($user)->post('/ar/dashboard/profile/email', [
            'email' => 'pending@example.test',
            'current_password' => 'current-password-123',
        ])->assertRedirect();

        $wrongLinkResponse = $this->actingAs($user)->get($this->confirmUrl(str_repeat('a', 32)));
        self::assertNotSame(200, $wrongLinkResponse->getStatusCode());

        $parts = parse_url($this->confirmUrl($this->rawTokenFromNotifications()));
        parse_str((string) ($parts['query'] ?? ''), $query);
        $query['signature'] = str_repeat('0', 64);
        $tampered = (string) ($parts['path'] ?? '').'?'.http_build_query($query);

        $this->actingAs($user)->get($tampered)->assertForbidden();

        $this->assertSame('wrong-link@example.test', $user->fresh()->email);
        $this->assertSame(
            0,
            DB::table('audit_logs')->where('action', 'account.email_change.confirmed')->where('actor_id', $user->id)->count()
        );
    }

    public function test_expired_confirmation_link_is_rejected(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'expired@example.test',
            'password' => 'current-password-123',
        ]);

        $this->actingAs($user)->post('/ar/dashboard/profile/email', [
            'email' => 'expired-new@example.test',
            'current_password' => 'current-password-123',
        ])->assertRedirect();

        $url = $this->confirmUrl($this->rawTokenFromNotifications(), 'ar', now()->subMinute());

        $this->actingAs($user)->get($url)->assertForbidden();

        $this->assertSame('expired@example.test', $user->fresh()->email);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'account.email_change.confirmed']);
    }

    public function test_confirmation_link_cannot_be_replayed(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'replay@example.test',
            'password' => 'current-password-123',
        ]);

        $this->actingAs($user)->post('/ar/dashboard/profile/email', [
            'email' => 'replay-new@example.test',
            'current_password' => 'current-password-123',
        ])->assertRedirect();

        $url = $this->confirmUrl($this->rawTokenFromNotifications());

        $this->actingAs($user)->get($url)->assertRedirect();
        $this->actingAs($user)->get($url);

        $this->assertSame('replay-new@example.test', $user->fresh()->email);
        $this->assertSame(
            1,
            DB::table('audit_logs')->where('action', 'account.email_change.confirmed')->where('actor_id', $user->id)->count()
        );
    }

    public function test_pending_email_change_can_be_cancelled(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'cancel@example.test',
            'password' => 'current-password-123',
        ]);

        $this->actingAs($user)->post('/ar/dashboard/profile/email', [
            'email' => 'cancel-new@example.test',
            'current_password' => 'current-password-123',
        ])->assertRedirect();

        $this->actingAs($user)->delete('/ar/dashboard/profile/email', [
            'current_password' => 'current-password-123',
        ])->assertRedirect();

        $this->assertSame('cancel@example.test', $user->fresh()->email);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.email_change.cancelled',
            'actor_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'account.email_change.confirmed',
            'actor_id' => $user->id,
        ]);

        $this->actingAs($user)->get('/ar/dashboard/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile.email', 'cancel@example.test')
                ->where('pendingEmail', null));
    }

    public function test_confirmation_revokes_other_sessions_but_preserves_the_current_one(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'sessions@example.test',
            'password' => 'current-password-123',
        ]);
        $currentReference = $this->seedSession($user, bin2hex(random_bytes(16)));
        $otherReference = $this->seedSession($user, bin2hex(random_bytes(16)));

        $this->actingAs($user)->post('/ar/dashboard/profile/email', [
            'email' => 'sessions-new@example.test',
            'current_password' => 'current-password-123',
        ])->assertRedirect();

        $url = $this->confirmUrl($this->rawTokenFromNotifications());

        $this->actingAs($user)
            ->withSession(['account.session_reference' => $currentReference])
            ->get($url)
            ->assertRedirect();

        $this->assertSame(1, $this->sessionCount($user, $currentReference));
        $this->assertSame(0, $this->sessionCount($user, $otherReference));
    }

    public function test_validation_messages_are_localized(): void
    {
        $user = User::factory()->create([
            'email' => 'locale@example.test',
            'password' => 'current-password-123',
        ]);

        $this->actingAs($user)->post('/ar/dashboard/profile/email', [
            'email' => 'invalid',
            'current_password' => 'current-password-123',
        ])->assertRedirect()->assertSessionHasErrors('email');

        $arabicMessage = (string) (session()->get('errors')->getBag('default')->get('email')[0] ?? '');

        $this->actingAs($user)->post('/en/dashboard/profile/email', [
            'email' => 'invalid',
            'current_password' => 'current-password-123',
        ])->assertRedirect()->assertSessionHasErrors('email');

        $englishMessage = (string) (session()->get('errors')->getBag('default')->get('email')[0] ?? '');

        $this->assertNotSame('', $arabicMessage);
        $this->assertNotSame('', $englishMessage);
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $arabicMessage);
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $englishMessage);
    }

    private function confirmUrl(string $token, string $locale = 'ar', mixed $expiration = null): string
    {
        return URL::temporarySignedRoute(
            'account.email.change.confirm',
            $expiration ?? now()->addMinutes(60),
            ['locale' => $locale, 'token' => $token],
        );
    }

    private function seedSession(User $user, string $reference): string
    {
        DB::table((string) config('session.table', 'sessions'))->insert([
            'id' => bin2hex(random_bytes(20)),
            'user_id' => $user->id,
            'ip_address' => '203.0.113.77',
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

    private function rawTokenFromNotifications(): string
    {
        $strings = $this->notificationStrings();

        foreach ($strings as $value) {
            if (preg_match('/^[a-f0-9]{64}$/', $value) === 1) {
                return $value;
            }
        }

        foreach ($strings as $value) {
            if (preg_match('/(?:[?&]|confirm\/)token=([A-Za-z0-9_-]{32,128})/', $value, $matches) === 1) {
                return $matches[1];
            }

            if (preg_match('#/email/confirm/([A-Za-z0-9_-]{32,128})#', $value, $matches) === 1) {
                return $matches[1];
            }

            if (preg_match('/^[A-Za-z0-9_-]{32,128}$/', $value) === 1) {
                return $value;
            }
        }

        $this->fail('No raw email-change token was found in the sent notifications.');
    }

    /** @return list<string> */
    private function notificationStrings(): array
    {
        $strings = [];

        foreach (Notification::sentNotifications() as $notifiableTypes) {
            foreach ($notifiableTypes as $notificationsByClass) {
                foreach ($notificationsByClass as $entries) {
                    foreach ($entries as $entry) {
                        foreach ($this->stringProperties($entry['notification']) as $value) {
                            $strings[] = $value;
                        }
                    }
                }
            }
        }

        return $strings;
    }

    /** @return list<string> */
    private function notificationRecipients(): array
    {
        $recipients = [];

        foreach (Notification::sentNotifications() as $notifiableTypes) {
            foreach ($notifiableTypes as $notificationsByClass) {
                foreach ($notificationsByClass as $entries) {
                    foreach ($entries as $entry) {
                        $notifiable = $entry['notifiable'] ?? null;

                        if ($notifiable instanceof AnonymousNotifiable) {
                            foreach ($notifiable->routes as $route) {
                                if (is_string($route) && filter_var($route, FILTER_VALIDATE_EMAIL) !== false) {
                                    $recipients[] = $route;
                                }
                            }
                        } elseif (is_object($notifiable) && isset($notifiable->email)) {
                            $recipients[] = (string) $notifiable->email;
                        }

                        foreach ($this->stringProperties($entry['notification']) as $value) {
                            if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
                                $recipients[] = $value;
                            }
                        }
                    }
                }
            }
        }

        return $recipients;
    }

    /** @return list<string> */
    private function stringProperties(object $object): array
    {
        $strings = [];
        $reflection = new ReflectionObject($object);

        do {
            foreach ($reflection->getProperties() as $property) {
                if ($property->isStatic() || ! $property->isInitialized($object)) {
                    continue;
                }

                $value = $property->getValue($object);

                if (is_string($value)) {
                    $strings[] = $value;
                } elseif (is_object($value) && method_exists($value, '__toString')) {
                    $strings[] = (string) $value;
                } elseif (is_array($value)) {
                    foreach ($value as $item) {
                        if (is_string($item)) {
                            $strings[] = $item;
                        }
                    }
                }
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);

        return $strings;
    }
}
