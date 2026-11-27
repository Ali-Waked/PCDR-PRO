<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Support\DemoSeedManifest;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Private rehearsal settings and supplementary account/website setting rows.
 *
 * Rehearsal settings are deliberately outside config/settings.php and are
 * never published. Website settings are filled only when a key or translation
 * is missing or empty; administrator-managed values are left untouched.
 */
final class DemoSettingsSeeder extends Seeder
{
    /** @var list<string> */
    private const USER_EMAILS = [
        'layla.nasser@pcdr.test',
        'sami.haddad@pcdr.test',
        'hana.rafiq@pcdr.test',
        'karim.zuhair@pcdr.test',
        'nadia.qamar@pcdr.test',
        'omar.faisal@pcdr.test',
        'reem.adwan@pcdr.test',
        'tareq.shukri@pcdr.test',
    ];

    /** @var array<string, array{base: string, ar: string, en: string}> */
    private const REHEARSAL_SETTINGS = [
        'dataset.label' => [
            'base' => 'Programme reference dataset',
            'ar' => 'مجموعة مرجعية للبرامج',
            'en' => 'Programme reference dataset',
        ],
        'dataset.owner' => [
            'base' => 'Programme coordination unit',
            'ar' => 'وحدة تنسيق البرامج',
            'en' => 'Programme coordination unit',
        ],
        'dataset.scope' => [
            'base' => 'CMS, complaints and engagement records',
            'ar' => 'سجلات المحتوى والشكاوى والتفاعل',
            'en' => 'CMS, complaints and engagement records',
        ],
        'date_window' => [
            'base' => '2026-06-01/2026-08-31',
            'ar' => 'من 2026-06-01 إلى 2026-08-31',
            'en' => '2026-06-01 to 2026-08-31',
        ],
    ];

    /** @var array<string, array{group: string, type: string, localized: bool, value: ?string, translations: array<string, string>}> */
    private const WEBSITE_SETTINGS = [
        'contact.email' => [
            'group' => 'contact',
            'type' => 'email',
            'localized' => false,
            'value' => 'contact@pcdr.test',
            'translations' => [],
        ],
        'contact.phone' => [
            'group' => 'contact',
            'type' => 'text',
            'localized' => false,
            'value' => '+000 30 5550 1180',
            'translations' => [],
        ],
        'contact.office_hours' => [
            'group' => 'contact',
            'type' => 'text',
            'localized' => true,
            'value' => null,
            'translations' => [
                'ar' => 'نستقبل الاستفسارات خلال أيام العمل من التاسعة صباحًا حتى الخامسة مساءً.',
                'en' => 'Inquiries are received on working days from 09:00 to 17:00.',
            ],
        ],
    ];

    public function run(DemoSeedManifest $manifest): void
    {
        $this->seedRehearsalSettings($manifest);
        $this->seedUserEmailChanges($manifest);
        $this->seedWebsiteSettings($manifest);
    }

    private function seedRehearsalSettings(DemoSeedManifest $manifest): void
    {
        foreach (self::REHEARSAL_SETTINGS as $key => $definition) {
            $settingId = $manifest->ensureRow('settings', 'demo_rehearsal', 'demo_rehearsal.'.$key, [
                'group' => 'demo_rehearsal',
                'key' => $key,
                'type' => 'string',
                'value' => $definition['base'],
                'is_public' => false,
                'is_localized' => true,
                'is_secret_reference' => false,
                ...DemoSeedManifest::stamps(),
            ], ['group' => 'demo_rehearsal', 'key' => $key]);

            foreach (['ar', 'en'] as $locale) {
                $manifest->ensureRow('setting_translations', 'demo_rehearsal', 'demo_rehearsal.'.$key.'.'.$locale, [
                    'setting_id' => $settingId,
                    'locale' => $locale,
                    'value' => $definition[$locale],
                    ...DemoSeedManifest::stamps(),
                ], ['setting_id' => $settingId, 'locale' => $locale]);
            }
        }
    }

