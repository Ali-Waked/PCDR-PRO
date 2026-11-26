<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Enums\ProjectStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ContentRevision;
use App\Models\Media;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Project;
use App\Models\Role;
use App\Models\StrategicPillar;
use App\Models\Tag;
use App\Models\User;
use App\Services\AccessManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CmsContentRedesignTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALE = 'ar';

    private const BOOTSTRAP_EMAIL = 'cms-redesign@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cms.bootstrap.email', self::BOOTSTRAP_EMAIL);
        config()->set('cms.local_auth_enabled', true);
        Storage::fake('public');
        Storage::fake('private');
    }

    private function bootstrapUser(): User
    {
        return User::factory()->create([
            'name' => 'CMS Redesign Editor',
            'email' => self::BOOTSTRAP_EMAIL,
            'password' => Hash::make('password'),
        ]);
    }

    private function login(): User
    {
        $user = $this->bootstrapUser();
        $this->actingAs($user);

        return $user;
    }

    private function createPillar(string $code, string $title): StrategicPillar
    {
        $pillar = StrategicPillar::create([
            'code' => $code,
            'status' => ContentStatus::Published->value,
            'display_order' => 1,
        ]);

        foreach (['ar' => $title, 'en' => $code.' pillar'] as $locale => $value) {
            $pillar->translations()->create([
                'locale' => $locale,
                'slug' => strtolower($code).'-'.$locale,
                'title' => $value,
            ]);
        }

        return $pillar;
    }

    private function createImage(string $name): Media
    {
        return Media::create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'public',
            'path' => 'media/2026/11/'.$name,
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

    /** @return array<string, mixed> */
    private function programPayload(string $slug, array $extra = []): array
    {
        return array_merge([
            'status' => ContentStatus::Draft->value,
            'published_at' => null,
            'display_order' => 0,
            'translations' => [
                'ar' => [
                    'title' => 'برنامج '.$slug,
                    'slug' => $slug.'-ar',
                    'summary' => 'ملخص عربي',
                    'description' => '<p>وصف عربي</p>',
                    'objectives' => 'أهداف البرنامج',
                    'seo_title' => 'SEO عربي',
                    'seo_description' => 'وصف سيو عربي',
                ],
                'en' => [
                    'title' => 'Program '.$slug,
                    'slug' => $slug.'-en',
                    'summary' => 'English summary',
                    'description' => '<p>English description</p>',
                    'objectives' => 'Program objectives',
                    'seo_title' => 'English SEO',
                    'seo_description' => 'English SEO description',
                ],
            ],
        ], $extra);
    }

    /** @return array<string, mixed> */
    private function projectPayload(string $slug, array $extra = []): array
    {
        return array_merge($this->programPayload($slug), [
            'project_status' => ProjectStatus::Planned->value,
        ], $extra);
    }

    /** @return array<string, mixed> */
    private function articlePayload(string $slug, array $extra = []): array
    {
        return array_merge([
            'status' => ContentStatus::Draft->value,
            'published_at' => null,
            'display_order' => 0,
            'translations' => [
                'ar' => [
                    'title' => 'خبر '.$slug,
                    'slug' => $slug.'-ar',
                    'excerpt' => 'ملخص الخبر',
                    'body' => '<p>نص الخبر</p>',
                    'seo_title' => 'SEO عربي',
                    'seo_description' => 'وصف سيو عربي',
                ],
                'en' => [
                    'title' => 'News '.$slug,
                    'slug' => $slug.'-en',
                    'excerpt' => 'News summary',
                    'body' => '<p>News body</p>',
                    'seo_title' => 'English SEO',
                    'seo_description' => 'English SEO description',
                ],
            ],
        ], $extra);
    }

    private function createProgram(string $slug, ContentStatus $status, ?StrategicPillar $pillar = null): Program
    {
        $program = Program::create([
            'status' => $status->value,
            'strategic_pillar_id' => $pillar?->getKey(),
            'published_at' => $status === ContentStatus::Published ? now()->subDay() : null,
        ]);

        foreach (['ar' => 'برنامج '.$slug, 'en' => 'Program '.$slug] as $locale => $title) {
            $program->translations()->create([
                'locale' => $locale,
                'slug' => $slug.'-'.$locale,
                'title' => $title,
                'summary' => 'Summary '.$slug,
            ]);
        }

        return $program;
    }

    private function createProject(
        string $slug,
        ProjectStatus $stage,
        ContentStatus $workflow,
        ?Program $program = null,
        ?StrategicPillar $pillar = null,
        ?Media $image = null,
    ): Project {
        $project = Project::create([
            'status' => $stage->value,
            'workflow_status' => $workflow->value,
            'program_id' => $program?->getKey(),
            'strategic_pillar_id' => $pillar?->getKey(),
            'featured_image_id' => $image?->getKey(),
            'published_at' => $workflow === ContentStatus::Published ? now()->subDay() : null,
        ]);

        foreach (['ar' => 'مشروع '.$slug, 'en' => 'Project '.$slug] as $locale => $title) {
            $project->translations()->create([
                'locale' => $locale,
                'slug' => $slug.'-'.$locale,
                'title' => $title,
                'summary' => 'Summary '.$slug,
            ]);
        }

        return $project;
    }

    private function createArticle(
        string $slug,
        ContentStatus $status,
        ?ArticleCategory $category = null,
        ?Tag $tag = null,
        bool $featured = false,
    ): Article {
        $article = Article::create([
            'status' => $status->value,
            'article_category_id' => $category?->getKey(),
            'is_featured' => $featured,
            'published_at' => $status === ContentStatus::Published ? now()->subDay() : null,
        ]);

        foreach (['ar' => 'خبر '.$slug, 'en' => 'News '.$slug] as $locale => $title) {
            $article->translations()->create([
                'locale' => $locale,
                'slug' => $slug.'-'.$locale,
                'title' => $title,
                'excerpt' => 'Excerpt '.$slug,
            ]);
        }

        if ($tag !== null) {
            $article->tags()->attach($tag);
        }

        return $article;
    }

    private function createCategory(string $slug, string $name): ArticleCategory
    {
        $category = ArticleCategory::create(['slug' => $slug, 'display_order' => 1]);
        foreach (['ar' => $name, 'en' => $slug.' category'] as $locale => $value) {
            $category->translations()->create(['locale' => $locale, 'name' => $value]);
        }

        return $category;
    }

    private function createTag(string $slug, string $name): Tag
    {
        $tag = Tag::create(['slug' => $slug]);
        foreach (['ar' => $name, 'en' => $slug.' tag'] as $locale => $value) {
            $tag->translations()->create(['locale' => $locale, 'name' => $value]);
        }

        return $tag;
    }

    public function test_programs_index_exposes_localized_filters_counts_sorting_pagination_and_transitions(): void
    {
        $this->login();
        $pillar = $this->createPillar('PH', 'الصحة العامة');
        $this->createProgram('health', ContentStatus::Published, $pillar);
        $this->createProgram('relief', ContentStatus::Draft, $pillar);
        $this->createProgram('recovery', ContentStatus::Draft);

        $this->get(route('cms.programs.index', [
            'locale' => self::LOCALE,
            'search' => 'health',
            'status' => 'published',
            'pillar' => $pillar->getKey(),
            'sort' => 'title_asc',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Cms/Content/Index')
            ->where('pageTitle', __('website.ui.cms_programs_page.title', [], self::LOCALE))
            ->where('filters', [
                'search' => 'health',
                'status' => 'published',
                'sort' => 'title_asc',
                'pillar' => (string) $pillar->getKey(),
            ])
            ->where('counts.all', 3)
            ->where('counts.published', 1)
            ->where('counts.draft', 2)
            ->where('items.0.title', 'برنامج health')
            ->where('items.0.pillar', 'الصحة العامة')
            ->where('pagination.total', 1)
            ->where('filterOptions.0.key', 'pillar')
            ->where('sortOptions.4.value', 'title_asc')
            ->where('transitions.draft.0.value', 'pending_review')
            ->where('can.create', true)
            ->has('statuses', 5)
            ->etc(),
        );

        $this->get(route('cms.programs.index', [
            'locale' => 'en',
            'status' => 'draft',
            'page' => 2,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('pageTitle', __('website.ui.cms_programs_page.title', [], 'en'))
            ->where('pagination.currentPage', 2)
            ->where('filters.status', 'draft')
            ->etc(),
        );
    }

    public function test_projects_index_and_edit_preserve_lifecycle_relations_and_media(): void
    {
        $this->login();
        $pillar = $this->createPillar('ED', 'التعليم');
        $program = $this->createProgram('education', ContentStatus::Published, $pillar);
        $image = $this->createImage('project-cover.png');
        $ongoing = $this->createProject('school', ProjectStatus::Ongoing, ContentStatus::Published, $program, $pillar, $image);
        $this->createProject('clinic', ProjectStatus::Planned, ContentStatus::Draft, $program, $pillar);

        $this->get(route('cms.projects.index', [
            'locale' => self::LOCALE,
            'program' => $program->getKey(),
            'pillar' => $pillar->getKey(),
            'project_status' => 'ongoing',
            'status' => 'published',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Cms/Content/Index')
            ->where('pageTitle', __('website.ui.cms_projects_page.title', [], self::LOCALE))
            ->where('items.0.id', $ongoing->getKey())
            ->where('items.0.program', 'برنامج education')
            ->where('items.0.pillar', 'التعليم')
            ->where('items.0.projectStatus', 'ongoing')
            ->where('items.0.projectStatusLabel', 'قيد التنفيذ')
            ->where('items.0.featuredImage.url', $image->url())
            ->where('pagination.total', 1)
            ->where('filterOptions.2.key', 'project_status')
            ->where('counts.published', 1)
            ->etc(),
        );

        $this->get(route('cms.projects.edit', [
            'locale' => self::LOCALE,
            'content' => $ongoing->getKey(),
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Cms/Content/Edit')
            ->where('resource', 'projects')
            ->where('pageTitle', __('website.ui.cms_projects_page.title', [], self::LOCALE))
            ->where('record.attributes.project_status', 'ongoing')
            ->where('record.status', 'published')
            ->where('options.projectStatuses.0.value', 'planned')
            ->where('options.programs.0.id', $program->getKey())
            ->where('options.media.0.id', $image->getKey())
            ->etc(),
        );
    }

    public function test_news_index_filters_category_tag_and_featured_and_edit_restores_tags(): void
    {
        $this->login();
        $category = $this->createCategory('field', 'ميداني');
        $tag = $this->createTag('relief', 'إغاثة');
        $otherTag = $this->createTag('policy', 'سياسات');
        $featured = $this->createArticle('featured', ContentStatus::Published, $category, $tag, true);
        $this->createArticle('plain', ContentStatus::Draft, $category, $otherTag, false);

        $this->get(route('cms.news.index', [
            'locale' => self::LOCALE,
            'category' => $category->getKey(),
            'tag' => $tag->getKey(),
            'featured' => '1',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Cms/Content/Index')
            ->where('pageTitle', __('website.ui.cms_news_page.title', [], self::LOCALE))
            ->where('filters.category', (string) $category->getKey())
            ->where('filters.tag', (string) $tag->getKey())
            ->where('filters.featured', '1')
            ->where('items.0.id', $featured->getKey())
            ->where('items.0.category', 'ميداني')
            ->where('items.0.isFeatured', true)
            ->where('items.0.tags.0', 'إغاثة')
            ->where('pagination.total', 1)
            ->where('filterOptions.3.key', 'featured')
            ->etc(),
        );

        $this->get(route('cms.news.index', ['locale' => self::LOCALE, 'featured' => '0']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pagination.total', 1)
                ->where('items.0.isFeatured', false)
                ->etc(),
            );

        $this->get(route('cms.news.edit', [
            'locale' => self::LOCALE,
            'content' => $featured->getKey(),
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Cms/Content/Edit')
            ->where('record.attributes.tags.0', $tag->getKey())
            ->where('options.tags.0.id', $tag->getKey())
            ->etc(),
        );
    }

    public function test_forms_persist_relationships_media_and_revision_actor(): void
    {
        $user = $this->login();
        $pillar = $this->createPillar('PS', 'السلام');
        $image = $this->createImage('featured.png');
        $program = $this->createProgram('peace', ContentStatus::Draft, $pillar);

        $this->post(route('cms.programs.store', ['locale' => self::LOCALE]), $this->programPayload('new-program', [
            'strategic_pillar_id' => $pillar->getKey(),
            'featured_image_id' => $image->getKey(),
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]))->assertRedirect();

        $createdProgram = Program::query()->where('featured_image_id', $image->getKey())->firstOrFail();
        $this->assertSame($pillar->getKey(), $createdProgram->strategic_pillar_id);
        $this->assertSame('2026-01-01', $createdProgram->start_date?->toDateString());
        $this->assertSame('2026-12-31', $createdProgram->end_date?->toDateString());

        $this->post(route('cms.projects.store', ['locale' => self::LOCALE]), $this->projectPayload('new-project', [
            'program_id' => $program->getKey(),
            'strategic_pillar_id' => $pillar->getKey(),
            'project_status' => ProjectStatus::Ongoing->value,
            'featured_image_id' => $image->getKey(),
        ]))->assertRedirect();

        $project = Project::query()->where('featured_image_id', $image->getKey())->firstOrFail();
        $this->assertSame($program->getKey(), $project->program_id);
        $this->assertSame(ProjectStatus::Ongoing, $project->status);
        $this->assertSame(ContentStatus::Draft, $project->workflow_status);

        $this->put(route('cms.projects.update', [
            'locale' => self::LOCALE,
            'content' => $project->getKey(),
        ]), $this->projectPayload('new-project', [
            'project_status' => ProjectStatus::Completed->value,
        ]))->assertRedirect();

        $this->assertSame(ProjectStatus::Completed, $project->fresh()->status);
        $this->assertSame(ContentStatus::Draft, $project->fresh()->workflow_status);

        $tag = $this->createTag('water', 'مياه');
        $this->post(route('cms.news.store', ['locale' => self::LOCALE]), $this->articlePayload('new-news', [
            'tags' => [$tag->getKey()],
            'is_featured' => true,
        ]))->assertRedirect();

        $article = Article::query()->where('is_featured', true)->firstOrFail();
        $this->assertTrue($article->tags()->whereKey($tag->getKey())->exists());

        $revision = ContentRevision::query()->latest('id')->firstOrFail();
        $this->assertSame($user->name, $revision->actor?->name);
        $this->assertSame(self::LOCALE, $revision->locale);

        $this->put(route('cms.news.update', [
            'locale' => self::LOCALE,
            'content' => $article->getKey(),
        ]), $this->articlePayload('new-news', ['tags' => []]))->assertRedirect();

        $this->assertSame(0, $article->fresh()->tags()->count());
    }

    public function test_transitions_are_permission_aware_and_preserve_executed_workflow_graph(): void
    {
        $this->login();
        $program = $this->createProgram('workflow', ContentStatus::Draft);

        $this->get(route('cms.programs.index', ['locale' => self::LOCALE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('transitions.draft', fn ($actions) => in_array('pending_review', array_column($actions->all(), 'value'), true))
                ->where('transitions.draft.0.confirmation', null)
                ->where('filters.status', 'all')
                ->etc(),
            );

        $this->post(route('cms.programs.status', [
            'locale' => self::LOCALE,
            'content' => $program->getKey(),
        ]), ['status' => 'pending_review'])->assertRedirect();

        $this->assertSame(ContentStatus::PendingReview, $program->fresh()->status);

        $viewer = User::factory()->create(['email' => 'viewer-redesign@example.test']);
        app(AccessManagementService::class)->syncRoles($viewer, []);
        $role = Role::create(['key' => 'programs-view-only', 'name' => 'Viewer']);
        $permission = Permission::create([
            'key' => 'programs.view',
            'name' => 'View programmes',
            'module' => 'content',
        ]);
        $role->permissions()->attach($permission);
        app(AccessManagementService::class)->syncRoles($viewer, [$role->getKey()]);

        $this->actingAs($viewer)
            ->get(route('cms.programs.index', ['locale' => self::LOCALE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.create', false)
                ->where('transitions.pending_review', [])
                ->etc(),
            );

        $this->post(route('cms.programs.status', [
            'locale' => self::LOCALE,
            'content' => $program->getKey(),
        ]), ['status' => 'published'])->assertForbidden();
    }
}
