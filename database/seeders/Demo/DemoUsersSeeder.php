<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Services\RbacService;
use App\Support\DemoSeedManifest;
use App\Support\ScopeValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Fictional staff accounts for the preview dataset.
 *
 * The credentials below are development-only and must never be reused for a
 * real deployment. No real officeholder is represented, and this seeder only
 * assigns the functional CMS roles created by CmsRolesSeeder; it does not
 * create permissions or institutional approval authority.
 */
final class DemoUsersSeeder extends Seeder
{
    private const STATUS_ACTIVE = 'active';

    private const STATUS_SUSPENDED = 'suspended';

    /** @var array<string, array{name: string, email: string, password: string, locale: string, status: string, is_platform_owner: bool, roles: list<string>}> */
    private const ACCOUNTS = [
        'owner' => [
            'name' => 'Layla Nasser',
            'email' => 'layla.nasser@pcdr.test',
            'password' => 'Owner!Preview2026#Q7k',
            'locale' => 'ar',
            'status' => self::STATUS_ACTIVE,
            'is_platform_owner' => true,
            'roles' => ['website_administrator'],
        ],
        'admin' => [
            'name' => 'Sami Haddad',
            'email' => 'sami.haddad@pcdr.test',
            'password' => 'Admin!Preview2026#M4p',
            'locale' => 'ar',
            'status' => self::STATUS_ACTIVE,
            'is_platform_owner' => false,
            'roles' => ['website_administrator'],
        ],
        'editor' => [
            'name' => 'Hana Rafiq',
            'email' => 'hana.rafiq@pcdr.test',
            'password' => 'Editor!Preview2026#T8v',
            'locale' => 'ar',
            'status' => self::STATUS_ACTIVE,
            'is_platform_owner' => false,
            'roles' => ['content_editor'],
        ],
        'publisher' => [
            'name' => 'Karim Zuhair',
            'email' => 'karim.zuhair@pcdr.test',
            'password' => 'Publish!Preview2026#W2b',
            'locale' => 'en',
            'status' => self::STATUS_ACTIVE,
            'is_platform_owner' => false,
            'roles' => ['publisher_reviewer'],
        ],
        'contact-manager' => [
            'name' => 'Nadia Qamar',
            'email' => 'nadia.qamar@pcdr.test',
            'password' => 'Contact!Preview2026#J6s',
            'locale' => 'ar',
            'status' => self::STATUS_ACTIVE,
            'is_platform_owner' => false,
            'roles' => ['contact_manager'],
        ],
        'support' => [
            'name' => 'Omar Faisal',
            'email' => 'omar.faisal@pcdr.test',
            'password' => 'Support!Preview2026#L9d',
            'locale' => 'en',
            'status' => self::STATUS_ACTIVE,
            'is_platform_owner' => false,
            'roles' => ['contact_support'],
        ],
        'complaints-officer' => [
            'name' => 'Reem Adwan',
            'email' => 'reem.adwan@pcdr.test',
            'password' => 'Complaints!Preview2026#R3n',
            'locale' => 'ar',
            'status' => self::STATUS_ACTIVE,
            'is_platform_owner' => false,
            'roles' => ['contact_manager', 'contact_support'],
        ],
        'suspended' => [
            'name' => 'Tareq Shukri',
            'email' => 'tareq.shukri@pcdr.test',
            'password' => 'Suspended!Preview2026#B5x',
            'locale' => 'en',
            'status' => self::STATUS_SUSPENDED,
            'is_platform_owner' => false,
            'roles' => ['content_editor'],
        ],
    ];

    public function run(DemoSeedManifest $manifest): void
    {
        /** @var array<string, int|string> $userIds */
        $userIds = [];

        foreach (self::ACCOUNTS as $key => $account) {
            $userIds[$key] = $manifest->ensureRow(
                'users',
                'users',
                $key,
                [
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'password' => Hash::make($account['password']),
                    'email_verified_at' => DemoSeedManifest::BASE_TIME,
                    'status' => $account['status'],
                    'locale' => $account['locale'],
                    'is_platform_owner' => $account['is_platform_owner'],
                    'failed_login_attempts' => 0,
                    ...DemoSeedManifest::stamps(),
                ],
                ['email' => $account['email']],
            );

            $manifest->putSetting(
                DemoSeedManifest::GROUP,
                'key:users:'.$account['email'],
                (string) $userIds[$key],
            );
        }

        $this->assignRoles($manifest, $userIds);
        $this->assignRoleScopes($manifest, $userIds);

        $rbac = app(RbacService::class);

        if (method_exists($rbac, 'invalidate')) {
            $rbac->invalidate();
        }
    }

    /**
     * @param  array<string, int|string>  $userIds
     */
    private function assignRoles(DemoSeedManifest $manifest, array $userIds): void
    {
        foreach ($userIds as $key => $userId) {
            $assignedBy = $key === 'owner' ? $userIds['admin'] : $userIds['owner'];

            foreach (self::ACCOUNTS[$key]['roles'] as $roleKey) {
                $roleId = DB::table('roles')->where('key', $roleKey)->value('id');

                if (! is_int($roleId) && ! is_string($roleId)) {
                    throw new RuntimeException("Missing CMS role [{$roleKey}] for preview account [{$key}].");
                }

                $manifest->ensurePivot(
                    'role_user',
                    ['user_id' => $userId, 'role_id' => $roleId],
                    [
                        'assigned_by' => $assignedBy,
                        'expires_at' => $key === 'suspended' ? '2026-08-15 09:00:00' : null,
                        ...DemoSeedManifest::stamps(),
                    ],
                );
            }
        }
    }

    /**
     * @param  array<string, int|string>  $userIds
     */
    private function assignRoleScopes(DemoSeedManifest $manifest, array $userIds): void
    {
        $scopes = [
            ['website_administrator', '*', 'all', []],
            ['content_editor', 'articles', 'self', []],
            ['publisher_reviewer', 'articles', 'all', []],
            ['contact_manager', 'complaints', 'assigned', ['user_ids' => [$userIds['admin'], $userIds['support']]]],
            ['contact_manager', 'contact_messages', 'assigned', ['user_ids' => [$userIds['contact-manager'], $userIds['support']]]],
            ['contact_support', 'complaints', 'self', []],
        ];

        foreach ($scopes as [$roleKey, $resourceKey, $scopeType, $scopeValue]) {
            $roleId = DB::table('roles')->where('key', $roleKey)->value('id');

            if (! is_int($roleId) && ! is_string($roleId)) {
                throw new RuntimeException("Missing CMS role [{$roleKey}] for preview scope.");
            }

            $valueHash = ScopeValue::hash($scopeValue);
            $naturalKey = $roleKey.':'.$resourceKey.':'.$scopeType;

            $manifest->ensureRow(
                'role_scopes',
                'role_scope',
                $naturalKey,
                [
                    'role_id' => $roleId,
                    'resource_key' => $resourceKey,
                    'scope_type' => $scopeType,
                    'scope_value' => ScopeValue::canonicalJson($scopeValue),
                    'scope_value_hash' => $valueHash,
                    ...DemoSeedManifest::stamps(),
                ],
                [
                    'role_id' => $roleId,
                    'resource_key' => $resourceKey,
                    'scope_type' => $scopeType,
                    'scope_value_hash' => $valueHash,
                ],
            );
        }
    }
}
