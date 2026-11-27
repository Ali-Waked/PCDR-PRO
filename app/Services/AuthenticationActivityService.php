<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

final class AuthenticationActivityService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly RbacService $rbac,
    ) {}

    public function recordSuccessfulLogin(User $user): void
    {
        $user->forceFill([
            'previous_login_at' => $user->last_login_at,
            'last_login_at' => now(),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        $this->rbac->forgetUser($user);
        $this->audit->record('auth.login', 'users', (string) $user->id);
    }

    public function recordFailedLogin(User $user, string $reason): void
    {
        $this->audit->record('auth.login_failed', 'users', (string) $user->id, null, [
            'reason' => $reason,
        ]);
    }

    public function recordLogout(User $user): void
    {
        $this->rbac->forgetUser($user);
        $this->audit->record('auth.logout', 'users', (string) $user->id);
    }
}
