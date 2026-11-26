<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AccountAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', '12345678901234567890123456789012');

        $this->withoutVite();
    }

    public function test_valid_png_upload_creates_a_restricted_private_media_row_and_signed_url(): void
    {
        Storage::fake('private');

        $user = User::factory()->create(['name' => 'Avatar Owner']);

        $this->actingAs($user)->post('/ar/dashboard/profile/avatar', [
            'avatar' => $this->pngUpload(),
        ])->assertRedirect();

        $media = Media::query()->where('uploaded_by', $user->id)->latest('id')->firstOrFail();
        $this->assertSame('private', $media->disk);
        $this->assertTrue((bool) $media->is_restricted);
        Storage::disk('private')->assertExists($media->path);

        $this->actingAs($user)->get('/ar/dashboard/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Profile/Show')
                ->where('profile.avatar_url', fn ($value): bool => is_string($value)
                    && str_contains($value, '/dashboard/profile/avatar/')
                    && str_contains($value, 'signature=')));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.avatar.uploaded',
            'actor_id' => $user->id,
        ]);
    }

    public function test_invalid_mime_types_and_oversized_files_are_rejected(): void
    {
        Storage::fake('private');

        $user = User::factory()->create();

        $this->actingAs($user)->post('/ar/dashboard/profile/avatar', [
            'avatar' => UploadedFile::fake()->createWithContent('avatar.png', 'plain text file')->mimeType('text/plain'),
        ])->assertRedirect()->assertSessionHasErrors('avatar');

        $this->actingAs($user)->post('/ar/dashboard/profile/avatar', [
            'avatar' => UploadedFile::fake()->createWithContent('avatar.php', '<?php echo "unsafe";')->mimeType('application/x-php'),
        ])->assertRedirect()->assertSessionHasErrors('avatar');

        $this->actingAs($user)->post('/ar/dashboard/profile/avatar', [
            'avatar' => UploadedFile::fake()->createWithContent('avatar.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')
                ->mimeType('image/svg+xml'),
        ])->assertRedirect()->assertSessionHasErrors('avatar');

        $this->actingAs($user)->post('/ar/dashboard/profile/avatar', [
            'avatar' => UploadedFile::fake()->createWithContent('huge.png', str_repeat('x', 3 * 1024 * 1024))
                ->mimeType('image/png'),
        ])->assertRedirect()->assertSessionHasErrors('avatar');

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_replacing_avatar_deletes_the_previous_file_when_it_is_unused(): void
    {
        Storage::fake('private');

        $user = User::factory()->create();

        $this->actingAs($user)->post('/ar/dashboard/profile/avatar', ['avatar' => $this->pngUpload('first.png')])
            ->assertRedirect();

        $oldMedia = Media::query()->where('uploaded_by', $user->id)->latest('id')->firstOrFail();
        $oldPath = $oldMedia->path;
        Storage::disk('private')->assertExists($oldPath);

        $this->actingAs($user)->post('/ar/dashboard/profile/avatar', ['avatar' => $this->pngUpload('second.png')])
            ->assertRedirect();

        $newMedia = Media::query()->where('uploaded_by', $user->id)->latest('id')->firstOrFail();
        $this->assertNotSame($oldMedia->id, $newMedia->id);
        Storage::disk('private')->assertMissing($oldPath);
        Storage::disk('private')->assertExists($newMedia->path);
    }

    public function test_removing_avatar_detaches_it_deletes_the_file_and_writes_audit(): void
    {
        Storage::fake('private');

        $user = User::factory()->create(['password' => 'current-password-123']);

        $this->actingAs($user)->post('/ar/dashboard/profile/avatar', ['avatar' => $this->pngUpload()])
            ->assertRedirect();

        $media = Media::query()->where('uploaded_by', $user->id)->latest('id')->firstOrFail();
        $path = $media->path;

        $this->actingAs($user)->delete('/ar/dashboard/profile/avatar', [
            'current_password' => 'current-password-123',
        ])->assertRedirect();

        Storage::disk('private')->assertMissing($path);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.avatar.removed',
            'actor_id' => $user->id,
        ]);

        $this->actingAs($user)->get('/ar/dashboard/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('profile.avatar_url', null));
    }

    public function test_avatar_download_requires_a_valid_signature_and_ownership(): void
    {
        Storage::fake('private');

        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post('/ar/dashboard/profile/avatar', ['avatar' => $this->pngUpload()])
            ->assertRedirect();

        $media = Media::query()->where('uploaded_by', $user->id)->latest('id')->firstOrFail();
        $contents = Storage::disk('private')->get($media->path);
        $url = URL::signedRoute('account.avatar.download', [
            'locale' => 'ar',
            'media' => $media->getKey(),
        ]);

        $this->actingAs($user)->get($url)->assertOk()->assertStreamedContent($contents);

        $unsigned = route('account.avatar.download', [
            'locale' => 'ar',
            'media' => $media->getKey(),
        ]);
        $this->actingAs($user)->get($unsigned)->assertForbidden();

        $otherResponse = $this->actingAs($other)->get($url);
        self::assertNotSame(200, $otherResponse->getStatusCode());
    }

    public function test_avatar_props_and_audit_entries_never_expose_the_private_path(): void
    {
        Storage::fake('private');

        $user = User::factory()->create();

        $this->actingAs($user)->post('/ar/dashboard/profile/avatar', ['avatar' => $this->pngUpload()])
            ->assertRedirect();

        $media = Media::query()->where('uploaded_by', $user->id)->latest('id')->firstOrFail();

        $response = $this->actingAs($user)->get('/ar/dashboard/profile')->assertOk();

        $payload = str_replace('\\/', '/', (string) $response->getContent());
        $this->assertStringNotContainsString($media->path, $payload);
        $this->assertStringNotContainsString('storage/', $payload);

        $serialized = json_encode(AuditLog::query()->get(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($media->path, $serialized);
        $this->assertStringNotContainsString('account.session_reference', $serialized);
    }

    private function pngUpload(string $name = 'avatar.png'): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
        self::assertNotFalse($png);

        /** @var File $file */
        $file = UploadedFile::fake()->createWithContent($name, $png)->mimeType('image/png');

        return $file;
    }
}
