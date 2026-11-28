<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HomepageSection;
use App\Models\HomepageSectionItem;
use App\Models\Media;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsitePage;
use App\Services\AccessManagementService;
use App\Services\HomepageService;
use App\Services\WebsiteStructureService;
use Database\Seeders\CmsStructureSeeder;
use Database\Seeders\HeroMediaRepairSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CmsIntegrityAndHeroMediaTest extends TestCase
{
    use RefreshDatabase;

    /** A valid 1x1 PNG generated without GD. */
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=';

    /** @var list<string> */
    private array $uploadedPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(CmsStructureSeeder::class);
        app(HomepageService::class)->flush();
    }

    protected function tearDown(): void
    {
        foreach ($this->uploadedPaths as $path) {
            Storage::disk('public')->delete($path);
        }

        parent::tearDown();
    }

    public function test_every_registered_section_has_a_reachable_management_destination(): void
    {
        $user = $this->editor(['website_structure.view', 'homepage.view', 'homepage.update']);
        $definitions = (array) config('cms_structure.sections', []);

        foreach (array_keys($definitions) as $key) {
            $key = (string) $key;
            $label = (string) ($definitions[$key]['label']['en'] ?? $key);

            $this->actingAs($user)
                ->get(route('cms.content.index', ['locale' => 'en', 'section' => $key]))
                ->assertOk()
                ->assertInertia(fn (Assert $page): Assert => $page
                    ->component('Cms/ContentManagement/Section')
                    ->where('section.key', $key)
                    ->where('section.name', $label)
                    ->where('can.update', true));
        }

        $this->actingAs($user)
            ->get('/en/cms/content/not-a-registered-section')
            ->assertNotFound();
    }

    public function test_structure_counts_partition_records_without_hiding_any_content(): void
    {
        $section = $this->section('hero');
        $eligible = $this->heroItem('slide-eligible');
        $hidden = $this->heroItem('slide-hidden', ['is_published' => false]);
        $scheduled = $this->heroItem('slide-scheduled', ['display_start_at' => now()->addDay()]);
        $expired = $this->heroItem('slide-expired', ['display_end_at' => now()->subDay()]);
        $control = $this->heroItem('primary');
        $legacy = $this->heroItem('ticker-legacy');

        foreach ([$eligible, $hidden, $scheduled, $expired, $control, $legacy] as $item) {
            $item->translations()->create(['locale' => 'en', 'title' => $item->item_key]);
        }

        app(HomepageService::class)->flush();

        $structure = app(WebsiteStructureService::class)->structure('en');
        $hero = collect($structure['sections'])->firstWhere('key', 'hero');

        $this->assertSame(6, $hero['recordCount']);
        $this->assertSame(3, $hero['eligibleCount']);
        $this->assertSame(3, $hero['visibleCount']);
        $this->assertSame(1, $hero['hiddenCount']);
        $this->assertSame(1, $hero['unpublishedCount']);
        $this->assertSame(1, $hero['scheduledCount']);
        $this->assertSame(1, $hero['expiredCount']);
        $this->assertSame(4, $hero['slideCount']);
        $this->assertSame(1, $hero['eligibleSlideCount']);
        $this->assertSame(6, $section->items()->count());

        $public = collect(app(HomepageService::class)->sections('en'))->firstWhere('key', 'hero');
        $this->assertSame(['slide-eligible', 'primary', 'ticker-legacy'], array_column($public['items'], 'key'));
        $this->assertSame(['slide-eligible'], array_values(array_filter(
            array_column($public['items'], 'key'),
            static fn (string $key): bool => str_starts_with($key, 'slide-'),
        )));

        WebsitePage::query()->where('key', 'home')->update(['is_enabled' => false]);
        $hiddenPage = collect(app(WebsiteStructureService::class)->structure('en')['sections'])
            ->firstWhere('key', 'hero');
        $this->assertSame(0, $hiddenPage['visibleCount']);
        $this->assertSame(6, $hiddenPage['recordCount']);
        WebsitePage::query()->where('key', 'home')->update(['is_enabled' => true]);

        $section->update(['is_enabled' => false]);
        $hiddenSection = collect(app(WebsiteStructureService::class)->structure('en')['sections'])
            ->firstWhere('key', 'hero');
        $this->assertSame(0, $hiddenSection['visibleCount']);
        $this->assertSame(6, $hiddenSection['recordCount']);
        $section->update(['is_enabled' => true]);
    }

    public function test_public_hero_media_is_delivered_without_admin_session_and_replacement_persists(): void
    {
        $firstImage = $this->publicImage('hero-first.png', 'media/cms/hero-first-test.png');
        $replacementImage = $this->publicImage('hero-replacement.png', 'media/cms/hero-replacement-test.png');
        $item = $this->heroItem('slide-media', ['media_id' => $firstImage->id]);
        $item->translations()->create(['locale' => 'en', 'title' => 'Covered hero']);

        app(HomepageService::class)->flush();

        $this->get('/en')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('sections.0.key', 'hero')
                ->where('sections.0.items.0.slide', true)
                ->where('sections.0.items.0.media.url', $firstImage->url()));

        $this->get($firstImage->url())
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $user = $this->editor(['homepage.view', 'homepage.update']);

        $this->actingAs($user)
            ->put($this->contentRoute('update', 'hero', ['sectionItem' => $item->id]), [
                'item_type' => 'inline',
                'is_published' => true,
                'sort_order' => 1,
                'media_id' => $replacementImage->id,
                'translations' => ['en' => ['title' => 'Replaced hero image']],
            ])
            ->assertRedirect();

        $this->assertSame($replacementImage->id, (int) $item->fresh()->media_id);

        app(HomepageService::class)->flush();

        $this->get('/en')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('sections.0.items.0.media.url', $replacementImage->url()));

        $this->get($replacementImage->url())
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_media_repair_seeder_assigns_only_missing_seeded_hero_slides(): void
    {
        Storage::fake('public');

        $missing = $this->heroItem('slide-1');
        $existingImage = Media::query()->create([
            'uuid' => (string) Str::ulid(),
            'disk' => 'public',
            'path' => 'media/cms/existing-hero.jpg',
            'type' => 'image',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 1024,
            'checksum' => hash('sha256', 'existing-hero'),
            'original_name' => 'existing-hero.jpg',
            'is_restricted' => false,
        ]);
        $preserved = $this->heroItem('slide-2', ['media_id' => $existingImage->id]);

        $this->seed(HeroMediaRepairSeeder::class);

        $repaired = $missing->fresh();
        $this->assertNotNull($repaired->media_id);
        $this->assertNotSame($existingImage->id, (int) $repaired->media_id);
        $this->assertSame('media/hero/slide-1.jpg', $repaired->media->path);
        $this->assertFalse((bool) $repaired->media->is_restricted);
        Storage::disk('public')->assertExists('media/hero/slide-1.jpg');
        $this->assertSame($existingImage->id, (int) $preserved->fresh()->media_id);

        $mediaCount = Media::query()->count();
        $this->seed(HeroMediaRepairSeeder::class);
        $this->assertSame($mediaCount, Media::query()->count());
        $this->assertSame((int) $repaired->media_id, (int) $missing->fresh()->media_id);
    }

    public function test_frontend_renders_backend_slide_membership_as_images_with_fallback(): void
    {
        $carousel = (string) File::get(resource_path('js/Components/RedesignHome/ProHeroCarousel.vue'));

        foreach ([
            'item.slide === true',
            'content.mediaUrl(item)',
            'class="hero__image"',
            ':src="slide.media"',
            '@error="imageFailed(slide)"',
            'hero__image-fallback',
        ] as $needle) {
            $this->assertStringContainsString($needle, $carousel, $needle);
        }

        $this->assertStringNotContainsString("startsWith('slide-')", $carousel);
        $this->assertStringNotContainsString('--photo', $carousel);

        $css = (string) File::get(resource_path('css/pcdr-prototype.css'));
        $this->assertStringContainsString('object-fit:cover', $css);
        $this->assertStringNotContainsString('.pcdr-prototype .slide::before', $css);

        $editor = (string) File::get(resource_path('js/Pages/Cms/ContentManagement/HeroSection.vue'));
        foreach ([
            'item.isSlide === true',
            'v-for="(item, index) in records"',
            'recordStatus(item)',
            'scheduleState',
        ] as $needle) {
            $this->assertStringContainsString($needle, $editor, $needle);
        }
    }

    /** @param list<string> $permissions */
    private function editor(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'key' => 'cms-integrity-'.Str::lower(Str::random(10)),
            'name' => 'CMS integrity tester',
        ]);

        foreach ($permissions as $permission) {
            $record = Permission::query()->firstOrCreate(
                ['key' => $permission],
                ['name' => $permission, 'module' => 'website'],
            );
            $role->permissions()->attach($record);
        }

        app(AccessManagementService::class)->syncRoles($user, [$role->id]);

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function heroItem(string $key, array $overrides = []): HomepageSectionItem
    {
        return $this->section('hero')->items()->create(array_merge([
            'item_type' => 'inline',
            'item_key' => $key,
            'is_published' => true,
            'sort_order' => 1,
        ], $overrides));
    }

    private function publicImage(string $name, string $path): Media
    {
        $bytes = base64_decode(self::PNG_BASE64, true);
        $this->assertNotFalse($bytes);
        Storage::disk('public')->put($path, $bytes, 'public');
        $this->uploadedPaths[] = $path;

        return Media::query()->create([
            'uuid' => (string) Str::ulid(),
            'disk' => 'public',
            'path' => $path,
            'type' => 'image',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => strlen((string) $bytes),
            'width' => 1,
            'height' => 1,
            'checksum' => hash('sha256', $name),
            'original_name' => $name,
            'is_restricted' => false,
        ]);
    }

    private function contentRoute(string $name, string $section, array $parameters = []): string
    {
        return route('cms.content.'.$name, array_merge([
            'locale' => 'en',
            'section' => $section,
        ], $parameters));
    }

    private function section(string $key): HomepageSection
    {
        return HomepageSection::query()->where('key', $key)->firstOrFail();
    }
}
