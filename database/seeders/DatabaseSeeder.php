<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed technical reference data and website reference content only.
     * The dedicated demo dataset is never invoked here; it is opt-in through
     * Database\Seeders\Demo\DemoDatabaseSeeder and requires both preview flags.
     * Bootstrap the Platform Owner with
     * `php artisan pcdr:platform-owner:bootstrap`.
     */
    public function run(): void
    {
        Artisan::call('pcdr:permissions:sync');

        $this->call(SiteSettingsSeeder::class);
        $this->call(WebsiteSeeder::class);
        $this->call(HomepageContentSeeder::class);
        $this->call(RedesignHomepageContentSeeder::class);
        $this->call(HomepagePrototypeContentSeeder::class);
        $this->call(CmsStructureSeeder::class);
        $this->call(CmsRolesSeeder::class);
        $this->call(EmailTemplatesSeeder::class);

        // Local development identity only. Never seeded in production and
        // never created unless the development-only CMS shim is enabled.
        if (app()->environment(['local', 'testing']) && config('cms.local_auth_enabled')) {
            $email = config('cms.bootstrap.email') ?: 'test@example.test';
            $password = env('CMS_LOCAL_PASSWORD', 'password');

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'Test User',
                    'password' => Hash::make($password),
                ],
            );

            $user->forceFill(['password' => Hash::make($password)])->save();
        }
    }
}
