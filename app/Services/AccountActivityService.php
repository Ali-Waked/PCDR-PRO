<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class AccountActivityService
{
    private const PER_PAGE = 15;

    private const MAX_PAGE = 10000;

    /**
     * Presentation-safe account activity. Raw IP addresses, user agents,
     * request IDs and before/after snapshots are never returned.
     *
     * @return array{
     *     data: list<array{id: int, action: string, label: string, created_at: ?string, device: string, ip_masked: string}>,
     *     links: array<string, mixed>,
     *     from: ?int,
     *     to: ?int,
     *     total: int
     * }
     */
    public function paginate(User $user, string $locale, int $page = 1): array
    {
        $page = max(1, min($page, self::MAX_PAGE));

        $paginator = AuditLog::query()
            ->select(['id', 'action', 'subject_type', 'subject_id', 'created_at', 'context'])
            ->where(function (Builder $query) use ($user): void {
                $query->where('actor_id', $user->id)
                    ->orWhere(function (Builder $nested) use ($user): void {
                        $nested->where('subject_type', 'users')
                            ->where('subject_id', (string) $user->id);
                    });
            })
            ->where(function (Builder $query): void {
                $query->where('action', 'like', 'auth.%')
                    ->orWhere('action', 'like', 'account.%')
                    ->orWhere('action', 'like', 'profile.%')
                    ->orWhere('action', 'like', 'user.%');
            })
            ->latest('created_at')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page)
            ->through(fn (AuditLog $log): array => $this->event($log, $locale))
            ->toArray();

        return [
            'data' => $paginator['data'],
            'links' => $paginator['links'],
            'from' => $paginator['from'] ?? null,
            'to' => $paginator['to'] ?? null,
            'total' => (int) $paginator['total'],
        ];
    }

    /** @return array{id: int, action: string, label: string, created_at: ?string, device: string, ip_masked: string} */
    private function event(AuditLog $log, string $locale): array
    {
        $action = (string) $log->action;
        $suffix = str_replace('.', '_', $action);
        if (preg_match('/^[A-Za-z0-9_]+$/', $suffix) !== 1) {
            $suffix = 'other';
        }

        $translationKey = 'ui.account_activity.action_'.$suffix;
        $label = __($translationKey, [], $locale);
        if (! is_string($label) || $label === '' || $label === $translationKey) {
            $fallback = __('ui.account_activity.other', [], $locale);
            $label = is_string($fallback) && $fallback !== '' && $fallback !== 'ui.account_activity.other'
                ? $fallback
                : 'Account activity';
        }

        $context = $log->context;
        $userAgent = is_array($context) && is_string($context['user_agent'] ?? null)
            ? $context['user_agent']
            : '';
        $ip = is_array($context) ? ($context['ip'] ?? null) : null;

        return [
            'id' => (int) $log->id,
            'action' => $suffix,
            'label' => $label,
            'created_at' => $log->created_at?->toIso8601String(),
            'device' => $this->device($userAgent, $locale),
            'ip_masked' => $this->maskIp($ip, $locale),
        ];
    }

    private function device(string $userAgent, string $locale): string
    {
        $key = preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent) === 1
            ? 'mobile'
            : 'desktop';

        foreach ([
            'ui.account_activity.device_'.$key,
            'ui.account_activity.devices.'.$key,
        ] as $translationKey) {
            $translation = __($translationKey, [], $locale);
            if (is_string($translation) && $translation !== '' && $translation !== $translationKey) {
                return $translation;
            }
        }

        return ucfirst($key);
    }

    private function maskIp(mixed $ip, string $locale): string
    {
        $value = is_string($ip) ? $ip : '';

        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $parts = explode('.', $value);
            $parts[count($parts) - 1] = 'xxx';

            return implode('.', $parts);
        }

        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $packed = @inet_pton($value);
            if ($packed !== false && strlen($packed) === 16) {
                $groups = str_split(bin2hex($packed), 4);

                return strtolower($groups[0].':'.$groups[1]).':xxxx';
            }
        }

        $unknown = __('ui.account_activity.unknown_ip', [], $locale);

        return is_string($unknown) && $unknown !== '' && $unknown !== 'ui.account_activity.unknown_ip'
            ? $unknown
            : 'Unknown';
    }
}
