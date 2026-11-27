<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Support\DemoSeedManifest;
use Database\Seeders\CmsRolesSeeder;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\HomepageContentSeeder;
use Database\Seeders\HomepagePrototypeContentSeeder;
use Database\Seeders\RedesignHomepageContentSeeder;
use Database\Seeders\SiteSettingsSeeder;
use Database\Seeders\WebsiteSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Single explicit entry point for the internally tracked demo dataset.
 *
 * Reference seeders are called directly so this seeder also works on a freshly
 * migrated database. They create non-demo-owned technical content only. The
 * normal DatabaseSeeder never calls this class, and the manifest guard refuses
 * production execution before any write occurs.
 */
final class DemoDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DemoSeedManifest::assertSeedable();

        app()->singleton(DemoSeedManifest::class, static fn (): DemoSeedManifest => new DemoSeedManifest);

        $manifest = app(DemoSeedManifest::class);

        Artisan::call('pcdr:permissions:sync', [
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $this->call(CmsRolesSeeder::class);
        $this->call(SiteSettingsSeeder::class);
        $this->call(WebsiteSeeder::class);
        $this->call(HomepageContentSeeder::class);
        $this->call(RedesignHomepageContentSeeder::class);
        $this->call(HomepagePrototypeContentSeeder::class);
        $this->call(EmailTemplatesSeeder::class);

        $this->call(DemoUsersSeeder::class);
        $this->call(DemoMediaSeeder::class);
        $this->call(DemoSettingsSeeder::class);
        $this->call(DemoContentSeeder::class);
        $this->call(DemoEngagementSeeder::class);
        $this->call(DemoComplaintsSeeder::class);
        $this->call(DemoDirectorySeeder::class);
        $this->call(DemoOperationsSeeder::class);
        $this->call(DemoPhase5Seeder::class);

        $manifest->markComplete();
    }
}
