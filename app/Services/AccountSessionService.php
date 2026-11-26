<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AccountSessionService
{
    private const REFERENCE_PATTERN = '/^[a-f0-9]{32}$/';

    private const ANY_REFERENCE_PATTERN = '/^[A-Za-z0-9]{32}$/';

    public function currentReference(Request $request): ?string
    {
        $reference = $request->session()->get('account.session_reference');

        return is_string($reference) && preg_match(self::REFERENCE_PATTERN, $reference) === 1
            ? $reference
            : null;
    }

    /**
     * Public, user-scoped view of active sessions. Raw session IDs and the
     * encrypted payload are never read from the session store.
     *
     * @return list<array{reference: string, is_current: bool, ip_masked: string, device: string, browser: string, platform: string, first_seen_at: ?string, last_activity_at: ?string}>
     */
    public function activeSessions(User $user, string $locale, ?string $currentReference = null): array
    {
        try {
            $rows = $this->sessionsQuery()
                ->where('user_id', $user->id)
                ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp())
                ->orderByDesc('last_activity')
                ->limit(50)
                ->get(['public_reference', 'ip_address', 'user_agent', 'created_at', 'last_activity']);
        } catch (\Throwable) {
            return [];
        }

        $sessions = [];

        foreach ($rows as $row) {
            $reference = $row->public_reference ?? null;

            if (! is_string($reference) || preg_match(self::ANY_REFERENCE_PATTERN, $reference) !== 1) {
                continue;
            }

            $userAgent = is_string($row->user_agent ?? null) ? $row->user_agent : '';
            $lastActivityAt = $this->timestamp($row->last_activity ?? null);

            $sessions[] = [
                'reference' => $reference,
                'is_current' => $currentReference !== null && hash_equals($currentReference, $reference),
                'ip_masked' => $this->maskIp(is_string($row->ip_address ?? null) ? $row->ip_address : '', $locale),
                'device' => $this->translate('device', $this->device($userAgent), $locale),
                'browser' => $this->translate('browser', $this->browser($userAgent), $locale),
                'platform' => $this->translate('platform', $this->platform($userAgent), $locale),
                'first_seen_at' => $this->timestamp($row->created_at ?? null) ?? $lastActivityAt,
                'last_activity_at' => $lastActivityAt,
            ];
        }

        return $sessions;
    }

    public function count(User $user): int
    {
        try {
            return $this->sessionsQuery()
                ->where('user_id', $user->id)
                ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp())
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function revoke(User $user, string $reference): int
    {
        if (! $this->isReference($reference)) {
            return 0;
        }

        try {
            $deleted = $this->sessionsQuery()
                ->where('user_id', $user->id)
                ->where('public_reference', $reference)
                ->delete();
        } catch (\Throwable) {
            return 0;
        }

        if ($deleted > 0) {
            $this->rotateRememberToken($user);
        }

        return $deleted;
    }

    public function revokeOthers(User $user, string $currentReference): int
    {
        if (! $this->isReference($currentReference)) {
            return 0;
        }

        try {
            $deleted = $this->sessionsQuery()
                ->where('user_id', $user->id)
                ->where('public_reference', '!=', $currentReference)
                ->delete();
        } catch (\Throwable) {
            return 0;
        }

        if ($deleted > 0) {
            $this->rotateRememberToken($user);
        }

        return $deleted;
    }

    public function touch(User $user): void
    {
        $user->forceFill(['last_activity_at' => now()])->saveQuietly();
    }

    private function sessionsQuery(): Builder
    {
        return DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'));
    }

    private function isReference(string $reference): bool
    {
        return preg_match(self::REFERENCE_PATTERN, $reference) === 1
            || preg_match(self::ANY_REFERENCE_PATTERN, $reference) === 1;
    }

    private function rotateRememberToken(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }

    private function device(string $userAgent): string
    {
        return preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent) === 1
            ? 'mobile'
            : 'desktop';
    }

    private function browser(string $userAgent): string
    {
        return match (true) {
            preg_match('/Edg(?:e|A|iOS)?\//i', $userAgent) === 1 => 'edge',
            preg_match('/OPR\/|Opera/i', $userAgent) === 1 => 'opera',
            preg_match('/Firefox\//i', $userAgent) === 1 => 'firefox',
            preg_match('/Chrome\//i', $userAgent) === 1 => 'chrome',
            preg_match('/Safari\//i', $userAgent) === 1 => 'safari',
            default => 'unknown',
        };
    }

    private function platform(string $userAgent): string
    {
        return match (true) {
            preg_match('/Windows/i', $userAgent) === 1 => 'windows',
            preg_match('/Android/i', $userAgent) === 1 => 'android',
            preg_match('/iPhone|iPad|iPod/i', $userAgent) === 1 => 'ios',
            preg_match('/Macintosh|Mac OS X/i', $userAgent) === 1 => 'macos',
            preg_match('/Linux/i', $userAgent) === 1 => 'linux',
            default => 'unknown',
        };
    }

    /**
     * Prefer explicit translations; fall back to readable labels so no raw
     * translation key or user-agent fragment reaches the client.
     */
    private function translate(string $group, string $key, string $locale): string
    {
        foreach ([
            'ui.account_security.'.$group.'_'.$key,
            'ui.account_security.'.$group.'s.'.$key,
        ] as $translationKey) {
            $translation = __($translationKey, [], $locale);

            if (is_string($translation) && $translation !== '' && $translation !== $translationKey) {
                return $translation;
            }
        }

        return ucfirst(str_replace('_', ' ', $key));
    }

    private function maskIp(string $ip, string $locale): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $parts = explode('.', $ip);
            $parts[count($parts) - 1] = 'xxx';

            return implode('.', $parts);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $packed = @inet_pton($ip);

            if ($packed !== false && strlen($packed) === 16) {
                $groups = str_split(bin2hex($packed), 4);

                return strtolower($groups[0].':'.$groups[1]).':xxxx';
            }
        }

        return $this->translate('ip', 'unknown', $locale);
    }

    private function timestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestampUTC((int) $value)->toIso8601String();
        }

        try {
            return Carbon::parse((string) $value)->utc()->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
