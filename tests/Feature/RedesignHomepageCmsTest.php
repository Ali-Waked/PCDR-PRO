<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\DeleteMedia;
use App\Enums\MediaType;
use App\Exceptions\MediaInUseException;
use App\Models\HomepageSection;
use App\Models\Media;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessManagementService;
use App\Services\HomepageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RedesignHomepageCmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->withoutVite();
    }

    public function test_legacy_homepage_editor_routes_are_removed(): void
    {
        foreach ([
            'cms.homepage.index',
            'cms.homepage.preview',
            'cms.homepage.update',
            'cms.homepage.reorder',
            'cms.homepage.status',
            'cms.homepage.toggle',
        ] as $routeName) {
            $this->assertFalse(Route::has($routeName), $routeName);
        }

        $owner = User::factory()->platformOwner()->create();

        $this->actingAs($owner)
            ->get('/ar/cms/homepage')
            ->assertNotFound();

        $this->actingAs($owner)
            ->get('/en/cms/homepage/preview')
            ->assertNotFound();
    }

    public function test_delete_media_is_blocked_while_a_homepage_item_references_it(): void
    {
        $media = $this->createImage('protected.png');
        $section = $this->createSection('community');
        $section->items()->create([
            'item_type' => 'inline',
            'item_key' => 'cover',
            'media_id' => $media->id,
            'is_published' => true,
            'sort_order' => 1,
        ]);

        $this->expectException(MediaInUseException::class);

        app(DeleteMedia::class)->handle($media);
    }

    public function test_media_translation_update_flushes_homepage_cache(): void
    {
        $media = $this->createImage('cached.png');
        $media->translations()->create(['locale' => 'en', 'alt_text' => 'Old alt']);
        $section = $this->createSection('albums');
        $section->items()->create([
            'item_type' => 'inline',
            'item_key' => 'cover',
            'media_id' => $media->id,
            'is_published' => true,
            'sort_order' => 1,
        ]);

        app(HomepageService::class)->sections('en');
        $this->assertTrue(Cache::has('homepage.sections.en.enabled.published'));

        $user = User::factory()->create();
        $this->grantPermission($user, 'media.update');

        $this->actingAs($user)
            ->put(route('cms.media.update', ['locale' => 'en', 'media' => $media->id]), [
                'translations' => ['en' => ['alt_text' => 'New alt']],
            ])
            ->assertRedirect();

        $this->assertFalse(Cache::has('homepage.sections.en.enabled.published'));
        $item = $section->items()->firstOrFail();
        $this->assertSame('New alt', $item->media->t('alt_text', 'en'));
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

    /** @param array<string, mixed> $overrides */
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
            'path' => 'media/cms/'.$name,
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
