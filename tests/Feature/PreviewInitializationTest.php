<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PreviewInitializationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'preview.initialize' => false,
            'cms.local_auth_enabled' => false,
        ]);
    }

    public function test_preview_initialization_migrates_seeds_once_and_is_restart_safe(): void
    {
        config(['preview.initialize' => true]);

        $this->artisan('pcdr:preview:initialize')->assertExitCode(0);
        $this->assertSame(1, User::where('email', 'info@pcdr.org')->count());
        $this->assertTrue(User::where('email', 'info@pcdr.org')->firstOrFail()->isPlatformOwner());
        $this->assertTrue(Setting::query()
            ->where('group', 'preview')
            ->where('key', 'initialized_at')
            ->exists());

        $before = $this->tableCounts();

        // A second container start must be a no-op for seeded data.
        $this->artisan('pcdr:preview:initialize')->assertExitCode(0);

        $this->assertSame($before, $this->tableCounts());
        $this->assertSame(1, User::where('email', 'info@pcdr.org')->count());
        $this->assertSame(1, User::where('is_platform_owner', true)->count());
    }

    public function test_production_default_migrates_without_seeding_demo_data(): void
    {
        $this->artisan('pcdr:preview:initialize')->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertSame(0, User::where('email', 'info@pcdr.org')->count());
        $this->assertFalse(Setting::query()
            ->where('group', 'preview')
            ->where('key', 'initialized_at')
            ->exists());
    }

    public function test_database_seeder_does_not_seed_demo_admin_without_opt_in(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::where('email', 'info@pcdr.org')->count());
    }

    public function test_no_seed_option_applies_migrations_only(): void
    {
        config(['preview.initialize' => true]);

        $this->artisan('pcdr:preview:initialize', ['--no-seed' => true])->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertSame(0, User::where('email', 'info@pcdr.org')->count());
        $this->assertFalse(Setting::query()
            ->where('group', 'preview')
            ->where('key', 'initialized_at')
            ->exists());
    }

    /** @return array<string, int> */
    private function tableCounts(): array
    {
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");

        $counts = [];

        foreach ($tables as $table) {
            $name = $table->name;
            $counts[$name] = DB::table($name)->count();
        }

        return $counts;
    }
}
