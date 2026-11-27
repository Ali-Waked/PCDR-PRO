<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Services\AuditService;
use App\Services\RbacService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Temporary-preview Platform Owner bootstrap.
 *
 * The credentials are intentionally scoped to this seeder and must never be
 * reused for a real deployment. The account is created through the same
 * User model, RBAC service and audit contract used by
 * `pcdr:platform-owner:bootstrap`; no second authorization mechanism is added.
 */
class DemoAdminSeeder extends Seeder
{
    private const NAME = 'Ali waked';

    private const EMAIL = 'info@pcdr.org';

    private const PASSWORD = 'mydtringPassword1@';

    public function run(): void
    {
        if (User::query()->where('is_platform_owner', true)->exists()) {
            $existingOwner = User::query()->where('email', self::EMAIL)->first();

            if ($existingOwner?->isPlatformOwner()) {
                // Idempotent repeat: the demo owner is already present.
                return;
            }

            throw new RuntimeException(
                'Demo admin seeding skipped: a different Platform Owner already exists.'
            );
        }

        if (User::withTrashed()->where('email', self::EMAIL)->exists()) {
            throw new RuntimeException(
                'Demo admin seeding skipped: the configured email belongs to an existing account.'
            );
        }

        $owner = DB::transaction(function (): User {
            // Re-check under lock so concurrent starts cannot create two owners.
            if (User::query()->where('is_platform_owner', true)->lockForUpdate()->exists()) {
                throw new RuntimeException('Demo admin seeding skipped: a Platform Owner was created concurrently.');
            }

            if (User::withTrashed()->where('email', self::EMAIL)->lockForUpdate()->exists()) {
                throw new RuntimeException('Demo admin seeding skipped: the configured email was claimed concurrently.');
            }

            $user = User::forceCreate([
                'name' => self::NAME,
                'email' => self::EMAIL,
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'status' => User::STATUS_ACTIVE,
                'locale' => 'ar',
                'is_platform_owner' => true,
            ]);

            // Same cache invalidation and audit action as the bootstrap command.
            app(RbacService::class)->invalidate();
            $this->recordCreation($user, app(AuditService::class));

            return $user;
        });

        $this->command?->info('Demo Platform Owner is ready ('.$owner->email.').');
    }

    /**
     * Kept overridable so transaction rollback can be verified in tests.
     */
    protected function recordCreation(User $user, AuditService $audit): void
    {
        $audit->record('platform_owner.bootstrapped', 'users', (string) $user->id, null, [
            'email' => $user->email,
        ]);
    }
}
