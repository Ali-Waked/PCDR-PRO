<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MediaType;
use App\Models\HomepageSection;
use App\Models\Media;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessManagementService;
use App\Services\HomepageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class RedesignHomepageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_public_endpoint_returns_locale_and_direction_without_auth(): void
    {
        $this->getJson('/api/public/homepage?locale=ar')
            ->assertOk()
            ->assertJsonPath('data.locale', 'ar')
            ->assertJsonPath('data.dir', 'rtl')
            ->assertJsonPath('data.sections', []);

        $this->getJson('/api/public/homepage?locale=en')
            ->assertOk()
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.dir', 'ltr');
    }

    public function test_public_endpoint_excludes_drafts_and_disabled_sections(): void
    {
        $this->createSection('hero', ['is_enabled' => true, 'status' => 'published']);
        $this->createSection('intro', ['is_enabled' => true, 'status' => 'draft']);
        $this->createSection('areas', ['is_enabled' => false, 'status' => 'published']);

        $response = $this->getJson('/api/public/homepage?locale=en');

        $response->assertOk()->assertJsonCount(1, 'data.sections');
        $response->assertJsonPath('data.sections.0.key', 'hero');
    }

    public function test_public_endpoint_exposes_only_approved_item_fields_and_media_url(): void
    {
        $media = $this->createImage('api-approved.png');
        $section = $this->createSection('partners', ['is_enabled' => true, 'status' => 'published']);
        $item = $section->items()->create([
            'item_type' => 'inline',
            'item_id' => null,
            'media_id' => $media->id,
            'item_key' => 'partner-one',
            'is_published' => true,
            'numeric_value' => 12.5,
            'sort_order' => 1,
        ]);
        $item->translations()->create([
            'locale' => 'en',
            'label' => 'Approved partner',
            'title' => 'Partner title',
            'body' => 'Approved body',
            'display_value' => '12.5',
            'url' => 'https://example.org/partner',
        ]);

        $response = $this->getJson('/api/public/homepage?locale=en');

        $response->assertOk();
        $itemData = $response->json('data.sections.0.items.0');
        $this->assertSame('partner-one', $itemData['key']);
        $this->assertSame('Approved partner', $itemData['label']);
        $this->assertSame('Partner title', $itemData['title']);
        $this->assertSame('https://example.org/partner', $itemData['url']);
        $this->assertSame(12.5, $itemData['value']);
        $this->assertArrayHasKey('media', $itemData);
        $this->assertSame($media->id, $itemData['media']['id']);
        $this->assertStringContainsString('api-approved.png', $itemData['media']['url']);
        $this->assertArrayNotHasKey('translations', $itemData);
        $this->assertArrayNotHasKey('item_type', $itemData);
    }

    public function test_public_endpoint_reflects_cms_changes_after_cache_flush(): void
    {
        $section = $this->createSection('hero', ['is_enabled' => true, 'status' => 'published']);
        $section->translations()->create(['locale' => 'en', 'title' => 'Hero']);

        $this->getJson('/api/public/homepage?locale=en')
            ->assertOk()
            ->assertJsonPath('data.sections.0.title', 'Hero');

        $section->translations()->updateOrCreate(['locale' => 'en'], ['title' => 'Updated hero title']);
        app(HomepageService::class)->flush();

        $this->getJson('/api/public/homepage?locale=en')
            ->assertOk()
            ->assertJsonPath('data.sections.0.title', 'Updated hero title');
    }

    public function test_public_endpoint_rate_limits_repeated_requests(): void
    {
        for ($requestNumber = 0; $requestNumber < 61; $requestNumber++) {
            $response = $this->getJson('/api/public/homepage?locale=en');
        }

        $response->assertStatus(429);
    }

    public function test_cms_homepage_index_sees_drafts_and_requires_permission(): void
    {
        $this->createSection('cta', ['is_enabled' => true, 'status' => 'draft']);

        $this->actingAs(User::factory()->create())
            ->get(route('cms.homepage.index', ['locale' => 'ar']))
            ->assertForbidden();

        $editor = User::factory()->create();
        $this->grantPermission($editor, 'homepage.view');

        $this->actingAs($editor)
            ->get(route('cms.homepage.index', ['locale' => 'ar']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cms/Homepage/Index')
                ->has('sections', 1)
                ->where('sections.0.key', 'cta')
                ->where('sections.0.status', 'draft')
            );
    }

    private function grantPermission(User $user, string $permission): void
    {
        $role = Role::create(['key' => 'test-'.$permission, 'name' => 'Test '.$permission]);
        $role->permissions()->attach(Permission::create([
            'key' => $permission,
            'name' => $permission,
            'module' => 'website',
        ]));

        app(AccessManagementService::class)->syncRoles($user, [$role->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSection(string $key, array $overrides = []): HomepageSection
    {
        return HomepageSection::create(array_merge([
            'key' => $key,
            'type' => $key,
            'status' => 'published',
            'published_at' => now(),
            'is_enabled' => true,
            'display_order' => 0,
        ], $overrides));
    }

    private function createImage(string $name): Media
    {
        return Media::create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'public',
            'path' => 'media/api/'.$name,
            'type' => MediaType::Image->value,
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => 1024,
            'width' => 800,
            'height' => 600,
            'checksum' => hash('sha256', $name),
            'original_name' => $name,
            'is_restricted' => false,
        ]);
    }
}
