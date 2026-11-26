<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\WebsiteSetting;
use App\Services\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SocialLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_stored_social_values_are_filtered_before_rendering(): void
    {
        $this->seedSocial('social.facebook', 'javascript:alert(1)');
        $this->seedSocial('social.tiktok', '@pcdr');
        app(SiteSettings::class)->flush();

        $links = app(SiteSettings::class)->socialLinks('en');

        $this->assertCount(1, $links);
        $this->assertSame('https://www.tiktok.com/@pcdr', $links[0]['url']);
    }

    private function seedSocial(string $key, ?string $value): void
    {
        WebsiteSetting::query()->create([
            'group' => 'social',
            'key' => $key,
            'type' => 'url',
            'is_public' => true,
            'is_localized' => false,
            'value' => $value,
        ]);
    }
}
