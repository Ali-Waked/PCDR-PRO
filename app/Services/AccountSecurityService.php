<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class AccountSecurityService
{
    public function __construct(
        private readonly AccountSessionService $sessions,
        private readonly AuditService $audit,
    ) {}

    public function changePassword(User $user, string $password, ?string $currentReference): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
            'remember_token' => Str::random(60),
        ])->save();

        if ($currentReference !== null) {
            $this->sessions->revokeOthers($user, $currentReference);
        }

        $this->audit->record('account.password.changed', 'users', (string) $user->id);
    }

    public function revokeSession(User $user, string $reference): int
    {
        $count = $this->sessions->revoke($user, $reference);

        $this->audit->record('account.session.revoked', 'users', (string) $user->id, null, [
            'public_reference' => $reference,
            'count' => $count,
        ]);

        return $count;
    }

    public function revokeOtherSessions(User $user, string $currentReference): int
    {
        $count = $this->sessions->revokeOthers($user, $currentReference);

        $this->audit->record('account.sessions.revoked', 'users', (string) $user->id, null, [
            'public_reference' => $currentReference,
            'count' => $count,
        ]);

        return $count;
    }

    /** @return array{sessions: list<array<string, mixed>>, total: int} */
    public function sessionsJson(User $user, string $locale, ?string $currentReference): array
    {
        return [
            'sessions' => $this->sessions->activeSessions($user, $locale, $currentReference),
            'total' => $this->sessions->count($user),
        ];
    }
}
