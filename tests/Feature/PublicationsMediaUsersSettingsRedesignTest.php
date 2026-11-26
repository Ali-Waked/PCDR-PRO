<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Permission;
use App\Models\Publication;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessManagementService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PublicationsMediaUsersSettingsRedesignTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALE = 'ar';

    private const BOOTSTRAP_EMAIL = 'editor@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cms.bootstrap.email', self::BOOTSTRAP_EMAIL);
        config()->set('cms.local_auth_enabled', true);
        Storage::fake('public');
        Storage::fake('private');
    }

    public function test_publication_restriction_validation_and_signed_restricted_downloads_are_enforced(): void
    {
        $this->actingAs($this->bootstrapUser());

        $publicDocument = $this->createMedia('public-guide.pdf', MediaType::Document, false);

        $this->from(route('cms.publications.create', ['locale' => self::LOCALE]))
            ->post(route('cms.publications.store', ['locale' => self::LOCALE]), $this->publicationPayload([
                'is_restricted' => true,
                'document_media_id' => null,
            ]))
            ->assertRedirect(route('cms.publications.create', ['locale' => self::LOCALE]))
            ->assertSessionHasErrors('document_media_id');

        $this->from(route('cms.publications.create', ['locale' => self::LOCALE]))
            ->post(route('cms.publications.store', ['locale' => self::LOCALE]), $this->publicationPayload([
                'is_restricted' => true,
                'document_media_id' => $publicDocument->id,
            ]))
            ->assertRedirect(route('cms.publications.create', ['locale' => self::LOCALE]))
            ->assertSessionHasErrors('is_restricted');

        $restrictedDocument = $this->createMedia('restricted-guide.pdf', MediaType::Document, true, 'private/restricted-guide.pdf');
        Storage::disk('private')->put('private/restricted-guide.pdf', '%PDF-1.4 restricted');

        $publication = Publication::create([
            'category' => 'policy',
            'status' => ContentStatus::Published,
            'published_at' => now()->subDay(),
            'publication_date' => now()->toDateString(),
            'document_media_id' => $restrictedDocument->id,
            'version' => 'v1',
            'is_restricted' => true,
            'display_order' => 0,
        ]);
        $publication->translations()->create([
            'locale' => self::LOCALE,
            'slug' => 'restricted-publication',
            'title' => 'Restricted publication',
            'description' => 'Restricted content',
        ]);

        $downloadUrl = URL::temporarySignedRoute(
            'public.media.download',
            now()->addMinutes(30),
            ['locale' => self::LOCALE, 'uuid' => $restrictedDocument->uuid],
        );

        auth()->logout();
        $this->get($downloadUrl)->assertForbidden();

        $viewer = $this->userWith(['publications.view', 'media.view']);
        $this->actingAs($viewer)->get($downloadUrl)->assertOk();

        $this->actingAs($viewer)
            ->get(route('public.media.download', ['locale' => self::LOCALE, 'uuid' => $restrictedDocument->uuid]))
            ->assertForbidden();
    }

    public function test_media_upload_enforces_mime_rules_reference_protection_and_private_signed_links(): void
    {
        $this->actingAs($this->bootstrapUser());

        $this->from(route('cms.media.index', ['locale' => self::LOCALE]))
            ->post(route('cms.media.store', ['locale' => self::LOCALE]), [
                'file' => UploadedFile::fake()->create('script.txt', 10, 'text/plain'),
                'is_restricted' => false,
            ])
            ->assertRedirect(route('cms.media.index', ['locale' => self::LOCALE]))
            ->assertSessionHasErrors('file');

        $this->post(route('cms.media.store', ['locale' => self::LOCALE]), [
            'file' => UploadedFile::fake()->create('guide.pdf', 120, 'application/pdf'),
            'is_restricted' => true,
        ])->assertRedirect();

        /** @var Media $media */
        $media = Media::query()->where('original_name', 'guide.pdf')->firstOrFail();
        $this->assertTrue($media->is_restricted);
        $this->assertSame(MediaType::Document, $media->type);
        Storage::disk('private')->assertExists($media->path);

        $publication = Publication::create([
            'category' => 'policy',
            'status' => ContentStatus::Draft,
            'document_media_id' => $media->id,
            'is_restricted' => true,
            'display_order' => 0,
        ]);
        $publication->translations()->create([
            'locale' => self::LOCALE,
            'slug' => 'draft-policy',
            'title' => 'Draft policy',
        ]);

        $this->from(route('cms.media.index', ['locale' => self::LOCALE]))
            ->delete(route('cms.media.destroy', ['locale' => self::LOCALE, 'media' => $media->id]))
            ->assertRedirect(route('cms.media.index', ['locale' => self::LOCALE]))
            ->assertSessionHas('flash.error');

        $this->assertDatabaseHas('media', ['id' => $media->id, 'deleted_at' => null]);

        $signed = $this->getJson(route('cms.media.signed', ['locale' => self::LOCALE, 'media' => $media->id]))
            ->assertOk()
            ->json('url');

        $this->assertIsString($signed);
        $this->assertStringContainsString('signature=', $signed);
        $this->get($signed)->assertOk();
    }

    public function test_user_management_exposes_capabilities_and_blocks_self_and_protected_targets(): void
    {
        $owner = User::factory()->platformOwner()->create();
        $target = User::factory()->create();
        $role = Role::create(['key' => 'operator', 'name' => 'Operator']);

        $this->actingAs($owner)
            ->get(route('admin.users.index', ['locale' => self::LOCALE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->where('can.create', true)
                ->where('can.update', true)
                ->where('can.assignRoles', true)
                ->where('can.resetPassword', true)
                ->where('can.delete', true));

        $this->actingAs($owner)
            ->put(route('admin.users.roles', ['locale' => self::LOCALE, 'user' => $owner->id]), [
                'roles' => [$role->id],
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('admin.users.status', ['locale' => self::LOCALE, 'user' => $owner->id]), [
                'status' => User::STATUS_INACTIVE,
            ])
            ->assertSessionHas('flash.error');

        $nonOwnerManager = $this->userWith(['users.view', 'users.update', 'users.assign_roles', 'users.reset_password', 'users.delete']);
        $this->actingAs($nonOwnerManager)
            ->put(route('admin.users.roles', ['locale' => self::LOCALE, 'user' => $owner->id]), [
                'roles' => [$role->id],
            ])
            ->assertSessionHas('flash.error');

        $this->actingAs($nonOwnerManager)
            ->delete(route('admin.users.destroy', ['locale' => self::LOCALE, 'user' => $target->id]))
            ->assertRedirect(route('admin.users.index', ['locale' => self::LOCALE]));

        $this->assertDatabaseMissing('users', ['id' => $target->id, 'deleted_at' => null]);
    }

    public function test_platform_settings_grouping_exposes_safe_values_and_redacts_private_audit_data(): void
    {
        $owner = User::factory()->platformOwner()->create();
        $settings = app(SettingsService::class);

        $this->actingAs($owner)
            ->get(route('admin.settings.index', ['locale' => self::LOCALE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/Index')
                ->where('canUpdate', true)
                ->where('groups.0.key', 'general')
                ->where('secretReferences', fn ($references) => $references->contains('MAIL_PASSWORD')));

        $this->actingAs($owner)
            ->put(route('admin.settings.update', ['locale' => self::LOCALE]), [
                'general.site_name' => 'PCDR Redesign',
                'security.login_rate_limit' => 7,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('PCDR Redesign', $settings->get('general.site_name'));
        $this->assertSame(7, $settings->get('security.login_rate_limit'));

        $audit = AuditLog::query()
            ->where('action', 'settings.update')
            ->where('subject_id', 'security.login_rate_limit')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('[REDACTED]', data_get($audit->before, 'value'));
        $this->assertSame('[REDACTED]', data_get($audit->after, 'value'));
        $this->assertStringNotContainsString('7', json_encode($audit->after, JSON_THROW_ON_ERROR));

        // Ensure redaction helper is used by service for private values.
        $publicAudit = AuditLog::query()
            ->where('action', 'settings.update')
            ->where('subject_id', 'general.site_name')
            ->latest('id')
            ->first();
        $this->assertSame('PCDR Redesign', data_get($publicAudit?->after, 'value'));
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function publicationPayload(array $overrides = []): array
    {
        return array_merge([
            'status' => ContentStatus::Draft->value,
            'category' => 'policy',
            'publication_date' => now()->toDateString(),
            'version' => 'v1',
            'is_restricted' => false,
            'document_media_id' => null,
            'translations' => [
                self::LOCALE => [
                    'title' => 'Policy publication',
                    'slug' => 'policy-publication',
                    'description' => 'Description',
                ],
            ],
        ], $overrides);
    }

    private function bootstrapUser(): User
    {
        return User::factory()->create([
            'name' => 'CMS Editor',
            'email' => self::BOOTSTRAP_EMAIL,
            'password' => Hash::make('password'),
        ]);
    }

    /** @param list<string> $keys */
    private function userWith(array $keys): User
    {
        $role = Role::create([
            'key' => 'role-'.Str::lower(Str::random(8)),
            'name' => 'Limited user',
        ]);

        foreach ($keys as $key) {
            $permission = Permission::firstOrCreate(
                ['key' => $key],
                ['name' => $key, 'module' => Str::before($key, '.'), 'is_protected' => false],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = User::factory()->create();
        app(AccessManagementService::class)->syncRoles($user, [$role->id]);

        return $user;
    }

    private function createMedia(string $name, MediaType $type, bool $restricted, ?string $path = null): Media
    {
        $storedPath = $path ?? 'media/2026/11/'.$name;
        $disk = $restricted ? 'private' : 'public';
        Storage::disk($disk)->put($storedPath, $type === MediaType::Document ? '%PDF-1.4 test' : 'image');

        return Media::create([
            'uuid' => (string) Str::uuid(),
            'disk' => $disk,
            'path' => $storedPath,
            'type' => $type->value,
            'mime_type' => $type === MediaType::Image ? 'image/png' : 'application/pdf',
            'extension' => $type === MediaType::Image ? 'png' : 'pdf',
            'size_bytes' => 1024,
            'width' => $type === MediaType::Image ? 800 : null,
            'height' => $type === MediaType::Image ? 600 : null,
            'checksum' => hash('sha256', $name),
            'original_name' => $name,
            'is_restricted' => $restricted,
        ]);
    }
}