    private function seedUserEmailChanges(DemoSeedManifest $manifest): void
    {
        $users = $this->demoUserIds($manifest);
        $requests = [
            [
                'seed' => 'email-change-pending',
                'user' => 2,
                'new_email' => 'editor.updated@pcdr.test',
                'expires_at' => '2026-10-01 09:00:00',
                'consumed_at' => null,
            ],
            [
                'seed' => 'email-change-consumed',
                'user' => 4,
                'new_email' => 'publisher.updated@pcdr.test',
                'expires_at' => '2026-09-30 09:00:00',
                'consumed_at' => '2026-09-12 11:20:00',
            ],
        ];

        foreach ($requests as $request) {
            $userId = $this->assignedUserId($users, $request['user']);

            if ($userId === null) {
                continue;
            }

            $currentEmail = DB::table('users')->where('id', $userId)->value('email');

            if ($currentEmail === null) {
                continue;
            }

            $tokenHash = hash('sha256', DemoSeedManifest::fixedUlid($request['seed']));

            $manifest->ensureRow('user_email_changes', 'account', 'change:'.$request['seed'], [
                'user_id' => $userId,
                'current_email' => $currentEmail,
                'new_email' => $request['new_email'],
                'token_hash' => $tokenHash,
                'expires_at' => $request['expires_at'],
                'consumed_at' => $request['consumed_at'],
                ...DemoSeedManifest::stamps('2026-09-01 09:00:00'),
            ], ['token_hash' => $tokenHash]);
        }
    }

    private function seedWebsiteSettings(DemoSeedManifest $manifest): void
    {
        foreach (self::WEBSITE_SETTINGS as $key => $definition) {
            $settingId = $manifest->ensureRow('website_settings', 'website', 'key:'.$key, [
                'group' => $definition['group'],
                'key' => $key,
                'type' => $definition['type'],
                'is_localized' => $definition['localized'],
                'is_public' => true,
                'value' => $definition['value'],
                ...DemoSeedManifest::stamps(),
            ], ['key' => $key]);

            if ($definition['value'] !== null) {
                $this->fillEmptyValue('website_settings', $settingId, $definition['value']);
            }

            foreach ($definition['translations'] as $locale => $value) {
                $translationId = $manifest->ensureRow('website_setting_translations', 'website', 'key:'.$key.':'.$locale, [
                    'website_setting_id' => $settingId,
                    'locale' => $locale,
                    'value' => $value,
                    ...DemoSeedManifest::stamps(),
                ], ['website_setting_id' => $settingId, 'locale' => $locale]);

                $this->fillEmptyValue('website_setting_translations', $translationId, $value);
            }
        }
    }

    private function fillEmptyValue(string $table, int|string $id, string $value): void
    {
        DB::table($table)
            ->where('id', $id)
            ->where(static function (Builder $query): void {
                $query->whereNull('value')->orWhere('value', '');
            })
            ->update([
                'value' => $value,
                'updated_at' => DemoSeedManifest::BASE_TIME,
            ]);
    }

    /** @return list<int> */
    private function demoUserIds(DemoSeedManifest $manifest): array
    {
        $ids = [];

        foreach (self::USER_EMAILS as $email) {
            $value = $manifest->settingValue(DemoSeedManifest::GROUP, 'key:users:'.$email);

            if ($value !== null && ctype_digit($value) && (int) $value > 0) {
                $ids[(int) $value] = (int) $value;
            }
        }

        if ($ids === []) {
            $rows = DB::table('settings')
                ->where('group', DemoSeedManifest::GROUP)
                ->where('key', 'like', 'row:users:%')
                ->orderBy('id')
                ->pluck('value');

            foreach ($rows as $row) {
                $decoded = json_decode((string) $row, true);

                if (is_array($decoded) && isset($decoded['id']) && ctype_digit((string) $decoded['id'])) {
                    $id = (int) $decoded['id'];
                    $ids[$id] = (int) $id;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * @param  list<int>  $users
     */
    private function assignedUserId(array $users, int $index): ?int
    {
        return $users === [] ? null : $users[$index % count($users)];
    }
}
