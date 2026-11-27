<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Enums\ProjectStatus;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\ContactMessage;
use App\Models\DirectoryPartner;
use App\Models\DirectoryPartnerTranslation;
use App\Models\HomepageSection;
use App\Models\HomepageSectionItem;
use App\Models\Media;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectTranslation;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsiteOrganization;
use App\Models\WebsitePage;
use App\Services\AccessManagementService;
use App\Services\HomepageService;
use Database\Seeders\CmsStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CentralizedCmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(CmsStructureSeeder::class);
        app(HomepageService::class)->flush();
    }

    public function test_cms_structure_and_content_routes_fail_closed_without_permissions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('cms.content-management.index', ['locale' => 'en']))
            ->assertForbidden();

        $this->actingAs($user)
            ->get($this->contentRoute('index', 'hero'))
            ->assertForbidden();

        $this->actingAs($user)
            ->patch(route('cms.content-management.pages.toggle', [
                'locale' => 'en',
                'websitePage' => 'home',
            ]), ['enabled' => false])
            ->assertForbidden();

        $this->actingAs($user)
            ->post($this->contentRoute('store', 'hero'), $this->heroPayload())
            ->assertForbidden();

        $this->assertSame(0, HomepageSectionItem::query()->count());
        $this->assertTrue((bool) $this->page('home')->fresh()->is_enabled);
    }

    public function test_predefined_structure_is_localized_and_fixed_by_registry(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, ['website_structure.view']);

        $this->actingAs($user)
            ->get(route('cms.content-management.index', ['locale' => 'ar']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Cms/ContentManagement/Index')
                ->where('pages.0.key', 'home')
                ->where('pages.0.name', 'الرئيسية')
                ->where('sections.0.key', 'hero')
                ->where('sections.0.name', 'شريط الصور الرئيسي')
                ->where('can.view', true)
                ->where('can.update', false));

        $this->actingAs($user)
            ->get(route('cms.content-management.index', ['locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('pages.0.name', 'Home')
                ->where('sections.0.name', 'Hero slider'));

        $this->assertCount(count(config('cms_structure.pages', [])), WebsitePage::query()->get());
        $this->assertCount(
            count(config('cms_structure.sections', [])),
            HomepageSection::query()->get(),
        );
        $this->assertFalse(Route::has('cms.content-management.pages.store'));
        $this->assertFalse(Route::has('cms.content-management.sections.create'));
    }

    public function test_page_section_and_content_visibility_are_independent_and_never_delete_content(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, [
            'website_structure.view', 'website_structure.update', 'homepage.view', 'homepage.update',
        ]);

        $item = $this->section('hero')->items()->create([
            'item_type' => 'inline',
            'item_key' => 'slide-preserved',
            'is_published' => true,
            'sort_order' => 4,
        ]);
        $item->translations()->create([
            'locale' => 'en',
            'title' => 'Preserved hero title',
            'alt_text' => 'Preserved alt text',
        ]);
        app(HomepageService::class)->flush();

        $this->assertContains('hero', array_column(app(HomepageService::class)->sections('en'), 'key'));

        $this->actingAs($user)
            ->patch(route('cms.content-management.pages.toggle', [
                'locale' => 'en',
                'websitePage' => 'home',
            ]), ['enabled' => false])
            ->assertRedirect();

        $this->assertNotContains('hero', array_column(app(HomepageService::class)->sections('en'), 'key'));
        $this->assertSame(1, HomepageSectionItem::query()->whereKey($item->id)->count());
        $this->assertSame('Preserved hero title', $item->fresh()->t('title', 'en'));

        $this->actingAs($user)
            ->patch(route('cms.content-management.pages.toggle', [
                'locale' => 'en',
                'websitePage' => 'home',
            ]), ['enabled' => true])
            ->assertRedirect();

        $this->assertContains('hero', array_column(app(HomepageService::class)->sections('en'), 'key'));

        $this->actingAs($user)
            ->patch(route('cms.content-management.sections.toggle', [
                'locale' => 'en',
                'homepageSection' => 'hero',
            ]), ['enabled' => false])
            ->assertRedirect();

        $this->assertNotContains('hero', array_column(app(HomepageService::class)->sections('en'), 'key'));
        $this->assertSame(1, HomepageSection::query()->whereKey($this->section('hero')->id)->count());
        $this->assertSame('Preserved alt text', $item->fresh()->t('alt_text', 'en'));

        $this->actingAs($user)
            ->patch(route('cms.content-management.sections.toggle', [
                'locale' => 'en',
                'homepageSection' => 'hero',
            ]), ['enabled' => true])
            ->assertRedirect();

        $this->actingAs($user)
            ->patch($this->contentRoute('toggle', 'hero', ['sectionItem' => $item->id]), ['enabled' => false])
            ->assertRedirect();

        $hero = collect(app(HomepageService::class)->sections('en'))->firstWhere('key', 'hero');
        $this->assertSame([], $hero['items']);
        $this->assertSame('Preserved hero title', $item->fresh()->t('title', 'en'));
        $this->assertSame(4, (int) $item->fresh()->sort_order);

        $this->actingAs($user)
            ->patch($this->contentRoute('toggle', 'hero', ['sectionItem' => $item->id]), ['enabled' => true])
            ->assertRedirect();

        $hero = collect(app(HomepageService::class)->sections('en'))->firstWhere('key', 'hero');
        $this->assertSame('slide-preserved', $hero['items'][0]['key']);
    }

    public function test_hidden_predefined_pages_are_unreachable_publicly_and_restored_without_data_loss(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, ['website_structure.view', 'website_structure.update']);

        $this->get('/en')->assertOk();
        $this->getJson('/api/public/homepage?locale=en')->assertOk();

        $this->actingAs($user)
            ->patch(route('cms.content-management.pages.toggle', [
                'locale' => 'en',
                'websitePage' => 'home',
            ]), ['enabled' => false])
            ->assertRedirect();

        $this->get('/en')->assertNotFound();
        $this->getJson('/api/public/homepage?locale=en')->assertNotFound();
        $this->assertSame(1, HomepageSection::query()->where('page_key', 'home')->count() > 0 ? 1 : 0);

        $this->actingAs($user)
            ->patch(route('cms.content-management.pages.toggle', [
                'locale' => 'en',
                'websitePage' => 'home',
            ]), ['enabled' => true])
            ->assertRedirect();

        $this->get('/en')->assertOk();
        $this->getJson('/api/public/homepage?locale=en')->assertOk();
    }

    public function test_hero_content_crud_reorder_and_media_library_validation(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, ['homepage.view', 'homepage.update']);
        $publicImage = $this->image('hero-public.png', false);
        $restrictedImage = $this->image('hero-private.png', true);

        $this->actingAs($user)
            ->get($this->contentRoute('index', 'hero'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Cms/ContentManagement/Section')
                ->where('section.key', 'hero')
                ->where('section.content', 'slides')
                ->where('can.update', true));

        $this->actingAs($user)
            ->post($this->contentRoute('store', 'hero'), array_merge($this->heroPayload(), [
                'media_id' => $restrictedImage->id,
            ]))
            ->assertSessionHasErrors('media_id');

        $this->assertSame(0, $this->section('hero')->items()->count());

        $this->actingAs($user)
            ->post($this->contentRoute('store', 'hero'), array_merge($this->heroPayload(), [
                'media_id' => $publicImage->id,
                'translations' => [
                    'ar' => ['title' => 'عنوان البطل', 'alt_text' => 'صورة البطل'],
                    'en' => ['title' => 'Hero title', 'alt_text' => 'Hero image'],
                ],
            ]))
            ->assertRedirect();

        $item = $this->section('hero')->items()->firstOrFail();
        $this->assertSame('slide-hero', $item->item_key);
        $this->assertSame($publicImage->id, (int) $item->media_id);
        $this->assertSame('Hero title', $item->t('title', 'en'));
        $this->assertSame('عنوان البطل', $item->t('title', 'ar'));

        $this->actingAs($user)
            ->put($this->contentRoute('update', 'hero', ['sectionItem' => $item->id]), array_merge($this->heroPayload(), [
                'item_key' => 'slide-hero-updated',
                'media_id' => $publicImage->id,
                'translations' => ['en' => ['title' => 'Updated hero title']],
            ]))
            ->assertRedirect();

        $this->assertSame('slide-hero-updated', $item->fresh()->item_key);
        $this->assertSame('Updated hero title', $item->fresh()->t('title', 'en'));

        $second = $this->section('hero')->items()->create([
            'item_type' => 'inline',
            'item_key' => 'slide-second',
            'is_published' => true,
            'sort_order' => 5,
        ]);

        $this->actingAs($user)
            ->post($this->contentRoute('reorder', 'hero'), ['order' => [
                ['id' => $second->id, 'sort_order' => 0],
                ['id' => $item->id, 'sort_order' => 1],
            ]])
            ->assertRedirect();

        $this->assertSame(0, (int) $second->fresh()->sort_order);
        $this->assertSame(1, (int) $item->fresh()->sort_order);

        $this->actingAs($user)
            ->delete($this->contentRoute('destroy', 'hero', ['sectionItem' => $item->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('homepage_section_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('homepage_section_item_translations', ['homepage_section_item_id' => $item->id]);
    }

    public function test_news_and_projects_reuse_published_records_and_enforce_display_dates(): void
    {
        $publishedArticle = $this->article('Shared news marker', true);
        $draftArticle = $this->article('Hidden draft news marker', false);
        $publishedProject = $this->project('Shared project marker', true);
        $draftProject = $this->project('Hidden draft project marker', false);

        $news = $this->section('news');
        $projects = $this->section('latest_projects');

        $this->linkItem($news, 'article', $publishedArticle->id);
        $this->linkItem($news, 'article', $draftArticle->id);
        $this->linkItem($news, 'article', $this->article('Future news marker', true)->id, [
            'display_start_at' => now()->addDay(),
        ]);
        $this->linkItem($news, 'article', $this->article('Expired news marker', true)->id, [
            'display_end_at' => now()->subDay(),
        ]);
        $this->linkItem($projects, 'project', $publishedProject->id);
        $this->linkItem($projects, 'project', $draftProject->id);

        app(HomepageService::class)->flush();
        $newsPayload = collect(app(HomepageService::class)->sections('en'))->firstWhere('key', 'news');
        $titles = array_column($newsPayload['items'], 'title');

        $this->assertContains('Shared news marker', $titles);
        $this->assertContains('Shared project marker', $titles);
        $this->assertNotContains('Hidden draft news marker', $titles);
        $this->assertNotContains('Hidden draft project marker', $titles);
        $this->assertNotContains('Future news marker', $titles);
        $this->assertNotContains('Expired news marker', $titles);

        $user = User::factory()->create();
        $this->grantPermissions($user, ['homepage.view', 'homepage.update']);

        $this->actingAs($user)
            ->post($this->contentRoute('store', 'news'), [
                'item_type' => 'article',
                'item_id' => $publishedArticle->id,
                'is_published' => true,
                'sort_order' => 1,
                'display_start_at' => now()->addDays(2)->toDateTimeString(),
                'display_end_at' => now()->addDay()->toDateTimeString(),
                'translations' => ['en' => ['label' => 'Invalid range']],
            ])
            ->assertSessionHasErrors('display_end_at');
    }

    public function test_partners_are_selected_from_one_shared_published_source(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, ['homepage.view', 'homepage.update']);

        $published = DirectoryPartner::factory()->create(['directory_partner_category_id' => null]);
        $draft = DirectoryPartner::factory()->draft()->create(['directory_partner_category_id' => null]);

        DirectoryPartnerTranslation::query()
            ->where('directory_partner_id', $published->id)
            ->where('locale', 'en')
            ->update(['name' => 'Central partner marker']);
        DirectoryPartnerTranslation::query()
            ->where('directory_partner_id', $draft->id)
            ->where('locale', 'en')
            ->update(['name' => 'Hidden partner marker']);

        $section = $this->section('partners_list');
        $this->linkItem($section, 'partner', $published->id);
        $this->linkItem($section, 'partner', $draft->id);

        $this->actingAs($user)
            ->get($this->contentRoute('index', 'partners_list'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('section.source', 'partner')
                ->has('sources', 2));

        app(HomepageService::class)->flush();
        $partners = collect(app(HomepageService::class)->sections('en'))
            ->firstWhere('key', 'partners_list');
        $titles = array_column($partners['items'], 'title');

        $this->assertContains('Central partner marker', $titles);
        $this->assertNotContains('Hidden partner marker', $titles);

        $this->get('/en/partners')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('directory.total', 1)
                ->where('directory.groups.0.partners.0.name', 'Central partner marker'));
    }

    public function test_shared_organization_content_reappears_on_the_internal_about_page(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, ['homepage.view', 'homepage.update']);

        $this->actingAs($user)
            ->put($this->contentRoute('organization', 'about'), [
                'translations' => [
                    'ar' => [
                        'title' => 'عنوان مؤسسي موحد',
                        'description' => 'وصف مؤسسي موحد',
                        'vision' => 'رؤية موحدة',
                        'mission' => 'رسالة موحدة',
                    ],
                    'en' => [
                        'title' => 'Shared organization title',
                        'description' => 'Shared organization description',
                        'vision' => 'Shared vision',
                        'mission' => 'Shared mission',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(1, WebsiteOrganization::query()->count());

        $this->get('/en/about')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('about.title', 'Shared organization title')
                ->where('about.body', 'Shared organization description')
                ->where('about.vision.body', 'Shared vision')
                ->where('about.mission.body', 'Shared mission'));

        $this->get('/ar/about')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('about.title', 'عنوان مؤسسي موحد')
                ->where('about.vision.body', 'رؤية موحدة'));
    }

    public function test_hidden_contact_page_keeps_inquiries_private_and_is_restored(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, ['website_structure.view', 'website_structure.update']);

        $this->actingAs($user)
            ->patch(route('cms.content-management.pages.toggle', [
                'locale' => 'en',
                'websitePage' => 'contact',
            ]), ['enabled' => false])
            ->assertRedirect();

        $this->get('/en/contact')->assertNotFound();
        $this->post('/en/contact', [
            'name' => 'Blocked sender',
            'email' => 'blocked@example.test',
            'subject' => 'Blocked subject',
            'message' => 'This submission must not be stored while the page is hidden.',
        ])->assertNotFound();

        $this->assertSame(0, ContactMessage::query()->count());

        $this->actingAs($user)
            ->patch(route('cms.content-management.pages.toggle', [
                'locale' => 'en',
                'websitePage' => 'contact',
            ]), ['enabled' => true])
            ->assertRedirect();

        ContactMessage::query()->create([
            'reference' => (string) Str::ulid(),
            'category' => 'general',
            'name' => 'Private sender',
            'email' => 'private-inquiry@example.test',
            'subject' => 'Private inquiry marker',
            'message' => 'Private inquiry body marker.',
            'locale' => 'en',
            'consent' => true,
            'status' => 'new',
        ]);

        $response = $this->get('/en/contact')->assertOk();
        $this->assertStringNotContainsString('Private inquiry marker', (string) $response->getContent());
        $this->assertStringNotContainsString('private-inquiry@example.test', (string) $response->getContent());
    }

    public function test_public_section_serialization_preloads_linked_records_without_item_n_plus_one_queries(): void
    {
        HomepageSection::query()
            ->whereNotIn('key', ['hero', 'news'])
            ->update(['is_enabled' => false]);

        $firstArticle = $this->article('Query baseline article', true);
        $this->linkItem($this->section('hero'), 'inline', 0, ['item_key' => 'query-hero']);
        $this->linkItem($this->section('news'), 'article', $firstArticle->id);
        app(HomepageService::class)->flush();

        $baseline = $this->countHomepageQueries();

        foreach (range(1, 8) as $index) {
            $this->linkItem($this->section('news'), 'article', $this->article('Query article '.$index, true)->id);
        }
        app(HomepageService::class)->flush();

        $expanded = $this->countHomepageQueries();

        $this->assertLessThan(30, $expanded);
        $this->assertLessThanOrEqual($baseline + 2, $expanded);
    }

    /** @return list<string> */
    private function heroPayload(): array
    {
        return [
            'item_type' => 'inline',
            'item_key' => 'slide-hero',
            'is_published' => true,
            'sort_order' => 1,
            'translations' => [
                'ar' => ['title' => 'عنوان البطل'],
                'en' => ['title' => 'Hero title'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function contentRoute(string $name, string $section, array $parameters = []): string
    {
        return route('cms.content.'.$name, array_merge([
            'locale' => 'en',
            'section' => $section,
        ], $parameters));
    }

    /** @param list<string> $permissions */
    private function grantPermissions(User $user, array $permissions): void
    {
        $role = Role::query()->create([
            'key' => 'central-cms-'.Str::lower(Str::random(10)),
            'name' => 'Central CMS tester',
        ]);

        foreach ($permissions as $permission) {
            $record = Permission::query()->firstOrCreate(
                ['key' => $permission],
                ['name' => $permission, 'module' => 'website'],
            );
            $role->permissions()->attach($record);
        }

        app(AccessManagementService::class)->syncRoles($user, [$role->id]);
    }

    private function section(string $key): HomepageSection
    {
        return HomepageSection::query()->where('key', $key)->firstOrFail();
    }

    private function page(string $key): WebsitePage
    {
        return WebsitePage::query()->where('key', $key)->firstOrFail();
    }

    private function image(string $name, bool $restricted): Media
    {
        return Media::query()->create([
            'uuid' => (string) Str::ulid(),
            'disk' => $restricted ? 'private' : 'public',
            'path' => 'media/cms/'.$name,
            'type' => MediaType::Image->value,
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => 2048,
            'width' => 800,
            'height' => 600,
            'checksum' => hash('sha256', $name),
            'original_name' => $name,
            'is_restricted' => $restricted,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function linkItem(
        HomepageSection $section,
        string $type,
        int $recordId,
        array $overrides = [],
    ): HomepageSectionItem {
        return $section->items()->create(array_merge([
            'item_type' => $type,
            'item_id' => $type === 'inline' ? null : $recordId,
            'item_key' => $type === 'inline' ? ($overrides['item_key'] ?? 'item-'.Str::lower(Str::random(8))) : null,
            'is_published' => true,
            'sort_order' => 1,
        ], $overrides));
    }

    private function article(string $title, bool $published): Article
    {
        $article = Article::query()->create([
            'status' => $published ? ContentStatus::Published->value : ContentStatus::Draft->value,
            'published_at' => $published ? now()->subDay() : null,
            'display_order' => 1,
        ]);

        foreach (['ar' => 'خبر '.$title, 'en' => $title] as $locale => $value) {
            ArticleTranslation::query()->create([
                'article_id' => $article->id,
                'locale' => $locale,
                'slug' => Str::slug($value).'-'.$article->id.'-'.$locale,
                'title' => $value,
                'excerpt' => 'Excerpt '.$value,
            ]);
        }

        return $article;
    }

    private function project(string $title, bool $published): Project
    {
        $project = Project::query()->create([
            'status' => ProjectStatus::Ongoing->value,
            'workflow_status' => $published ? ContentStatus::Published->value : ContentStatus::Draft->value,
            'published_at' => $published ? now()->subDay() : null,
            'display_order' => 1,
        ]);

        foreach (['ar' => 'مشروع '.$title, 'en' => $title] as $locale => $value) {
            ProjectTranslation::query()->create([
                'project_id' => $project->id,
                'locale' => $locale,
                'slug' => Str::slug($value).'-'.$project->id.'-'.$locale,
                'title' => $value,
                'summary' => 'Summary '.$value,
            ]);
        }

        return $project;
    }

    private function countHomepageQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            app(HomepageService::class)->sections('en');

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
}
