<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserEmailChange;
use App\Notifications\AccountEmailChangeNoticeNotification;
use App\Notifications\AccountEmailChangeNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

final class AccountEmailChangeService
{
    private const EXPIRY_MINUTES = 60;

    public function __construct(
        private readonly AccountSessionService $sessions,
        private readonly AuditService $audit,
        private readonly RbacService $rbac,
    ) {}

    public function request(User $user, string $newEmail): UserEmailChange
    {
        $oldEmail = (string) $user->email;
        $locale = (string) ($user->locale ?: 'ar');
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        $change = DB::transaction(function () use ($user, $oldEmail, $newEmail, $tokenHash): UserEmailChange {
            $user->emailChanges()->whereNull('consumed_at')->delete();

            return $user->emailChanges()->create([
                'current_email' => $oldEmail,
                'new_email' => $newEmail,
                'token_hash' => $tokenHash,
                'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            ]);
        });

        Notification::route('mail', $newEmail)->notify(
            new AccountEmailChangeNotification($token, $locale, self::EXPIRY_MINUTES),
        );
        Notification::route('mail', $oldEmail)->notify(
            new AccountEmailChangeNoticeNotification($locale),
        );

        $this->audit->record('account.email_change.requested', 'users', (string) $user->id, null, [
            'new_email_hash' => hash('sha256', strtolower($newEmail)),
        ]);

        return $change;
    }

    public function confirm(User $user, string $token, ?string $currentReference): bool
    {
        $tokenHash = hash('sha256', $token);
        $change = UserEmailChange::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>=', now())
            ->where('token_hash', $tokenHash)
            ->first();

        if ($change === null || ! hash_equals($change->token_hash, $tokenHash)) {
            return false;
        }

        DB::transaction(function () use ($user, $change): void {
            $change->forceFill(['consumed_at' => now()])->save();

            $user->forceFill([
                'email' => (string) $change->new_email,
                'email_verified_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();
        });

        if ($currentReference !== null) {
            $this->sessions->revokeOthers($user, $currentReference);
        }

        $this->rbac->forgetUser($user);
        $this->rbac->invalidate();

        $this->audit->record('account.email_change.confirmed', 'users', (string) $user->id, null, [
            'change_id' => (int) $change->id,
        ]);

        event(new Verified($user));

        return true;
    }

    public function cancel(User $user): int
    {
        $deleted = $user->emailChanges()->whereNull('consumed_at')->delete();

        $this->audit->record('account.email_change.cancelled', 'users', (string) $user->id, null, [
            'count' => $deleted,
        ]);

        return $deleted;
    }
}
