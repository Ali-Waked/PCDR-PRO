<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Internal ownership registry for the dedicated demo dataset.
 *
 * All markers live in the existing private settings table. Nothing in this
 * class adds columns to application tables or exposes ownership through CMS
 * serialization: SettingsService only publishes keys declared in
 * config/settings.php, and demo_registry is deliberately absent there.
 */
final class DemoSeedManifest
{
    public const GROUP = 'demo_registry';

    public const VERSION = '2026.11.27.1';

    public const BASE_TIME = '2026-09-01 09:00:00';

    /** @return array<string, string> */
    public static function stamps(string $at = self::BASE_TIME): array
    {
        return ['created_at' => $at, 'updated_at' => $at];
    }

    public static function fixedUlid(string $seed): string
    {
        return strtoupper(substr(hash('sha256', 'pcdr-demo:'.$seed), 0, 26));
    }

    public static function assertSeedable(): void
    {
        if (app()->isProduction()
            || app()->environment('production')
            || config('app.env') === 'production'
            || env('APP_ENV') === 'production') {
            throw new RuntimeException('Refusing to seed demo data: APP_ENV=production. Demo seeding is development/testing only.');
        }

        if (config('preview.initialize') !== true) {
            throw new RuntimeException('Refusing to seed demo data: PCDR_PREVIEW_INIT must be true.');
        }

        if (config('preview.demo_seed') !== true) {
            throw new RuntimeException('Refusing to seed demo data: PCDR_DEMO_SEED must be true.');
        }
    }

    public static function assertCleanable(): void
    {
        if (app()->isProduction()
            || app()->environment('production')
            || config('app.env') === 'production'
            || env('APP_ENV') === 'production') {
            throw new RuntimeException('Refusing to clean demo data: APP_ENV=production. Demo cleanup is development/testing only.');
        }

        if (config('preview.demo_seed') !== true) {
            throw new RuntimeException('Refusing to clean demo data: PCDR_DEMO_SEED must be true.');
        }
    }

    /**
     * Insert a row once and remember its natural key and database id.
     *
     * Existing rows that are not already tracked are reused but never adopted
     * as demo-owned. This protects administrator-created records from cleanup.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $lookup
     */
    public function ensureRow(
        string $table,
        string $module,
        string $naturalKey,
        array $attributes,
        array $lookup = [],
        ?callable $resolver = null,
        string $idColumn = 'id',
    ): int|string {
        $keySetting = $this->keySetting($module, $naturalKey);
        $trackedId = $this->settingValue(self::GROUP, $keySetting);

        if ($trackedId !== null && $trackedId !== '' && $this->rowExists($table, $idColumn, $trackedId)) {
            return $this->castIdentifier($trackedId);
        }

        if ($trackedId !== null && $trackedId !== '') {
            $this->forgetRow($table, $idColumn, $trackedId);
            $this->forgetSetting(self::GROUP, $keySetting);
        }

        if ($resolver !== null) {
            $resolved = $resolver();

            if ($resolved !== null && $this->rowExists($table, $idColumn, (string) $resolved)) {
                return $this->castIdentifier((string) $resolved);
            }
        }

        if ($lookup !== []) {
            $query = DB::table($table);

            foreach ($lookup as $column => $value) {
                $query->where($column, $value);
            }

            $existing = $query->first();

            if ($existing !== null) {
                return $this->castIdentifier((string) $existing->{$idColumn});
            }
        }

        $providedId = $attributes[$idColumn] ?? null;

        if ($providedId !== null && $providedId !== '') {
            DB::table($table)->insert($attributes);
            $id = (string) $providedId;
        } else {
            $id = (string) DB::table($table)->insertGetId($attributes);
        }

        $this->putSetting(self::GROUP, $keySetting, $id);
        $this->trackRow($table, $idColumn, $id, $module.':'.$naturalKey);

        return $this->castIdentifier($id);
    }

    /**
     * Insert a pivot row once, without ever adopting untracked rows.
     *
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $attributes
     */
    public function ensurePivot(string $table, array $match, array $attributes = []): void
    {
        if ($this->pivotExists($table, $match)) {
            return;
        }

        $values = array_merge($match, $attributes);

        if (! array_key_exists('created_at', $values)) {
            $values['created_at'] = self::BASE_TIME;
        }

        if (! array_key_exists('updated_at', $values)) {
            $values['updated_at'] = self::BASE_TIME;
        }

        DB::table($table)->insert($values);

        $key = $this->registryKey('pivot:'.$table, $this->encodeKey($match));
        $this->putSetting(self::GROUP, $key, $this->encode($match), 'json');
    }

    /**
     * Remember a generated media-library file so cleanup can remove it safely.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function trackFile(string $naturalKey, string $disk, string $path, array $metadata): void
    {
        $checksum = (string) ($metadata['checksum'] ?? '');

        if ($checksum === '') {
            throw new RuntimeException('Demo file tracking requires a checksum.');
        }

        $fileKey = 'file:'.$checksum;

        if ($this->settingValue(self::GROUP, $fileKey) !== null) {
            return;
        }

        $this->putSetting(self::GROUP, $fileKey, $this->encode(array_merge($metadata, [
            'disk' => $disk,
            'path' => $path,
        ])), 'json');
        $this->putSetting(self::GROUP, $this->keySetting('file', $naturalKey), $checksum);
    }

    /**
     * Register a row inserted outside ensureRow(), so cleanup can remove it.
     */
    public function trackExistingRow(string $table, string $naturalKey, string $idColumn, int|string $id): void
    {
        $this->trackRow($table, $idColumn, (string) $id, $naturalKey);
    }

