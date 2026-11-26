<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\WebsiteSetting;
use App\Services\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class SocialLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_public_contact_exposes_normalized_localized_social_links(): void
    {
        $this->seedSocial('social.facebook', ' @pcdr ');
        $this->seedSocial('social.youtube', 'youtube.com/watch?v=abc123XYZ');
        $this->seedSocial('social.whatsapp', '+970 59 123 4567');
        app(SiteSettings::class)->flush();

        $this->get('/ar/contact')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Contact')
                ->where('site.social', function ($links): bool {
                    $links = collect($links);

                    return $links->count() === 3
                        && $links->contains(fn (array $link): bool => $link['platform'] === 'facebook'
                            && $link['label'] === 'فيسبوك'
                            && $link['url'] === 'https://www.facebook.com/pcdr')
                        && $links->contains(fn (array $link): bool => $link['platform'] === 'youtube'
                            && $link['label'] === 'يوتيوب'
                            && $link['url'] === 'https://www.youtube.com/watch?v=abc123XYZ')
                        && $links->contains(fn (array $link): bool => $link['platform'] === 'whatsapp'
                            && $link['label'] === 'واتساب'
                            && $link['url'] === 'https://wa.me/970591234567');
                }));

        $this->get('/en/contact')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.social', fn ($links): bool => collect($links)
                    ->contains(fn (array $link): bool => $link['platform'] === 'facebook' && $link['label'] === 'Facebook')));
    }

    public function test_invalid_stored_social_values_are_filtered_before_rendering(): void
    {
        $this->seedSocial('social.facebook', 'javascript:alert(1)');
        $this->seedSocial('social.tiktok', '@pcdr');
        app(SiteSettings::class)->flush();

        $links = app(SiteSettings::class)->socialLinks('en');

        $this->assertCount(1, $links);
        $this->assertSame('https://www.tiktok.com/@pcdr', $links[0]['url']);
    }

    public function test_public_components_share_one_icon_map_and_expose_safe_link_attributes(): void
    {
        $component = (string) file_get_contents(resource_path('js/Components/Public/SocialLinks.vue'));
        $icons = (string) file_get_contents(resource_path('js/Components/Public/social-icons.js'));
        $footer = (string) file_get_contents(resource_path('js/Components/Public/SiteFooter.vue'));
        $contact = (string) file_get_contents(resource_path('js/Pages/Public/Contact.vue'));

        foreach (['target="_blank"', 'rel="noopener noreferrer"', ':aria-label="linkLabel(link)"', 'focusable="false"', 'socialIcons'] as $marker) {
            $this->assertStringContainsString($marker, $component, $marker.' missing from SocialLinks.vue.');
        }

        foreach (['facebook', 'x', 'instagram', 'linkedin', 'youtube', 'tiktok', 'snapchat', 'whatsapp'] as $platform) {
            $this->assertStringContainsString($platform.': {', $icons, $platform.' icon missing.');
        }

        $this->assertStringContainsString('<SocialLinks :links="social" compact />', $footer);
        $this->assertStringNotContainsString('v-for="link in social"', $footer);
        $this->assertStringContainsString('<SocialLinks :links="site.social" tone="light" />', $contact);
        $this->assertStringNotContainsString('class="ps-pill"', $contact);
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
