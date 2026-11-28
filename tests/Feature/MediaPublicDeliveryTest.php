<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HomepageSection;
use App\Models\HomepageSectionItem;
use App\Models\Media;
use App\Models\User;
use App\Services\HomepageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class MediaPublicDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private const BOOTSTRAP_EMAIL = 'admin@example.test';

    /** A valid 1x1 PNG generated without GD. */
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=';

    /** @var list<string> */
    private array $uploadedPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cms.bootstrap.email', self::BOOTSTRAP_EMAIL);
        config()->set('cms.local_auth_enabled', true);

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        foreach ($this->uploadedPaths as $path) {
            Storage::disk('public')->delete($path);
        }

        parent::tearDown();
    }

    public function test_public_storage_link_targets_the_current_project_public_disk(): void
    {
        $this->assertSame(
            realpath(storage_path('app/public')),
            realpath(public_path('storage')),
        );
    }

    public function test_admin_uploaded_image_is_stored_and_publicly_retrievable_as_an_image(): void
    {
        $user = $this->loginAsBootstrap();
        $png = $this->pngContent();

        $this->post(route('cms.media.store', ['locale' => 'ar']), [
            'file' => UploadedFile::fake()->createWithContent('public-marker.png', $png),
            'translations' => [
                'ar' => ['alt_text' => 'صورة عامة', 'caption' => 'صورة للاختبار'],
                'en' => ['alt_text' => 'Public image', 'caption' => 'Test image'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $media = Media::query()->firstOrFail();
        $this->uploadedPaths[] = $media->path;

        $this->assertSame('public', $media->disk);
        $this->assertFalse((bool) $media->is_restricted);
        $this->assertSame($user->getKey(), $media->uploaded_by);
        $this->assertMatchesRegularExpression('#^media/\d{4}/\d{2}/[A-Za-z0-9]+\.png$#', $media->path);
        $this->assertTrue(Storage::disk('public')->exists($media->path));
        $this->assertSame($png, Storage::disk('public')->get($media->path));
        $this->assertSame(url('/storage/'.$media->path), $media->url());

        $this->get($media->url())
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->assertSame($png, file_get_contents(public_path('storage/'.$media->path)));
    }

    public function test_existing_public_media_references_resolve_to_their_physical_files(): void
    {
        $path = 'media/2026/11/legacy-reference-test.png';
        $png = $this->pngContent();
        $this->uploadedPaths[] = $path;
        Storage::disk('public')->put($path, $png);

        $media = Media::factory()->create([
            'uuid' => 'legacy-reference-test',
            'disk' => 'public',
            'path' => $path,
            'type' => 'image',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'is_restricted' => false,
        ]);

        $this->assertSame(url('/storage/'.$path), $media->url());
        $this->get($media->url())->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_missing_public_image_returns_not_found_instead_of_forbidden(): void
    {
        $this->get('/storage/media/2026/11/does-not-exist.png')->assertNotFound();
    }

    public function test_private_files_are_not_reachable_through_the_public_storage_url(): void
    {
        $privatePath = 'media/private-document-test.txt';
        Storage::disk('private')->put($privatePath, 'confidential');

        try {
            $this->assertFalse(Storage::disk('public')->exists($privatePath));
            $this->get('/storage/'.$privatePath)->assertNotFound();
        } finally {
            Storage::disk('private')->delete($privatePath);
        }
    }

    public function test_restricted_media_records_never_expose_a_public_storage_url(): void
    {
        $media = Media::factory()->create([
            'disk' => 'public',
            'path' => 'media/private/legacy-misplacement.pdf',
            'is_restricted' => true,
        ]);

        $this->assertStringContainsString('/media/'.$media->uuid.'/download', $media->url());
        $this->assertStringNotContainsString('/storage/', $media->url());
    }

    public function test_public_gallery_media_urls_are_retrievable_by_visitors(): void
    {
        $path = 'media/2026/11/public-gallery-test.png';
        $this->uploadedPaths[] = $path;
        Storage::disk('public')->put($path, $this->pngContent());

        $media = Media::factory()->create([
            'uuid' => 'public-gallery-test',
            'disk' => 'public',
            'path' => $path,
            'type' => 'image',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'is_restricted' => false,
        ]);

        $section = HomepageSection::create([
            'key' => 'albums',
            'page_key' => 'home',
            'type' => 'albums',
            'status' => 'published',
            'published_at' => now(),
            'is_enabled' => true,
            'display_order' => 6,
        ]);

        HomepageSectionItem::create([
            'homepage_section_id' => $section->id,
            'item_type' => 'inline',
            'item_key' => 'album-test',
            'media_id' => $media->id,
            'is_published' => true,
            'sort_order' => 0,
        ]);

        app(HomepageService::class)->flush();

        foreach (['ar', 'en'] as $locale) {
            $this->get("/{$locale}/media")
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('media.albums.items.0.media.url', $media->url()));

            $this->get($media->url())->assertOk()->assertHeader('Content-Type', 'image/png');
        }
    }

    public function test_restricted_library_items_keep_null_url_and_signed_download_requires_permission(): void
    {
        $this->loginAsBootstrap();
        Storage::fake('private');

        $path = 'media/private-library-test.pdf';
        Storage::disk('private')->put($path, 'confidential');

        $media = Media::factory()->create([
            'uuid' => 'private-library-test',
            'disk' => 'private',
            'path' => $path,
            'type' => 'document',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'original_name' => 'private-library-test.pdf',
            'is_restricted' => true,
        ]);

        try {
            $this->get(route('cms.media.index', ['locale' => 'ar', 'q' => 'private-library-test.pdf']))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('items.0.id', $media->getKey())
                    ->where('items.0.restricted', true)
                    ->where('items.0.url', null)
                    ->where('items.0.accessUrl', fn ($url): bool => str_contains((string) $url, 'signature='))
                    ->where('items.0.canPreview', true));

            $signedUrl = $this->get(route('cms.media.signed', [
                'locale' => 'ar',
                'media' => $media->getKey(),
            ]))->assertOk()->json('url');

            $this->assertIsString($signedUrl);
            $this->assertStringContainsString('signature=', $signedUrl);
            $this->assertStringNotContainsString('/storage/', $signedUrl);

            $this->get($signedUrl)->assertOk()->assertHeader('Content-Type', 'application/pdf');

            auth()->logout();
            $this->get($signedUrl)->assertForbidden();
        } finally {
            Storage::disk('private')->delete($path);
        }
    }

    private function loginAsBootstrap(): User
    {
        $user = User::factory()->create([
            'email' => self::BOOTSTRAP_EMAIL,
            'password' => Hash::make('password'),
        ]);
        auth()->login($user);

        return $user;
    }

    private function pngContent(): string
    {
        $content = base64_decode(self::PNG_BASE64, true);
        $this->assertNotFalse($content);

        return $content;
    }
}
