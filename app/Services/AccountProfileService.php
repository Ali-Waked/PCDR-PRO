<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\UserEmailChange;
use Illuminate\Database\Eloquent\Collection;

final class AccountProfileService
{
    public function __construct(
        private readonly AccountSessionService $sessions,
        private readonly AccountActivityService $activity,
        private readonly AuditService $audit,
    ) {}

    /**
     * @return array{
     *     profile: array<string, mixed>,
     *     pendingEmail: ?array{new_email: string, expires_at: ?string},
     *     sessions: array<string, mixed>,
     *     activity: array<string, mixed>,
     *     passwordPolicy: array{min_length: int}
     * }
     */
    public function show(User $user, string $locale, ?string $currentReference, int $page = 1): array
    {
        $pending = UserEmailChange::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>=', now())
            ->latest('created_at')
            ->first();

        /** @var Collection<int, Role> $roles */
        $roles = $user->roles()->get(['roles.id', 'roles.key', 'roles.name']);

        return [
            'profile' => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'locale' => (string) $user->locale,
                'theme' => (string) ($user->theme ?: User::THEME_SYSTEM),
                'status' => (string) $user->status,
                'created_at' => $user->created_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'last_activity_at' => $user->last_activity_at?->toIso8601String(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'email_verified' => $user->hasVerifiedEmail(),
                'avatar_url' => $user->avatarUrl(),
                'roles' => $roles->map(static fn (Role $role): array => [
                    'key' => (string) $role->key,
                    'label' => (string) $role->name,
                ])->all(),
                'is_platform_owner' => $user->isPlatformOwner(),
            ],
            'pendingEmail' => $pending === null ? null : [
                'new_email' => (string) $pending->new_email,
                'expires_at' => $pending->expires_at?->toIso8601String(),
            ],
            'sessions' => [
                'data' => $this->sessions->activeSessions($user, $locale, $currentReference),
                'total' => $this->sessions->count($user),
                'current_reference' => $currentReference,
            ],
            'activity' => $this->activity->paginate($user, $locale, $page),
            'passwordPolicy' => ['min_length' => 12],
        ];
    }

    /** @param array{name?: string, locale?: string, theme?: string|null} $data */
    public function update(User $user, array $data): void
    {
        $before = [
            'name' => (string) $user->name,
            'locale' => (string) $user->locale,
            'theme' => (string) ($user->theme ?: User::THEME_SYSTEM),
        ];

        $changes = [];

        if (array_key_exists('name', $data)) {
            $changes['name'] = $data['name'];
        }

        if (array_key_exists('locale', $data)) {
            $changes['locale'] = $data['locale'];
        }

        if (array_key_exists('theme', $data)) {
            $changes['theme'] = $data['theme'] === User::THEME_SYSTEM || $data['theme'] === null
                ? null
                : $data['theme'];
        }

        if ($changes !== []) {
            $user->forceFill($changes)->save();
        }

        $this->audit->record('account.profile.updated', 'users', (string) $user->id, $before, [
            'name' => (string) $user->name,
            'locale' => (string) $user->locale,
            'theme' => (string) ($user->theme ?: User::THEME_SYSTEM),
        ]);
    }
}
