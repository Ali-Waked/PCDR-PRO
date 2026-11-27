<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use App\Services\RbacService;
use Database\Seeders\DemoAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DemoAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    private const NAME = 'Ali waked';

    private const EMAIL = 'info@pcdr.org';

    private const PASSWORD = 'mydtringPassword1@';

    protected function setUp(): void
    {
        parent::setUp();
        config(['preview.initialize' => false]);
    }

    public function test_it_creates_one_platform_owner_with_a_hashed_password(): void
    {
        $this->seed(DemoAdminSeeder::class);

        $owner = User::where('email', self::EMAIL)->firstOrFail();

        $this->assertSame(self::NAME, $owner->name);
        $this->assertTrue($owner->isPlatformOwner());
        $this->assertSame(User::STATUS_ACTIVE, $owner->status);
        $this->assertNotNull($owner->email_verified_at);
        $this->assertNotSame(self::PASSWORD, $owner->password);
        $this->assertTrue(Hash::check(self::PASSWORD, $owner->password));
        $this->assertDatabaseMissing('users', ['password' => self::PASSWORD]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform_owner.bootstrapped',
            'subject_id' => (string) $owner->id,
        ]);
        $this->assertSame(1, User::where('is_platform_owner', true)->count());
    }

    public function test_password_authentication_succeeds(): void
    {
        $this->seed(DemoAdminSeeder::class);

        $this->assertTrue(Auth::attempt([
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ]));
    }

    public function test_repeated_seeding_never_duplicates_the_account(): void
    {
        $this->seed(DemoAdminSeeder::class);
        $this->seed(DemoAdminSeeder::class);

        $this->assertSame(1, User::where('email', self::EMAIL)->count());
        $this->assertSame(1, User::where('is_platform_owner', true)->count());
    }

    public function test_existing_password_is_never_overwritten(): void
    {
        $originalHash = Hash::make('an-existing-password-123');
        $existing = User::forceCreate([
            'name' => 'Existing User',
            'email' => self::EMAIL,
            'password' => $originalHash,
            'status' => User::STATUS_ACTIVE,
        ]);

        try {
            $this->seed(DemoAdminSeeder::class);
            $this->fail('Seeding should have reported the email conflict.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('existing account', $exception->getMessage());
        }

        $this->assertSame($originalHash, $existing->fresh()->password);
        $this->assertFalse($existing->fresh()->isPlatformOwner());
        $this->assertSame(0, User::where('is_platform_owner', true)->count());
    }

    public function test_existing_email_conflict_is_reported_safely(): void
    {
        User::forceCreate([
            'name' => 'Existing User',
            'email' => self::EMAIL,
            'password' => Hash::make('an-existing-password-123'),
            'status' => User::STATUS_ACTIVE,
        ]);

        try {
            $this->seed(DemoAdminSeeder::class);
            $this->fail('Seeding should have reported the email conflict.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('existing account', $exception->getMessage());
        }
    }

    public function test_existing_platform_owner_blocks_an_additional_owner(): void
    {
        $existingOwner = User::factory()->platformOwner()->create([
            'email' => 'another-owner@example.test',
        ]);

        try {
            $this->seed(DemoAdminSeeder::class);
            $this->fail('Seeding should have refused to create a second owner.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Platform Owner', $exception->getMessage());
        }

        $this->assertSame(1, User::where('is_platform_owner', true)->count());
        $this->assertSame($existingOwner->id, User::where('is_platform_owner', true)->firstOrFail()->id);
        $this->assertSame(0, User::where('email', self::EMAIL)->count());
    }

    public function test_platform_owner_uses_the_existing_authorization_architecture(): void
    {
        $this->seed(DemoAdminSeeder::class);

        $owner = User::where('email', self::EMAIL)->firstOrFail();
        $rbac = app(RbacService::class);

        $this->assertSame(['platform_owner'], $rbac->rolesFor($owner)->all());
        $this->assertTrue($rbac->has($owner, 'users.view'));
        $this->assertDatabaseMissing('roles', ['key' => 'platform_owner']);
    }

    public function test_database_changes_roll_back_when_seeding_fails(): void
    {
        $seeder = new class extends DemoAdminSeeder
        {
            protected function recordCreation(User $user, AuditService $audit): void
            {
                throw new RuntimeException('forced audit failure');
            }
        };

        try {
            $seeder->run();
            $this->fail('The forced failure should have propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced audit failure', $exception->getMessage());
        }

        $this->assertSame(0, User::count());
        $this->assertSame(0, AuditLog::count());
    }
}
