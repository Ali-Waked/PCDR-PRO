<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\TransitionContentStatus;
use App\Actions\UploadMedia;
use App\Enums\ContentStatus;
use App\Enums\ProjectStatus;
use App\Exceptions\InvalidWorkflowTransition;
use App\Http\Controllers\Cms\ArticleController;
use App\Models\Article;
use App\Models\Concerns\HasTranslations;
use App\Models\ContentRevision;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Project;
use App\Models\ProjectTranslation;
use App\Models\Publication;
use App\Models\User;
use App\Services\TranslationSaver;
use App\Support\RichText;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CmsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALE = 'ar';

    private const BOOTSTRAP_EMAIL = 'admin@example.test';

    /** A valid 1x1 PNG generated without GD. */
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=';

    /** Minimal PDF payload accepted by the restricted-upload MIME allowlist. */
    private const PDF_CONTENT = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n";

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cms.bootstrap.email', self::BOOTSTRAP_EMAIL);
        config()->set('cms.local_auth_enabled', true);
        Storage::fake('public');
        Storage::fake('private');
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function bootstrapUser(): User
    {
        return User::factory()->create([
            'name' => 'CMS Admin',
            'email' => self::BOOTSTRAP_EMAIL,
            'password' => Hash::make('password'),
        ]);
    }

    private function loginAsBootstrap(): User
    {
        $user = $this->bootstrapUser();
        auth()->login($user);

        return $user;
    }

    private function outsider(): User
    {
        return User::factory()->create(['email' => 'outside@example.test']);
    }

    /** @return array<string, mixed> */
    private function contentPayload(string $slug, string $status = 'draft'): array
    {
        return [
            'status' => $status,
            'published_at' => null,
            'display_order' => 5,
            'translations' => [
                'ar' => [
                    'title' => 'عنوان المحتوى',
                    'slug' => $slug,
                    'summary' => 'ملخص المحتوى',
                    'body' => '<p>Safe body</p>',
                    'seo_title' => 'SEO عربي',
                    'seo_description' => 'وصف سيو عربي',
                ],
                'en' => [
                    'title' => 'Content title',
                    'slug' => $slug.'-en',
                    'summary' => 'Content summary',
                    'body' => '<p>English body</p>',
                    'seo_title' => 'SEO English',
                    'seo_description' => 'English SEO description',
                ],
            ],
        ];
    }

    private function createPage(string $slug = 'workflow-page'): Page
    {
        $page = Page::create([
            'status' => ContentStatus::Draft->value,
            'display_order' => 0,
        ]);
        $page->translations()->create([
            'locale' => 'ar',
            'slug' => $slug,
            'title' => 'صفحة الاختبار',
            'body' => '<p>نص</p>',
        ]);

        return $page;
    }

    /** Perform a CMS write and assert that it completed without an error. */
    private function performCmsRequest(callable $request): void
    {
        $this->withoutExceptionHandling();

        try {
            $request();
        } finally {
            $this->withExceptionHandling();
        }
    }

    /** @return array<int, string> */
    private function screenRoutes(): array
    {
        return [
            'cms.dashboard',
            'cms.pillars.index', 'cms.pillars.create',
            'cms.programs.index', 'cms.programs.create',
            'cms.projects.index', 'cms.projects.create',
            'cms.news.index', 'cms.news.create',
            'cms.publications.index', 'cms.publications.create',
            'cms.media.index',
            'cms.settings.index',
            'cms.inquiries.index',
        ];
    }

    private function fakePng(string $name = 'photo.png'): UploadedFile
    {
        $content = base64_decode(self::PNG_BASE64, true);
        $this->assertNotFalse($content);

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function fakePdf(string $name = 'report.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, self::PDF_CONTENT);
    }

    // ---------------------------------------------------------------------
    // 1. Access control
    // ---------------------------------------------------------------------

    public function test_guests_cannot_reach_the_dashboard_or_cms_crud_routes(): void
    {
        $guestUris = [
            '/ar/cms',
            '/ar/cms/pillars',
            '/ar/cms/programs',
            '/ar/cms/projects',
            '/ar/cms/news',
            '/ar/cms/publications',
            '/ar/cms/media',
            '/ar/cms/settings',
            '/ar/cms/inquiries',
        ];

        foreach ($guestUris as $uri) {
            $response = $this->get($uri);

            $this->assertFalse($response->isSuccessful(), "Guest must not access {$uri}");
            if ($response->isRedirect()) {
                $response->assertRedirectContains('login');
            } else {
                $this->assertContains($response->status(), [401, 403], "Unexpected guest status for {$uri}");
            }
        }

        $post = $this->post('/ar/cms/news', $this->contentPayload('guest-news'));
        $this->assertFalse($post->isSuccessful());
        $this->assertDatabaseCount('articles', 0);

        $media = $this->post('/ar/cms/media', ['file' => $this->fakePng()]);
        $this->assertFalse($media->isSuccessful());
        $this->assertDatabaseCount('media', 0);
    }

    public function test_authenticated_user_without_bootstrap_email_gets_403_everywhere(): void
    {
        // No Phase 2 permission contracts (hasPermission / PermissionService /
        // Gates) exist in this worktree, so CmsAccess falls back to the email.
        $outsider = $this->outsider();
        $this->assertFalse(method_exists($outsider, 'hasPermission'));
        auth()->login($outsider);

        $requests = [
            ['get', 'cms.dashboard', []],
            ['get', 'cms.pillars.index', []],
            ['get', 'cms.programs.index', []],
            ['get', 'cms.projects.index', []],
            ['get', 'cms.news.index', []],
            ['post', 'cms.news.store', []],
            ['post', 'cms.news.status', ['content' => 1]],
            ['get', 'cms.publications.index', []],
            ['get', 'cms.media.index', []],
            ['post', 'cms.media.store', []],
            ['get', 'cms.media.signed', ['media' => 1]],
            ['get', 'cms.settings.index', []],
            ['put', 'cms.settings.update', []],
            ['get', 'cms.inquiries.index', []],
        ];

        foreach ($requests as [$verb, $route, $parameters]) {
            $response = $this->{$verb}(
                route($route, array_merge(['locale' => self::LOCALE], $parameters)),
            );

            $response->assertForbidden();
        }

        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseCount('media', 0);
    }

    // ---------------------------------------------------------------------
    // 2. Local CMS login
    // ---------------------------------------------------------------------

    public function test_local_login_page_works_and_bootstrap_user_can_sign_in(): void
    {
        $user = $this->bootstrapUser();

        $this->get(route('cms.login', ['locale' => self::LOCALE]))->assertOk();

        $this->from(route('cms.login', ['locale' => self::LOCALE]))
            ->post(route('cms.login.attempt', ['locale' => self::LOCALE]), [
                'email' => self::BOOTSTRAP_EMAIL,
                'password' => 'not-the-password',
            ])
            ->assertSessionHas('flash.error');

        $this->assertGuest();

        $this->post(route('cms.login.attempt', ['locale' => self::LOCALE]), [
            'email' => self::BOOTSTRAP_EMAIL,
            'password' => 'password',
        ])->assertRedirect(route('cms.dashboard', ['locale' => self::LOCALE]));

        $this->assertAuthenticatedAs($user);
    }

    // ---------------------------------------------------------------------
    // 3. CMS screens
    // ---------------------------------------------------------------------

    public function test_bootstrap_user_can_open_dashboard_and_all_crud_screens(): void
    {
        $this->loginAsBootstrap();

        foreach ($this->screenRoutes() as $route) {
            $response = $this->get(route($route, ['locale' => self::LOCALE]));

            $this->assertSame(200, $response->status(), "Unable to open {$route}");
            $response->assertViewIs('app');
        }
    }

    // ---------------------------------------------------------------------
    // 4. Store / update, translations and revisions
    // ---------------------------------------------------------------------

    public function test_bootstrap_user_can_store_and_update_article_translations(): void
    {
        $this->loginAsBootstrap();

        $payload = $this->contentPayload('news-item');
        foreach ($payload['translations'] as &$translation) {
            $translation['excerpt'] = $translation['summary'];
            unset($translation['summary']);
        }
        unset($translation);

        $this->performCmsRequest(fn () => $this->post(
            route('cms.news.store', ['locale' => self::LOCALE]),
            $payload,
        ));

        $article = Article::query()->firstOrFail();
        $this->assertSame('draft', $article->status->value);

        // News uses `excerpt` rather than the shared `summary` column.
        $this->assertDatabaseHas('article_translations', [
            'article_id' => $article->getKey(),
            'locale' => 'ar',
            'title' => 'عنوان المحتوى',
            'slug' => 'news-item',
            'excerpt' => 'ملخص المحتوى',
            'body' => '<p>Safe body</p>',
            'seo_title' => 'SEO عربي',
            'seo_description' => 'وصف سيو عربي',
        ]);
        $this->assertDatabaseHas('article_translations', [
            'article_id' => $article->getKey(),
            'locale' => 'en',
            'title' => 'Content title',
            'slug' => 'news-item-en',
            'body' => '<p>English body</p>',
        ]);

        $payload = $this->contentPayload('news-item');
        $payload['status'] = 'published'; // must not bypass workflow on update either
        $payload['translations']['en']['title'] = 'Updated news title';

        $this->performCmsRequest(fn () => $this->put(
            route('cms.news.update', ['locale' => self::LOCALE, 'content' => $article->getKey()]),
            $payload,
        ));

        $article->refresh();
        $this->assertSame('draft', $article->status->value);
        $this->assertNull($article->published_at);
        $this->assertDatabaseHas('article_translations', [
            'article_id' => $article->getKey(),
            'locale' => 'en',
            'title' => 'Updated news title',
            'slug' => 'news-item-en',
        ]);
    }

    public function test_revisions_are_recorded_with_actor_and_translation_payload(): void
    {
        $user = $this->loginAsBootstrap();
        $page = new WorkflowPage(['status' => 'draft', 'display_order' => 0]);
        $page->save();
        $page->translations()->create([
            'locale' => 'ar',
            'slug' => 'revision-page',
            'title' => 'صفحة المراجعات',
        ]);

        // WorkflowPage keeps status as a string, so the real CMS revision
        // writer can be exercised here without the production enum-cast defect.
        $request = Request::create('/');
        $request->setUserResolver(static fn () => $user);
        $method = new \ReflectionMethod(ArticleController::class, 'revision');
        $method->invoke(app(ArticleController::class), $page, $request, 'created');

        $revision = ContentRevision::query()->firstOrFail();
        $this->assertSame($page->getMorphClass(), $revision->revisionable_type);
        $this->assertSame($page->getKey(), $revision->revisionable_id);
        $this->assertSame('created', $revision->note);
        $this->assertSame('draft', $revision->status);
        $this->assertSame($user->getKey(), $revision->actor_id);
        $this->assertSame(self::LOCALE, $revision->locale);
        $this->assertSame('صفحة المراجعات', $revision->title);
        $this->assertSame(
            ['ar' => ['title' => 'صفحة المراجعات']],
            $revision->payload,
        );
    }

    // ---------------------------------------------------------------------
    // 5. Publication workflow
    // ---------------------------------------------------------------------

    public function test_workflow_transitions_draft_to_published_with_revisions(): void
    {
        $user = $this->loginAsBootstrap();
        $page = new WorkflowPage(['status' => 'draft', 'display_order' => 0]);
        $page->save();
        $page->translations()->create([
            'locale' => 'ar',
            'slug' => 'published-page',
            'title' => 'صفحة منشورة',
        ]);
        foreach (['pending_review', 'approved', 'published'] as $target) {
            app(TransitionContentStatus::class)->handle($page, $target, $user);

            $this->assertSame($target, $page->fresh()->status);
            $this->assertDatabaseHas('content_revisions', [
                'revisionable_type' => $page->getMorphClass(),
                'revisionable_id' => $page->getKey(),
                'status' => $target,
                'note' => 'workflow transition',
                'actor_id' => $user->getKey(),
            ]);
        }

        $this->assertNotNull($page->fresh()->published_at);
        $this->assertSame(3, ContentRevision::query()
            ->where('revisionable_type', $page->getMorphClass())
            ->where('revisionable_id', $page->getKey())
            ->where('note', 'workflow transition')
            ->count());
    }

    public function test_invalid_workflow_jump_is_rejected_and_leaves_database_unchanged(): void
    {
        $user = $this->loginAsBootstrap();
        $page = new WorkflowPage(['status' => 'draft', 'display_order' => 0]);
        $page->save();
        $page->translations()->create([
            'locale' => 'ar',
            'slug' => 'invalid-transition',
            'title' => 'انتقال غير صالح',
        ]);

        try {
            app(TransitionContentStatus::class)->handle($page, ContentStatus::Published, $user);
            $this->fail('Expected an InvalidWorkflowTransition exception.');
        } catch (InvalidWorkflowTransition $exception) {
            $this->assertSame('draft', $exception->from->value);
            $this->assertSame('published', $exception->to->value);
            $this->assertStringContainsString('draft -> published', $exception->getMessage());
        }

        $this->assertSame('draft', $page->fresh()->status);
        $this->assertNull($page->fresh()->published_at);
        $this->assertDatabaseCount('content_revisions', 0);
    }

    public function test_status_endpoint_cannot_skip_workflow_steps(): void
    {
        $this->loginAsBootstrap();
        $article = Article::create([
            'status' => ContentStatus::Draft->value,
            'display_order' => 0,
        ]);
        $article->translations()->create([
            'locale' => self::LOCALE,
            'slug' => 'http-invalid-transition',
            'title' => 'خبر انتقال غير صالح',
        ]);
        $revisionsBefore = ContentRevision::query()->count();

        $response = $this->post(route('cms.news.status', [
            'locale' => self::LOCALE,
            'content' => $article->getKey(),
        ]), ['status' => 'published']);

        // Invalid workflow jumps are rejected with a conflict and no write.
        $this->assertSame(409, $response->status());
        $this->assertSame('draft', $article->fresh()->status->value);
        $this->assertNull($article->fresh()->published_at);
        $this->assertSame($revisionsBefore, ContentRevision::query()->count());
    }

    // ---------------------------------------------------------------------
    // 6. Project lifecycle
    // ---------------------------------------------------------------------

    public function test_project_lifecycle_is_independent_from_publication_workflow(): void
    {
        $this->loginAsBootstrap();

        $payload = [
            'project_status' => ProjectStatus::Planned->value,
            'status' => ContentStatus::Draft->value,
            'published_at' => null,
            'display_order' => 2,
            'translations' => [
                'ar' => [
                    'title' => 'مشروع الاختبار',
                    'slug' => 'test-project',
                    'summary' => 'ملخص المشروع',
                    'description' => '<p>وصف المشروع</p>',
                    'objectives' => '<p>الأهداف</p>',
                    'seo_title' => 'SEO المشروع',
                    'seo_description' => 'وصف سيو المشروع',
                ],
                'en' => [
                    'title' => 'Test project',
                    'slug' => 'test-project-en',
                    'summary' => 'Project summary',
                    'description' => '<p>Project description</p>',
                    'seo_title' => 'Project SEO',
                    'seo_description' => 'Project SEO description',
                ],
            ],
        ];

        $this->performCmsRequest(fn () => $this->post(
            route('cms.projects.store', ['locale' => self::LOCALE]),
            $payload,
        ));

        $project = Project::query()->firstOrFail();
        $this->assertSame(ProjectStatus::Planned, $project->status);
        $this->assertSame(ContentStatus::Draft, $project->workflow_status);
        $this->assertDatabaseHas('project_translations', [
            'project_id' => $project->getKey(),
            'locale' => 'ar',
            'slug' => 'test-project',
            'title' => 'مشروع الاختبار',
        ]);

        // A normal edit repeats the lifecycle value and attempts to set the
        // publication status; neither may change the workflow state.
        $editPayload = $payload;
        $editPayload['status'] = ContentStatus::Published->value;
        $editPayload['translations']['ar']['title'] = 'مشروع محدّث';

        $this->performCmsRequest(fn () => $this->put(
            route('cms.projects.update', ['locale' => self::LOCALE, 'content' => $project->getKey()]),
            $editPayload,
        ));

        $project->refresh();
        $this->assertSame(ProjectStatus::Planned, $project->status);
        $this->assertSame(ContentStatus::Draft, $project->workflow_status);
        $this->assertNull($project->published_at);
        $this->assertDatabaseHas('project_translations', [
            'project_id' => $project->getKey(),
            'locale' => 'ar',
            'title' => 'مشروع محدّث',
        ]);

        // A valid workflow transition must not disturb the project lifecycle.
        $response = $this->post(route('cms.projects.status', [
            'locale' => self::LOCALE,
            'content' => $project->getKey(),
        ]), ['status' => 'pending_review']);
        $this->assertSame(302, $response->status());
        $this->assertSame(ProjectStatus::Planned, $project->fresh()->status);
        $this->assertSame(ContentStatus::PendingReview, $project->fresh()->workflow_status);
    }

    public function test_project_status_action_only_changes_workflow_status(): void
    {
        $user = $this->loginAsBootstrap();
        $project = new WorkflowProject([
            'status' => ProjectStatus::Planned->value,
            'workflow_status' => ContentStatus::Draft->value,
            'display_order' => 1,
        ]);
        $project->save();
        $project->translations()->create([
            'locale' => 'ar',
            'slug' => 'action-project',
            'title' => 'مشروع الإجراء',
        ]);

        app(TransitionContentStatus::class)->handle($project, ContentStatus::PendingReview, $user);

        $project->refresh();
        $this->assertSame('pending_review', $project->workflow_status);
        $this->assertSame('planned', $project->status, 'Project lifecycle must not follow the publication workflow.');
        $this->assertNull($project->published_at);
        $this->assertDatabaseHas('content_revisions', [
            'revisionable_type' => $project->getMorphClass(),
            'revisionable_id' => $project->getKey(),
            'status' => 'pending_review',
            'note' => 'workflow transition',
        ]);
    }

    // ---------------------------------------------------------------------
    // 7. Rich text sanitising
    // ---------------------------------------------------------------------

    public function test_rich_text_sanitizer_removes_dangerous_markup_and_keeps_safe_tags(): void
    {
        $clean = RichText::clean(
            '<p onclick="alert(1)">Safe <strong>bold</strong>'
            .'<script>alert("x")</script>'
            .'<a href="javascript:alert(1)">bad link</a>'
            .'<a href="https://example.test" target="_blank">good link</a></p>'
        );

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('<p>', $clean);
        $this->assertStringContainsString('<strong>bold</strong>', $clean);
        $this->assertStringContainsString('href="https://example.test"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
    }

    public function test_translation_saver_sanitizes_rich_text_before_persisting(): void
    {
        $page = $this->createPage('sanitize-me');

        app(TranslationSaver::class)->sync(
            $page,
            [
                'ar' => [
                    'title' => 'محتوى آمن',
                    'slug' => 'sanitize-me',
                    'body' => '<p onclick="alert(1)">نص<script>alert(2)</script><a href="javascript:alert(3)">رابط</a></p>',
                ],
            ],
            ['title', 'slug', 'summary', 'body', 'seo_title', 'seo_description'],
            'page_id',
        );

        $body = (string) $page->translations()->where('locale', 'ar')->firstOrFail()->body;
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringContainsString('<p>', $body);
        $this->assertStringContainsString('نص', $body);
    }

    // ---------------------------------------------------------------------
    // 8. Media upload
    // ---------------------------------------------------------------------

    public function test_bootstrap_user_can_upload_a_public_image_without_gd(): void
    {
        $user = $this->loginAsBootstrap();
        $png = base64_decode(self::PNG_BASE64, true);
        $this->assertNotFalse($png);

        $this->post(route('cms.media.store', ['locale' => self::LOCALE]), [
            'file' => $this->fakePng('field-notes.png'),
            'translations' => [
                'ar' => ['alt_text' => 'صورة الميدان', 'caption' => 'لقطة من الميدان'],
                'en' => ['alt_text' => 'Field photo', 'caption' => 'Field caption'],
            ],
        ])->assertRedirect();

        $media = Media::query()->firstOrFail();
        $this->assertSame('public', $media->disk);
        $this->assertFalse($media->is_restricted);
        $this->assertSame('image/png', $media->mime_type);
        $this->assertSame('png', $media->extension);
        $this->assertSame(hash('sha256', $png), $media->checksum);
        $this->assertSame($user->getKey(), $media->uploaded_by);
        $this->assertSame(1, $media->width);
        $this->assertSame(1, $media->height);
        $this->assertMatchesRegularExpression(
            '#^media/\d{4}/\d{2}/[A-Za-z0-9]+\.png$#',
            $media->path,
        );
        Storage::disk('public')->assertExists($media->path);
        $this->assertSame($png, Storage::disk('public')->get($media->path));
        $this->assertDatabaseHas('media_translations', [
            'media_id' => $media->getKey(),
            'locale' => 'ar',
            'alt_text' => 'صورة الميدان',
        ]);
    }

    public function test_restricted_upload_is_stored_on_the_private_disk(): void
    {
        $this->loginAsBootstrap();

        $this->post(route('cms.media.store', ['locale' => self::LOCALE]), [
            'file' => $this->fakePdf('internal-report.pdf'),
            'is_restricted' => true,
        ])->assertRedirect();

        $media = Media::query()->firstOrFail();
        $this->assertTrue($media->is_restricted);
        $this->assertSame('private', $media->disk);
        $this->assertSame('application/pdf', $media->mime_type);
        $this->assertMatchesRegularExpression('#^media/\d{4}/\d{2}/[A-Za-z0-9]+\.pdf$#', $media->path);
        Storage::disk('private')->assertExists($media->path);
        Storage::disk('public')->assertMissing($media->path);
    }

    public function test_upload_rejects_disallowed_mime_type(): void
    {
        $user = $this->loginAsBootstrap();
        $file = UploadedFile::fake()
            ->createWithContent('payload.exe', 'this is not an image')
            ->mimeType('application/x-msdownload');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('file type is not allowed');

        app(UploadMedia::class)->handle($file, false, $user);
    }

    public function test_upload_rejects_disallowed_operating_system_extension(): void
    {
        $user = $this->loginAsBootstrap();
        $file = UploadedFile::fake()
            ->createWithContent('photo.png!!', base64_decode(self::PNG_BASE64, true))
            ->mimeType('image/png');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('file extension is not allowed');

        app(UploadMedia::class)->handle($file, false, $user);
    }

    // ---------------------------------------------------------------------
    // 9. Signed restricted media URLs
    // ---------------------------------------------------------------------

    public function test_signed_media_url_requires_permission_and_download_requires_signature(): void
    {
        $user = $this->loginAsBootstrap();

        // Restricted uploads accept document MIME types only.
        $media = app(UploadMedia::class)->handle($this->fakePdf('confidential.pdf'), true, $user);
        $this->assertTrue($media->is_restricted);
        $this->assertSame('private', $media->disk);
        Storage::disk('private')->assertExists($media->path);

        $signedRoute = route('cms.media.signed', [
            'locale' => self::LOCALE,
            'media' => $media->getKey(),
        ]);
        $downloadRoute = route('public.media.download', [
            'locale' => self::LOCALE,
            'uuid' => $media->uuid,
        ]);

        // Unsigned downloads are denied even for a bootstrap user.
        $this->get($downloadRoute)->assertForbidden();

        // The signed-URL endpoint returns a signed URL for permitted users.
        $response = $this->get($signedRoute)->assertOk()->assertJsonStructure(['url']);
        $signedUrl = $response->json('url');
        $this->assertIsString($signedUrl);
        $this->assertStringContainsString('signature=', $signedUrl);
        $this->assertStringContainsString(
            route('public.media.download', ['locale' => self::LOCALE, 'uuid' => $media->uuid]),
            $signedUrl,
        );

        // Signed URLs work for a user with media.view permission.
        $this->get($signedUrl)->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Restricted files also require an authenticated, permitted viewer.
        auth()->logout();
        $this->get($signedUrl)->assertForbidden();

        auth()->login($this->outsider());
        $this->get($signedUrl)->assertForbidden();
    }
}

/**
 * Test-only models that keep the workflow columns as plain strings.
 *
 * The production models cast `status`/`workflow_status` to ContentStatus, and
 * the current application code casts those enums with `(string) $enum`, which
 * is invalid PHP. These shims let us verify the workflow rules and revision
 * format without changing application code; the bug is reported separately.
 */
final class StringBackedStatusCast implements CastsAttributes
{
    /** Keep enum objects out of Laravel's cast cache after workflow writes. */
    public bool $withoutObjectCaching = true;

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : (string) $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}

final class WorkflowPage extends Model
{
    use HasTranslations;

    protected $table = 'pages';

    protected $fillable = ['status', 'published_at', 'display_order'];

    protected function casts(): array
    {
        return [
            'status' => StringBackedStatusCast::class,
            'published_at' => 'datetime',
            'display_order' => 'integer',
        ];
    }

    public function translationModel(): string
    {
        return PageTranslation::class;
    }

    public function translationForeignKey(): string
    {
        return 'page_id';
    }
}

final class WorkflowProject extends Model
{
    use HasTranslations;

    protected $table = 'projects';

    protected $fillable = ['status', 'workflow_status', 'published_at', 'display_order'];

    protected function casts(): array
    {
        return [
            'status' => StringBackedStatusCast::class,
            'workflow_status' => StringBackedStatusCast::class,
            'published_at' => 'datetime',
            'display_order' => 'integer',
        ];
    }

    public function translationModel(): string
    {
        return ProjectTranslation::class;
    }

    public function translationForeignKey(): string
    {
        return 'project_id';
    }
}
