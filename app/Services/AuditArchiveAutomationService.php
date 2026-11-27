<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditArchive;
use App\Models\AuditLog;
use App\Models\WebsiteSetting;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Reads the archive automation section from the existing Website Settings
 * catalogue and calculates safe previous-calendar-month periods.
 */
final class AuditArchiveAutomationService
{
    public const GROUP = 'audit_automation';

    public const ACTION_CHECKED = 'audit_archives.automation_checked';

    public const ACTION_FAILED = 'audit_archives.automation_failed';

    /** @var array<string, mixed> */
    private const DEFAULTS = [
        'enabled' => false,
        'interval_months' => 1,
        'execution_day' => 2,
        'execution_time' => '02:30',
        'timezone' => 'UTC',
        'notifications_enabled' => true,
        'lookback_months' => 3,
    ];

    public function __construct(private readonly AuditService $audit) {}

    /** @return array<string, mixed> */
    public function configuration(): array
    {
        $rows = WebsiteSetting::query()
            ->where('group', self::GROUP)
            ->get()
            ->keyBy('key');

        $value = static function (string $key, string $type) use ($rows): mixed {
            $raw = $rows->get(self::GROUP.'.'.$key)?->value;
            if ($raw === null || $raw === '') {
                return null;
            }

            return match ($type) {
                'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
                'integer' => (int) $raw,
                default => (string) $raw,
            };
        };

        $timezone = (string) ($value('timezone', 'string') ?? config('app.timezone', 'UTC'));
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = (string) config('app.timezone', 'UTC');
        }

        $interval = (int) ($value('interval_months', 'integer') ?? self::DEFAULTS['interval_months']);
        $day = (int) ($value('execution_day', 'integer') ?? self::DEFAULTS['execution_day']);
        $lookback = (int) ($value('lookback_months', 'integer') ?? self::DEFAULTS['lookback_months']);
        $time = (string) ($value('execution_time', 'string') ?? self::DEFAULTS['execution_time']);

        return [
            'enabled' => (bool) ($value('enabled', 'boolean') ?? self::DEFAULTS['enabled']),
            'interval_months' => in_array($interval, [1, 2, 3], true) ? $interval : 1,
            'execution_day' => max(1, min(28, $day)),
            'execution_time' => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1 ? $time : '02:30',
            'timezone' => $timezone,
            'notifications_enabled' => (bool) ($value('notifications_enabled', 'boolean') ?? self::DEFAULTS['notifications_enabled']),
            'lookback_months' => max(1, min(12, $lookback)),
        ];
    }

    /**
     * Return completed, unarchived period keys. The interval defines the normal
     * cadence; the lookback window safely recovers missed scheduler runs.
     *
     * @return list<string>
     */
    public function duePeriods(?Carbon $now = null): array
    {
        $config = $this->configuration();
        if (! $config['enabled']) {
            return [];
        }

        $local = ($now ?? now())->copy()->setTimezone($config['timezone']);
        if ($local->day < $config['execution_day']) {
            return [];
        }

        if ($local->month % $config['interval_months'] !== 0) {
            return [];
        }

        $periods = [];
        for ($offset = 1; $offset <= $config['lookback_months']; $offset++) {
            $start = $local->copy()->startOfMonth()->subMonths($offset);
            $periods[] = $start->format('Y-m');
        }

        $existing = AuditArchive::query()
            ->whereIn('period_key', $periods)
            ->whereIn('status', [
                AuditArchive::STATUS_VERIFIED,
                AuditArchive::STATUS_PENDING,
                AuditArchive::STATUS_PROCESSING,
            ])
            ->pluck('period_key')
            ->all();

        $missing = array_values(array_diff($periods, $existing));
        sort($missing);

        return $missing;
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $config = $this->configuration();
        $lastCheck = AuditLog::query()
            ->where('action', self::ACTION_CHECKED)
            ->latest('created_at')
            ->first();

        $lastSuccess = AuditArchive::query()
            ->where('source', AuditArchive::SOURCE_AUTOMATIC)
            ->where('status', AuditArchive::STATUS_VERIFIED)
            ->latest('verified_at')
            ->first();

        $lastFailure = AuditArchive::query()
            ->where('source', AuditArchive::SOURCE_AUTOMATIC)
            ->where('status', AuditArchive::STATUS_FAILED)
            ->latest('failed_at')
            ->first();

        if (! $config['enabled']) {
            $health = 'disabled';
        } elseif ($lastFailure !== null && ($lastSuccess === null || $lastFailure->failed_at > $lastSuccess->verified_at)) {
            $health = 'degraded';
        } elseif ($lastCheck === null) {
            $health = 'not_started';
        } else {
            $health = 'healthy';
        }

        return [
            'enabled' => $config['enabled'],
            'interval_months' => $config['interval_months'],
            'execution_day' => $config['execution_day'],
            'execution_time' => $config['execution_time'],
            'timezone' => $config['timezone'],
            'notifications_enabled' => $config['notifications_enabled'],
            'lookback_months' => $config['lookback_months'],
            'next_expected_at' => $config['enabled'] ? $this->nextExpectedAt($config) : null,
            'last_execution_at' => $lastCheck?->created_at?->toISOString(),
            'last_successful_archive' => $lastSuccess?->period_key,
            'last_successful_at' => $lastSuccess?->verified_at?->toISOString(),
            'last_failure' => $lastFailure?->error,
            'last_failure_at' => $lastFailure?->failed_at?->toISOString(),
            'health' => $health,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function nextExpectedAt(array $config): ?string
    {
        $local = now()->copy()->setTimezone($config['timezone']);
        [$hour, $minute] = array_map('intval', explode(':', (string) $config['execution_time']));

        for ($offset = 0; $offset < 24; $offset++) {
            $candidate = $local->copy()
                ->startOfMonth()
                ->addMonths($offset)
                ->setDay((int) $config['execution_day'])
                ->setTime($hour, $minute, 0);

            if ($candidate->month % (int) $config['interval_months'] !== 0) {
                continue;
            }

            if ($candidate->greaterThanOrEqualTo($local)) {
                return $candidate->toISOString();
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $periods
     */
    public function recordCheck(array $periods, int $dispatched): void
    {
        $this->audit->record(self::ACTION_CHECKED, 'audit_archives', null, null, [
            'periods' => $periods,
            'dispatched' => $dispatched,
            'interval_months' => $this->configuration()['interval_months'],
            'description' => 'Automatic archive due-check completed',
        ]);
    }

    public function recordFailure(Throwable $exception): void
    {
        $this->audit->record(self::ACTION_FAILED, 'audit_archives', null, null, [
            'error' => mb_substr($exception->getMessage(), 0, 500),
            'description' => 'Automatic archive due-check failed',
        ]);
    }
}
