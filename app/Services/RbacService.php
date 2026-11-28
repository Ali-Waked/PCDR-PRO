<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\RoleScope;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class RbacService
{
    private const VERSION_KEY = 'pcdr:rbac:version';

    private const TECHNICAL_WILDCARD = '*';

    /** @return Collection<int, string> */
    public function permissionsFor(User $user): Collection
    {
        if ($user->isPlatformOwner()) {
            return collect(array_keys(config('permissions', [])))
                ->reject(fn (string $permission): bool => $this->isAuthorityPermission($permission))
                ->push(self::TECHNICAL_WILDCARD)
                ->unique()
                ->values();
        }

        $key = $this->key('permissions', $user);

        return collect($this->rememberArray($key, function () use ($user): array {
            return $user->roles()
                ->whereNull('roles.deleted_at')
                ->with('permissions')
                ->get()
                ->flatMap(fn (Role $role) => $role->permissions->where('is_retired', false)->pluck('key'))
                ->reject(fn (string $permission): bool => $this->isAuthorityPermission($permission))
                ->unique()
                ->values()
                ->all();
        }));
    }

    /**
     * Permissions exposed to the browser. This is a presentation-safe subset of
     * the authorization list: unsupported, hidden, reserved, and historical
     * keys are never sent to the dashboard, while the technical wildcard is
     * retained for Platform Owner UI decisions.
     *
     * @return Collection<int, string>
     */
    public function dashboardPermissionsFor(User $user): Collection
    {
        if ($user->isPlatformOwner()) {
            return collect(PermissionCatalog::supportedKeys())
                ->reject(fn (string $permission): bool => $this->isAuthorityPermission($permission))
                ->push(self::TECHNICAL_WILDCARD)
                ->unique()
                ->values();
        }

        return $this->permissionsFor($user)
            ->filter(fn (string $permission): bool => $permission === self::TECHNICAL_WILDCARD
                || PermissionCatalog::isSupported($permission))
            ->values();
    }

    /** @return Collection<int, string> */
    public function rolesFor(User $user): Collection
    {
        if ($user->isPlatformOwner()) {
            return collect(['platform_owner']);
        }

        $key = $this->key('roles', $user);

        return collect($this->rememberArray(
            $key,
            fn (): array => $user->roles()->pluck('roles.key')->unique()->values()->all(),
        ));
    }

    /** @return Collection<int, RoleScope> */
    public function scopeRowsFor(User $user): Collection
    {
        if ($user->isPlatformOwner()) {
            return collect();
        }

        return $user->roles()
            ->with('scopes')
            ->get()
            ->flatMap(fn (Role $role) => $role->scopes)
            ->values();
    }

    /**
     * Public-safe scope summary. Scope values and internal identifiers are
     * deliberately never returned.
     *
     * @return array<string, list<array{scope_type: string}>>
     */
    public function publicScopesFor(User $user): array
    {
        if ($user->isPlatformOwner()) {
            return ['*' => [['scope_type' => 'all']]];
        }

        $result = [];

        foreach ($this->scopeRowsFor($user) as $scope) {
            $result[$scope->resource_key][] = ['scope_type' => $scope->scope_type];
        }

        return $result;
    }

    public function has(User $user, string $permission): bool
    {
        if ($this->isAuthorityPermission($permission)) {
            return false;
        }

        if ($user->isPlatformOwner()) {
            return true;
        }

        return $this->permissionsFor($user)->contains($permission);
    }

    /**
     * Institutional authority is never implied by technical permission
     * ownership, including for future permission keys that are not yet known
     * to the catalog.
     */
    public function isAuthorityPermission(string $permission): bool
    {
        $normalized = strtolower($permission);
        $compact = (string) preg_replace('/[^a-z0-9]/', '', $normalized);

        if ($normalized === '' || $normalized === self::TECHNICAL_WILDCARD) {
            return false;
        }

        /** @var list<string> $prefixes */
        $prefixes = config('governance.authority.permission_prefixes', []);
        /** @var list<string> $suffixes */
        $suffixes = config('governance.authority.permission_suffixes', []);

        foreach ($prefixes as $prefix) {
            $prefix = strtolower($prefix);

            if (str_starts_with($normalized, $prefix)
                || str_starts_with($compact, (string) preg_replace('/[^a-z0-9]/', '', $prefix))) {
                return true;
            }
        }

        foreach ($suffixes as $suffix) {
            $suffix = strtolower($suffix);
            $compactSuffix = (string) preg_replace('/[^a-z0-9]/', '', $suffix);

            if (str_ends_with($normalized, $suffix)
                || str_ends_with($compact, $compactSuffix)) {
                return true;
            }
        }

        return $this->matchesAuthorityStem($compact);
    }

    public function isAuthorityAbility(string $ability): bool
    {
        $spaced = (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $ability);
        $lower = strtolower($spaced);
        $tokens = preg_split('/[^a-zA-Z0-9]+/', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $compact = (string) preg_replace('/[^a-z0-9]/', '', $lower);

        if ($tokens === []) {
            return false;
        }

        if (in_array('institutional', $tokens, true) || in_array('governance', $tokens, true)) {
            return true;
        }

        /** @var list<string> $abilities */
        $abilities = array_map('strtolower', (array) config('governance.authority.abilities', []));

        if (in_array($compact, $abilities, true)) {
            return true;
        }

        foreach ($tokens as $token) {
            if (in_array($token, $abilities, true)) {
                return true;
            }
        }

        return $this->matchesAuthorityStem($compact);
    }

    private function matchesAuthorityStem(string $compact): bool
    {
        /** @var list<string> $stems */
        $stems = config('governance.authority.term_stems', []);

        foreach ($stems as $stem) {
            $stem = (string) preg_replace('/[^a-z0-9]/', '', strtolower((string) $stem));

            if ($stem !== '' && str_contains($compact, $stem)) {
                return true;
            }
        }

        return false;
    }

    public function authorityReason(string $locale = 'en'): string
    {
        $reasons = config('governance.authority.reason', []);

        return (string) ($reasons[$locale] ?? $reasons['en'] ?? '');
    }

    public function invalidate(): void
    {
        try {
            Cache::increment(self::VERSION_KEY);
        } catch (\Throwable) {
            Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 1) + 1);
        }
    }

    public function forgetUser(User $user): void
    {
        $version = Cache::get(self::VERSION_KEY, 1);

        foreach (['permissions', 'roles'] as $type) {
            Cache::forget("pcdr:rbac:v{$version}:{$type}:{$user->id}");
        }
    }

    /**
     * Cache plain arrays so the payload survives Laravel's class-restricted
     * cache unserialization. Legacy object payloads are discarded and rebuilt.
     *
     * @param  callable(): list<string>  $loader
     * @return list<string>
     */
    private function rememberArray(string $key, callable $loader): array
    {
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        if ($cached !== null) {
            Cache::forget($key);
        }

        $value = Cache::rememberForever($key, $loader);

        return is_array($value) ? $value : $loader();
    }

    private function key(string $type, User $user): string
    {
        $version = Cache::get(self::VERSION_KEY, 1);

        return "pcdr:rbac:v{$version}:{$type}:{$user->id}";
    }
}
