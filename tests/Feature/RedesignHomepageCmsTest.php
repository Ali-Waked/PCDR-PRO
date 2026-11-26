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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RedesignHomepageCmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_update_requires_permission_and_persists_published_state(): void
    {
        $section = $this->createSection('hero');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('cms.homepage.status', ['locale' => 'ar', 'homepageSection' => $section]), ['status' => 'draft'])
            ->assertForbidden();

        $this->grantPermission($user, 'homepage.update');

        $this->actingAs($user)
            ->patch(route('cms.homepage.status', ['locale' => 'ar', 'homepageSection' => $section]), ['status' => 'draft'])
            ->assertRedirect();

        $this->assertSame('draft', $section->fresh()->status->value);
        $this->assertNull($section->fresh()->published_at);

        $this->actingAs($user)
            ->patch(route('cms.homepage.status', ['locale' => 'ar', 'homepageSection' => $section]), ['status' => 'published'])
            ->assertRedirect();

        $this->assertSame('published', $section->fresh()->status->value);
        $this->assertNotNull($section->fresh()->published_at);
    }

    public function test_toggle_requires_permission_and_preserves_item_content(): void
    {
        $section = $this->createSection('areas');
        $item = $section->items()->create([
            'item_type' => 'inline',
            'item_id' => null,
            'item_key' => 'area-label',
            'is_published' => true,
            'sort_order' => 3,
        ]);
        $item->translations()->create(['locale' => 'ar', 'label' => 'Label preserved', 'body' => 'Body preserved']);

        $user = User::factory()->create();
        $this->actingAs($user)
            ->post(route('cms.homepage.toggle', ['locale' => 'ar', 'homepageSection' => $section]), ['is_enabled' => false])
            ->assertForbidden();

        $this->grantPermission($user, 'homepage.update');
        $this->actingAs($user)
            ->post(route('cms.homepage.toggle', ['locale' => 'ar', 'homepageSection' => $section]), ['is_enabled' => false])
            ->assertRedirect();

        $this->assertFalse((bool) $section->fresh()->is_enabled);
        $this->assertSame('Label preserved', $item->fresh()->t('label', 'ar'));
        $this->assertSame('Body preserved', $item->fresh()->t('body', 'ar'));
        $this->assertSame(3, $item->fresh()->sort_order);
    }

    public function test_reorder_validates_keys_and_requires_permission(): void
    {
        $first = $this->createSection('hero', ['display_order' => 0]);
        $second = $this->createSection('intro', ['display_order' => 1]);
        $user = User::factory()->create();
        $this->grantPermission($user, 'homepage.update');

        $route = route('cms.homepage.reorder', ['locale' => 'ar']);

        $this->actingAs($user)
            ->post($route, ['order' => [['id' => 999999, 'display_order' => 0]]])
            ->assertSessionHasErrors('order.0.id');

        $this->actingAs($user)
            ->post($route, ['order' => [
                ['id' => $second->id, 'display_order' => 0],
                ['id' => $first->id, 'display_order' => 1],
            ]])
            ->assertRedirect();

        $this->assertSame(1, $first->fresh()->display_order);
        $this->assertSame(0, $second->fresh()->display_order);
    }

    public function test_preview_requires_permission_and_never_exposes_drafts_publicly(): void
    {
        $this->createSection('cta', ['status' => 'draft']);
        $user = User::factory()->create();
        $previewRoute = route('cms.homepage.preview', ['locale' => 'ar']);

        $this->actingAs($user)->get($previewRoute)->assertForbidden();

        $this->getJson('/api/public/homepage?locale=ar')
            ->assertOk()
            ->assertJsonCount(0, 'data.sections');

        $this->grantPermission($user, 'homepage.view');

        $this->actingAs($user)
            ->get($previewRoute)
            ->assertOk()
            ->assertJsonPath('data.locale', 'ar')
            ->assertJsonPath('data.dir', 'rtl')
            ->assertJsonPath('data.sections.0.key', 'cta')
            ->assertJsonPath('data.sections.0.status', 'draft');
    }

    public function test_update_rejects_unsafe_urls_and_allows_safe_item_replacement(): void
    {
        $section = $this->createSection('knowledge');
        $user = User::factory()->create();
        $this->grantPermission($user, 'homepage.update');

        $payload = [
            'sections' => [[
                'id' => $section->id,
                'is_enabled' => true,
                'display_order' => 0,
                'translations' => [
                    'en' => ['title' => 'Knowledge'],
                ],
                'items' => [[
                    'item_type' => 'inline',
                    'item_key' => 'safe-link',
                    'is_published' => true,
                    'sort_order' => 1,
                    'translations' => [
                        'en' => [
                            'label' => 'Safe link',
                            'url' => 'javascript:alert(1)',
                        ],
                    ],
                ]],
            ]],
        ];

        $updateRoute = route('cms.homepage.update', ['locale' => 'ar']);

        $this->actingAs($user)
            ->put($updateRoute, $payload)
            ->assertSessionHasErrors('sections.0.items.0.translations.en.url');

        $payload['sections'][0]['items'][0]['translations']['en']['url'] = 'https://example.org/safe';
        $payload['sections'][0]['items'][0]['media_id'] = '';

        $this->actingAs($user)
            ->put($updateRoute, $payload)
            ->assertRedirect();

        $item = $section->items()->firstOrFail();
        $this->assertSame('safe-link', $item->item_key);
        $this->assertSame('https://example.org/safe', $item->t('url', 'en'));
        $this->assertNull($item->media_id);
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