    /** @return list<array{table: string, id_column: string, id: string, natural_key: string}> */
    public function rows(): array
    {
        $rows = [];

        $settings = DB::table('settings')
            ->where('group', self::GROUP)
            ->where('key', 'like', 'row:%')
            ->orderBy('id')
            ->get(['key', 'value']);

        foreach ($settings as $setting) {
            $decoded = json_decode((string) $setting->value, true);

            if (! is_array($decoded)
                || ! isset($decoded['table'], $decoded['id'], $decoded['id_column'])) {
                continue;
            }

            $rows[] = [
                'table' => (string) $decoded['table'],
                'id_column' => (string) $decoded['id_column'],
                'id' => (string) $decoded['id'],
                'natural_key' => (string) ($decoded['natural_key'] ?? ''),
            ];
        }

        return $rows;
    }

    /** @return list<array{table: string, match: array<string, mixed>}> */
    public function pivots(): array
    {
        $pivots = [];

        $settings = DB::table('settings')
            ->where('group', self::GROUP)
            ->where('key', 'like', 'pivot:%')
            ->orderBy('id')
            ->get(['key', 'value']);

        foreach ($settings as $setting) {
            $decoded = json_decode((string) $setting->value, true);

            if (! is_array($decoded) || ! str_starts_with((string) $setting->key, 'pivot:')) {
                continue;
            }

            $tail = substr((string) $setting->key, strlen('pivot:'));
            $separator = strpos($tail, ':');
            $table = $separator === false ? $tail : substr($tail, 0, $separator);

            $pivots[] = [
                'table' => $table,
                'match' => $decoded,
            ];
        }

        return $pivots;
    }

    /** @return list<array{disk: string, path: string, checksum: string, natural_key: string}> */
    public function files(): array
    {
        $files = [];

        $settings = DB::table('settings')
            ->where('group', self::GROUP)
            ->where('key', 'like', 'file:%')
            ->orderBy('id')
            ->get(['value']);

        foreach ($settings as $setting) {
            $decoded = json_decode((string) $setting->value, true);

            if (! is_array($decoded)
                || ! isset($decoded['disk'], $decoded['path'], $decoded['checksum'])) {
                continue;
            }

            $files[] = [
                'disk' => (string) $decoded['disk'],
                'path' => (string) $decoded['path'],
                'checksum' => (string) $decoded['checksum'],
                'natural_key' => (string) ($decoded['natural_key'] ?? ''),
            ];
        }

        return $files;
    }

    public function markComplete(?string $version = null): void
    {
        $this->putSetting(self::GROUP, 'run:version', $version ?? self::VERSION);
        $this->putSetting(self::GROUP, 'run:completed_at', now()->toISOString());
        $this->flushCache();
    }

    public function completionVersion(): ?string
    {
        return $this->settingValue(self::GROUP, 'run:version');
    }

    public function clearManifest(): int
    {
        $deleted = DB::table('settings')->where('group', self::GROUP)->delete();
        $this->flushCache();

        return $deleted;
    }

    public function flushCache(): void
    {
        app(SettingsService::class)->flushCache();
        Cache::forget('pcdr:navigation');
    }

    public function putSetting(string $group, string $key, mixed $value, string $type = 'string', bool $public = false): void
    {
        $stored = is_string($value) ? $value : $this->encode($value);

        DB::table('settings')->updateOrInsert(
            ['group' => $group, 'key' => $key],
            [
                'type' => $type,
                'value' => $stored,
                'is_public' => $public,
                'is_localized' => false,
                'is_secret_reference' => false,
                'created_at' => self::BASE_TIME,
                'updated_at' => self::BASE_TIME,
            ],
        );
    }

    public function forgetSetting(string $group, string $key): void
    {
        DB::table('settings')->where('group', $group)->where('key', $key)->delete();
    }

    public function settingValue(string $group, string $key): ?string
    {
        $setting = DB::table('settings')
            ->where('group', $group)
            ->where('key', $key)
            ->first('value');

        return $setting === null ? null : (string) $setting->value;
    }

    /** @param array<string, mixed> $match */
    private function pivotExists(string $table, array $match): bool
    {
        $query = DB::table($table);

        foreach ($match as $column => $value) {
            $query->where($column, $value);
        }

        return $query->exists();
    }

    private function rowExists(string $table, string $idColumn, string $id): bool
    {
        return DB::table($table)->where($idColumn, $id)->exists();
    }

    private function forgetRow(string $table, string $idColumn, string $id): void
    {
        DB::table('settings')
            ->where('group', self::GROUP)
            ->where('key', 'like', 'row:'.$table.':%')
            ->where('value', 'like', '%"id":"'.$id.'"%')
            ->delete();

        DB::table($table)->where($idColumn, $id)->delete();
    }

    private function trackRow(string $table, string $idColumn, string $id, string $naturalKey): void
    {
        $key = $this->registryKey('row:'.$table, $id);

        $this->putSetting(self::GROUP, $key, $this->encode([
            'table' => $table,
            'id_column' => $idColumn,
            'id' => $id,
            'natural_key' => $naturalKey,
        ]), 'json');
    }

    private function keySetting(string $module, string $naturalKey): string
    {
        return $this->registryKey('key:'.$module, $naturalKey);
    }

    private function registryKey(string $prefix, string $value): string
    {
        $key = $prefix.':'.$value;

        if (strlen($key) <= 160) {
            return $key;
        }

        return $prefix.':'.hash('sha256', $value);
    }

    private function encodeKey(array $values): string
    {
        ksort($values);

        return (string) json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function encode(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function castIdentifier(string $id): int|string
    {
        return ctype_digit($id) ? (int) $id : $id;
    }
}
