<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MediaType;
use App\Models\Article;
use App\Models\ContactMessage;
use App\Models\Media;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StrategicPillar;
use App\Models\User;
use App\Services\AccessManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CmsEditorialToolsTest extends TestCase
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

    public function test_media_library_filters_records_and_exposes_per_locale_alt_text(): void
    {
        $this->actingAs($this->bootstrapUser());

        $image = $this->createMedia('field-photo.png', MediaType::Image, false);
        $this->createMedia('policy-note.pdf', MediaType::Document, false);
        $image->translations()->createMany([
            ['locale' => 'ar', 'alt_text' => 'صورة الميدان', 'caption' => 'لقطة ميدانية'],
            ['locale' => 'en', 'alt_text' => 'Field photo', 'caption' => 'Field caption'],
        ]);

        $this->get(route('cms.media.index', [
            'locale' => self::LOCALE,
            'q' => 'field-photo',
            'type' => 'image',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Cms/Media/Index')
            ->where('filters', [
                'type' => 'image',
                'kind' => '',
                'visibility' => '',
                'q' => 'field-photo',
                'date_from' => '',
                'date_to' => '',
                'sort' => 'newest',
            ])
            ->where('stats.all', 2)
            ->where('pagination.total', 1)
            ->where('items.0.id', $image->getKey())
            ->where('items.0.translations.ar.alt_text', 'صورة الميدان')
            ->where('items.0.translations.en.alt_text', 'Field photo'));
    }

    public function test_media_library_derives_kind_metadata_and_filters_by_kind_and_upload_date(): void
    {
        $this->actingAs($this->bootstrapUser());

        $image = $this->createMedia('field-photo.png', MediaType::Image, false);
        $word = $this->createMedia(
            'policy.docx',
            MediaType::Document,
            true,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'docx',
            now()->subDays(12)->toISOString(),
        );
        $recentPdf = $this->createMedia('recent.pdf', MediaType::Document, false, 'application/pdf', 'pdf', now()->toISOString());
        $legacySvg = $this->createMedia('legacy.svg', MediaType::Image, false, 'image/svg+xml', 'svg');

        $this->get(route('cms.media.index', ['locale' => self::LOCALE, 'q' => 'policy.docx']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('pagination.total', 1)
                ->where('items.0.id', $word->getKey())
                ->where('items.0.kind', 'word')
                ->where('items.0.extension', 'docx')
                ->where('items.0.canPreview', false)
                ->where('items.0.url', null)
                ->where('items.0.accessUrl', fn ($url): bool => str_contains((string) $url, 'signature='))
                ->where('items.0.usage.count', 0));

        $this->get(route('cms.media.index', ['locale' => self::LOCALE, 'q' => 'field-photo.png']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.kind', 'image')
                ->where('items.0.canPreview', true)
                ->where('items.0.accessUrl', $image->url()));

        $this->get(route('cms.media.index', ['locale' => self::LOCALE, 'kind' => 'pdf']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('pagination.total', 1)
                ->where('items.0.id', $recentPdf->getKey())
                ->where('items.0.kind', 'pdf')
                ->where('items.0.canPreview', true));

        $this->get(route('cms.media.index', ['locale' => self::LOCALE, 'kind' => 'other']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('pagination.total', 1)
                ->where('items.0.id', $legacySvg->getKey())
                ->where('items.0.canPreview', false));

        $this->get(route('cms.media.index', [
            'locale' => self::LOCALE,
            'date_from' => now()->subDays(3)->toDateString(),
        ]))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->where('pagination.total', 3)
            ->where('filters.date_from', now()->subDays(3)->toDateString())
            ->where('items', fn ($items): bool => collect($items)->doesntContain(
                fn ($item): bool => $item['id'] === $word->getKey(),
            )));

        $this->get(route('cms.media.index', [
            'locale' => self::LOCALE,
            'kind' => 'word',
            'date_to' => now()->subDays(12)->toDateString(),
        ]))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->where('pagination.total', 1)
            ->where('filters.kind', 'word'));

        $this->get(route('cms.media.index', ['locale' => self::LOCALE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('kindOptions', fn ($options): bool => collect($options)->pluck('value')->all() === [
                    'image', 'pdf', 'word', 'excel', 'powerpoint', 'video', 'audio', 'text', 'other',
                ])
                ->where('uploadFormats.image', fn ($formats): bool => $formats->contains('gif'))
                ->where('uploadFormats.document', fn ($formats): bool => $formats->contains('pptx')));
    }

    public function test_media_search_treats_like_wildcards_as_literal_characters(): void
    {
        $this->actingAs($this->bootstrapUser());

        $literal = $this->createMedia('100%_report.pdf', MediaType::Document, false);
        $other = $this->createMedia('annual-report.pdf', MediaType::Document, false);

        $this->get(route('cms.media.index', ['locale' => self::LOCALE, 'q' => '%_']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('pagination.total', 1)
                ->where('items.0.id', $literal->getKey())
                ->where('filters.q', '%_'));

        $this->get(route('cms.media.index', ['locale' => self::LOCALE, 'q' => 'annual-report']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('pagination.total', 1)
                ->where('items.0.id', $other->getKey()));
    }

    public function test_media_upload_accepts_gif_and_rejects_svg_and_executables(): void
    {
        $this->actingAs($this->bootstrapUser());

        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', true);
        $this->assertNotFalse($gif);

        $this->post(route('cms.media.store', ['locale' => self::LOCALE]), [
            'file' => UploadedFile::fake()->createWithContent('animation.gif', $gif),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $media = Media::query()->where('original_name', 'animation.gif')->firstOrFail();
        $this->assertSame('image/gif', $media->mime_type);
        $this->assertSame('gif', $media->extension);
        $this->assertSame('image', $media->kind()->value);

        $this->post(route('cms.media.store', ['locale' => self::LOCALE]), [
            'file' => UploadedFile::fake()->createWithContent('unsafe.svg', '<svg onload="alert(1)"></svg>'),
        ])->assertSessionHasErrors('file');

        $this->post(route('cms.media.store', ['locale' => self::LOCALE]), [
            'file' => UploadedFile::fake()->createWithContent('script.exe', 'MZ'),
            'is_restricted' => true,
        ])->assertSessionHasErrors('file');

        $this->assertSame(1, Media::query()->count());
    }

    public function test_inquiry_index_filters_by_workflow_status(): void
    {
        $this->actingAs($this->bootstrapUser());

        $new = $this->createContactMessage('new');
        $inProgress = $this->createContactMessage('in_progress');

        $this->get(route('cms.inquiries.index', ['locale' => self::LOCALE, 'status' => 'in_progress']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cms/Inquiries/Index')
                ->where('filters.status', 'in_progress')
                ->where('pagination.contact.total', 1)
                ->where('inquiries.contact.0.reference', $inProgress->reference)
                ->where('inquiries.contact.0.status', 'in_progress')
                ->missing('inquiries.contact.1')
                ->where('statuses', fn ($statuses) => array_values(array_intersect(
                    ['new', 'in_progress', 'resolved', 'closed'],
                    $statuses->all(),
                )) === ['new', 'in_progress', 'resolved', 'closed']));

        $this->assertSame('new', $new->fresh()->status);
    }

    public function test_authorized_user_can_change_an_inquiry_workflow_status(): void
    {
        $this->actingAs($this->bootstrapUser());

        $message = $this->createContactMessage('new');

        $this->put(route('cms.inquiries.status', [
            'locale' => self::LOCALE,
            'type' => 'contact',
            'inquiry' => $message->getKey(),
        ]), ['status' => 'in_progress'])->assertRedirect();

        $this->assertSame('in_progress', $message->fresh()->status);
    }

    public function test_inquiry_status_update_rejects_non_workflow_values_and_unknown_types(): void
    {
        $this->actingAs($this->bootstrapUser());

        $message = $this->createContactMessage('new');

        $this->put(route('cms.inquiries.status', [
            'locale' => self::LOCALE,
            'type' => 'contact',
            'inquiry' => $message->getKey(),
        ]), ['status' => 'approved'])->assertSessionHasErrors('status');

        $this->put('/'.self::LOCALE."/cms/inquiries/unknown/{$message->getKey()}/status", [
            'status' => 'resolved',
        ])->assertNotFound();

        $this->assertSame('new', $message->fresh()->status);
    }

    public function test_user_without_inquiry_update_permission_cannot_change_status(): void
    {
        $message = $this->createContactMessage('new');
        $viewer = $this->userWith(['inquiries.view']);

        $this->actingAs($viewer)->put(route('cms.inquiries.status', [
            'locale' => self::LOCALE,
            'type' => 'contact',
            'inquiry' => $message->getKey(),
        ]), ['status' => 'resolved'])->assertForbidden();

        $this->assertSame('new', $message->fresh()->status);
    }

    public function test_content_editor_offers_typed_media_options_with_restricted_signatures(): void
    {
        $article = Article::create(['status' => 'draft', 'display_order' => 0]);
        foreach (['ar', 'en'] as $locale) {
            $article->translations()->create([
                'locale' => $locale,
                'slug' => 'editorial-article-'.$locale,
                'title' => 'Article title ('.$locale.')',
            ]);
        }

        $publicImage = $this->createMedia('cover.png', MediaType::Image, false);
        $restrictedDocument = $this->createMedia('private.pdf', MediaType::Document, true);

        $this->actingAs($this->bootstrapUser())
            ->get(route('cms.news.edit', ['locale' => self::LOCALE, 'content' => $article->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cms/Content/Edit')
                ->where('options.media', fn ($options) => $options->count() === 2
                    && $options->firstWhere('id', $publicImage->getKey())['type'] === 'image'
                    && $options->firstWhere('id', $publicImage->getKey())['kind'] === 'image'
                    && $options->firstWhere('id', $publicImage->getKey())['restricted'] === false
                    && $options->firstWhere('id', $publicImage->getKey())['url'] === $publicImage->url()
                    && $options->firstWhere('id', $restrictedDocument->getKey())['type'] === 'document'
                    && $options->firstWhere('id', $restrictedDocument->getKey())['kind'] === 'pdf'
                    && $options->firstWhere('id', $restrictedDocument->getKey())['restricted'] === true
                    && str_contains($options->firstWhere('id', $restrictedDocument->getKey())['url'], 'signature=')));

        $pillar = StrategicPillar::create(['code' => 'ED', 'status' => 'draft', 'display_order' => 0]);
        $pillar->translations()->create([
            'locale' => self::LOCALE,
            'slug' => 'editorial-pillar',
            'title' => 'Pillar title',
        ]);

        $this->actingAs($this->userWith(['pillars.view']))
            ->get(route('cms.pillars.edit', ['locale' => self::LOCALE, 'content' => $pillar->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $pageProps) => $pageProps
                ->component('Cms/Content/Edit')
                ->where('options.media', []));
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
        $role = Role::create(['key' => 'limited-'.implode('-', $keys), 'name' => 'Limited user']);

        foreach ($keys as $key) {
            $permission = Permission::create([
                'key' => $key,
                'name' => $key,
                'module' => 'content',
            ]);
            $role->permissions()->attach($permission);
        }

        $user = User::factory()->create();
        app(AccessManagementService::class)->syncRoles($user, [$role->getKey()]);

        return $user;
    }

    private function createMedia(
        string $name,
        MediaType $type,
        bool $restricted,
        ?string $mime = null,
        ?string $extension = null,
        ?string $createdAt = null,
    ): Media {
        $media = Media::create([
            'uuid' => (string) Str::uuid(),
            'disk' => $restricted ? 'private' : 'public',
            'path' => 'media/2026/11/'.$name,
            'type' => $type->value,
            'mime_type' => $mime ?? ($type === MediaType::Image ? 'image/png' : 'application/pdf'),
            'extension' => $extension ?? ($type === MediaType::Image ? 'png' : 'pdf'),
            'size_bytes' => 1024,
            'width' => $type === MediaType::Image ? 800 : null,
            'height' => $type === MediaType::Image ? 600 : null,
            'checksum' => hash('sha256', $name),
            'original_name' => $name,
            'is_restricted' => $restricted,
        ]);

        if ($createdAt !== null) {
            $media->forceFill(['created_at' => $createdAt])->save();
        }

        return $media;
    }

    private function createContactMessage(string $status): ContactMessage
    {
        return ContactMessage::create([
            'reference' => (string) Str::ulid(),
            'category' => 'general',
            'name' => 'Test sender',
            'email' => 'sender@example.test',
            'subject' => 'Test subject',
            'message' => 'Test message',
            'locale' => self::LOCALE,
            'consent' => true,
            'status' => $status,
        ]);
    }
}
