<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleCategoryTranslation;
use App\Models\ArticleTranslation;
use App\Models\ContactMessage;
use App\Models\DirectoryPartner;
use App\Models\DirectoryPartnerCategory;
use App\Models\DirectoryPartnerCategoryTranslation;
use App\Models\DirectoryPartnerTranslation;
use App\Models\HomepageSection;
use App\Models\HomepageSectionItem;
use App\Models\HomepageSectionItemTranslation;
use App\Models\HomepageSectionTranslation;
use App\Models\Media;
use App\Models\Program;
use App\Models\ProgramTranslation;
use App\Models\Project;
use App\Models\ProjectTranslation;
use App\Models\StrategicPillar;
use App\Models\StrategicPillarTranslation;
use App\Models\WebsiteSetting;
use App\Models\WebsiteSettingTranslation;
use App\Services\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        app(SiteSettings::class)->flush();
    }

    public function test_public_pages_render_distinct_inertia_components_with_seo_props(): void
    {
        $expected = [
            '/en/about' => 'Public/About',
            '/en/programs' => 'Public/Programs',
            '/en/media' => 'Public/MediaCenter',
            '/en/contact' => 'Public/Contact',
            '/en/partners' => 'Public/Partners',
        ];

        foreach ($expected as $url => $component) {
            $response = $this->get($url)->assertOk();
            $page = $this->inertiaPage($response);

            $this->assertSame($component, $page['component'] ?? null, $url);
            $this->assertIsArray($page['props'] ?? null, $url);
            $this->assertNotEmpty((string) ($page['props']['meta']['title'] ?? ''), $url);
            $this->assertStringContainsString('/en', (string) ($page['props']['meta']['canonical'] ?? ''), $url);
        }
    }

    public function test_about_page_only_publishes_cms_content_with_locale_fallback(): void
    {
        $section = $this->section('about');
        $this->sectionTranslation($section, 'en', [
            'eyebrow' => 'Who we are',
            'title' => 'About PCDR',
            'body' => 'Our institutional overview.',
        ]);
        $this->sectionItem($section, 'vision', 1, [
            'en' => ['title' => 'Our vision', 'body' => 'A resilient Palestine.'],
        ], $this->media('about-vision.jpg'));
        $this->sectionItem($section, 'mission', 2, [
            'en' => ['title' => 'Our mission', 'body' => 'Coordinate principled response.'],
        ]);
        $this->sectionItem($section, 'objective-1', 3, [
            'en' => ['title' => 'Serve vulnerable families'],
        ]);
        $this->sectionItem($section, 'board-1', 4, [
            'en' => ['title' => 'Board member', 'body' => 'Governance role'],
        ]);
        $this->sectionItem($section, 'achievement-1', 5, [
            'en' => ['title' => '11 strategic pillars'],
        ]);
        $this->sectionItem($section, 'draft-hidden', 6, [
            'en' => ['title' => 'Hidden draft item'],
        ], null, false);

        $pillar = $this->pillar('A1', 'en', 'Humanitarian response', 1);
        $this->pillar('B2', 'en', 'Draft pillar', 2, false);

        $about = $this->props($this->get('/ar/about'))['about'];

        $this->assertSame('About PCDR', $about['title'] ?? null, 'English CMS copy must fall back for Arabic requests.');
        $this->assertSame('Our institutional overview.', $about['body'] ?? null);
        $this->assertSame('A resilient Palestine.', $about['vision']['body'] ?? null);
        $this->assertSame('Coordinate principled response.', $about['mission']['body'] ?? null);
        $this->assertSame('Serve vulnerable families', $about['objectives'][0]['title'] ?? null);
        $this->assertSame('Board member', $about['board'][0]['title'] ?? null);
        $this->assertSame('11 strategic pillars', $about['achievements'][0]['title'] ?? null);
        $this->assertSame('Humanitarian response', $about['pillars'][0]['title'] ?? null);
        $this->assertCount(1, $about['pillars']);
        $this->assertStringContainsString('about-vision.jpg', (string) ($about['image']['url'] ?? ''));

        $serialized = json_encode($about, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Hidden draft item', $serialized);
        $this->assertStringNotContainsString('Draft pillar', $serialized);
    }

    public function test_programs_page_only_lists_published_programmes_and_supports_area_filters(): void
    {
        $first = $this->pillar('A1', 'en', 'Humanitarian response', 1);
        $second = $this->pillar('B2', 'en', 'Food security', 2);
        $draftArea = $this->pillar('C3', 'en', 'Draft area', 3, false);

        $publicImage = $this->media('program-cover.jpg');
        $restrictedImage = $this->media('restricted-project.jpg', true);

        $program = Program::query()->create([
            'strategic_pillar_id' => $first->id,
            'status' => ContentStatus::Published->value,
            'published_at' => now()->subDay(),
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'featured_image_id' => $publicImage->id,
            'display_order' => 1,
        ]);
        $this->programTranslation($program, 'en', 'Emergency response', 'Rapid assistance');
        $this->programTranslation($program, 'ar', 'الاستجابة الطارئة', 'مساعدة سريعة');

        $project = Project::query()->create([
            'program_id' => $program->id,
            'strategic_pillar_id' => $first->id,
            'status' => 'ongoing',
            'workflow_status' => ContentStatus::Published->value,
            'published_at' => now()->subDay(),
            'start_date' => '2026-02-01',
            'featured_image_id' => $restrictedImage->id,
            'display_order' => 1,
        ]);
        $this->projectTranslation($project, 'en', 'Mobile clinics', 'Field medical teams');

        $draftProject = Project::query()->create([
            'program_id' => $program->id,
            'strategic_pillar_id' => $first->id,
            'status' => 'planned',
            'workflow_status' => ContentStatus::Draft->value,
        ]);
        $this->projectTranslation($draftProject, 'en', 'Hidden project', 'Do not publish');

        $draftProgram = Program::query()->create([
            'strategic_pillar_id' => $first->id,
            'status' => ContentStatus::Draft->value,
        ]);
        $this->programTranslation($draftProgram, 'en', 'Hidden programme', 'Draft');

        $unpublishedAreaProgram = Program::query()->create([
            'strategic_pillar_id' => $draftArea->id,
            'status' => ContentStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $this->programTranslation($unpublishedAreaProgram, 'en', 'Hidden area programme', 'Draft area');

        $payload = $this->props($this->get('/en/programs'))['programs'];

        $this->assertSame(['A1', 'B2'], array_column($payload['areas'], 'code'));
        $this->assertNull($payload['activeArea']);
        $this->assertCount(1, $payload['programs']);
        $this->assertSame('Emergency response', $payload['programs'][0]['title']);
        $this->assertSame('Humanitarian response', $payload['programs'][0]['area']['title']);
        $this->assertStringStartsWith('2026-01-01T', (string) $payload['programs'][0]['startDate']);
        $this->assertStringContainsString('program-cover.jpg', (string) $payload['programs'][0]['image']['url']);
        $this->assertSame('Mobile clinics', $payload['programs'][0]['projects'][0]['title']);
        $this->assertNull($payload['programs'][0]['projects'][0]['image']);

        $filtered = $this->props($this->get('/en/programs?area=A1'))['programs'];
        $this->assertSame('A1', $filtered['activeArea']);
        $this->assertSame(['Emergency response'], array_column($filtered['programs'], 'title'));

        $other = $this->props($this->get('/en/programs?area=B2'))['programs'];
        $this->assertSame('B2', $other['activeArea']);
        $this->assertSame([], $other['programs']);

        $invalid = $this->props($this->get('/en/programs?area=not-an-area'))['programs'];
        $this->assertSame('not-an-area', $invalid['activeArea']);
        $this->assertSame([], $invalid['programs']);

        $serialized = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Hidden programme', $serialized);
        $this->assertStringNotContainsString('Hidden project', $serialized);
        $this->assertStringNotContainsString('Hidden area programme', $serialized);
    }

    public function test_media_center_has_no_publications_and_only_publishes_approved_items(): void
    {
        $newsCategory = ArticleCategory::query()->create(['slug' => 'news', 'display_order' => 1]);
        $this->categoryTranslation($newsCategory, 'en', 'News');
        $activityCategory = ArticleCategory::query()->create(['slug' => 'activities', 'display_order' => 2]);
        $this->categoryTranslation($activityCategory, 'en', 'Activities');
        $activityCategoryArabic = ArticleCategory::query()->create(['slug' => 'field-visits', 'display_order' => 3]);
        $this->categoryTranslation($activityCategoryArabic, 'ar', 'زيارات ميدانية');

        $publicCover = $this->media('article-cover.jpg');
        $restrictedCover = $this->media('restricted-cover.jpg', true);

        $this->article($newsCategory, ContentStatus::Published, now()->subDay(), $publicCover->id, 'Latest update', 'Field summary');
        $this->article($activityCategory, ContentStatus::Published, now()->subDay(), null, 'Community workshop', 'Training session');
        $this->article($activityCategoryArabic, ContentStatus::Published, now()->subDay(), null, 'Field visit', 'Site visit');
        $this->article($newsCategory, ContentStatus::Draft, null, null, 'Draft news', 'Hidden');
        $this->article($newsCategory, ContentStatus::Published, now()->addDay(), null, 'Future news', 'Hidden');
        $this->article($newsCategory, ContentStatus::Published, now()->subDay(), $restrictedCover->id, 'Restricted cover', 'Hidden cover');

        $albums = $this->section('albums');
        $this->sectionTranslation($albums, 'en', ['title' => 'Photo album']);
        $this->sectionItem($albums, 'photo-1', 1, [
            'en' => ['title' => 'Clinic opening', 'caption' => 'Opening day'],
        ], $publicCover);
        $this->sectionItem($albums, 'photo-hidden', 2, [
            'en' => ['title' => 'Hidden photo'],
        ], null, false);

        $stories = $this->section('stories');
        $this->sectionTranslation($stories, 'en', ['title' => 'Success stories']);
        $this->sectionItem($stories, 'story-1', 1, [
            'en' => ['title' => 'A family rebuilt', 'body' => 'After the floods'],
        ]);

        $media = $this->props($this->get('/en/media'))['media'];

        $newsTitles = array_column($media['news'], 'title');
        $activityTitles = array_column($media['activities'], 'title');
        sort($newsTitles);
        sort($activityTitles);

        $this->assertSame(['news', 'activities', 'albums', 'stories'], array_keys($media));
        $this->assertArrayNotHasKey('publications', $media);
        $this->assertSame(['Latest update', 'Restricted cover'], $newsTitles);
        $this->assertSame(['Community workshop', 'Field visit'], $activityTitles);
        $this->assertSame('Photo album', $media['albums']['title']);
        $this->assertSame('Clinic opening', $media['albums']['items'][0]['title']);
        $this->assertSame('A family rebuilt', $media['stories']['items'][0]['title']);
        $latest = collect($media['news'])->firstWhere('title', 'Latest update');
        $restricted = collect($media['news'])->firstWhere('title', 'Restricted cover');
        $this->assertStringContainsString('article-cover.jpg', (string) ($latest['image']['url'] ?? ''));
        $this->assertIsArray($restricted);
        $this->assertArrayHasKey('image', $restricted);
        $this->assertNull($restricted['image']);
        $this->assertStringStartsWith('2026-', (string) ($latest['publishedAt'] ?? ''));

        $serialized = json_encode($media, JSON_THROW_ON_ERROR);
        foreach (['Draft news', 'Future news', 'Hidden photo'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $serialized);
        }
    }

    public function test_contact_details_are_locale_aware_public_and_safely_filtered(): void
    {
        $this->setting('contact.email', 'pcdr@example.org');
        $this->setting('contact.phone', '+970 82 123 456');
        $this->setting('contact.hours', '09:00 - 17:00');
        $this->setting('contact.map_link', 'javascript:alert(1)');
        $this->setting('contact.private_note', 'Do not expose', false);
        $this->setting('social.facebook', '@pcdr');
        $this->setting('social.x', 'javascript:alert(1)');
        $this->localizedSetting('contact.address', [
            'en' => 'Gaza Strip',
            'ar' => 'قطاع غزة',
        ]);
        app(SiteSettings::class)->flush();

        $english = $this->props($this->get('/en/contact'))['contact'];

        $this->assertSame('pcdr@example.org', $english['email'] ?? null);
        $this->assertSame('+970 82 123 456', $english['phone'] ?? null);
        $this->assertSame('Gaza Strip', $english['address'] ?? null);
        $this->assertSame('09:00 - 17:00', $english['hours'] ?? null);
        $this->assertSame('09:00 - 17:00', $english['workingHours'] ?? null);
        $this->assertNull($english['mapLink']);
        $this->assertSame('facebook', $english['socialLinks'][0]['platform'] ?? null);
        $this->assertSame('https://www.facebook.com/pcdr', $english['socialLinks'][0]['url'] ?? null);

        $arabic = $this->props($this->get('/ar/contact'))['contact'];
        $this->assertSame('قطاع غزة', $arabic['address'] ?? null);

        $serialized = json_encode([$english, $arabic], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Do not expose', $serialized);
        $this->assertStringNotContainsString('javascript:', $serialized);
    }

    public function test_public_pages_never_expose_other_people_contact_messages(): void
    {
        ContactMessage::query()->create([
            'reference' => (string) Str::ulid(),
            'category' => 'general',
            'name' => 'Other sender',
            'email' => 'private@example.test',
            'subject' => 'Private subject marker',
            'message' => 'Private message marker that must never appear publicly.',
            'locale' => 'en',
            'consent' => true,
            'status' => 'new',
        ]);

        $response = $this->get('/en/contact')->assertOk();
        $props = $this->props($response);

        $this->assertArrayNotHasKey('messages', $props);
        $this->assertArrayNotHasKey('contactMessages', $props);
        $this->assertStringNotContainsString('Private subject marker', (string) $response->getContent());
        $this->assertStringNotContainsString('Private message marker', (string) $response->getContent());
        $this->assertStringNotContainsString('private@example.test', (string) $response->getContent());
    }

    public function test_contact_store_persists_valid_messages_and_flashes_success(): void
    {
        Mail::fake();

        $response = $this->post('/en/contact', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.test',
            'subject' => 'Programme question',
            'message' => 'I would like to know more about your programmes.',
        ])->assertRedirect();

        $response->assertSessionHas('flash.success');
        $response->assertSessionHas('flash.reference');

        $message = ContactMessage::query()->firstOrFail();
        $this->assertSame('general', $message->category);
        $this->assertSame('Jane Doe', $message->name);
        $this->assertSame('jane@example.test', $message->email);
        $this->assertSame('Programme question', $message->subject);
        $this->assertSame('I would like to know more about your programmes.', $message->message);
        $this->assertSame('en', $message->locale);
        $this->assertTrue($message->consent);
        $this->assertSame('new', $message->status);
        $this->assertNotNull($message->ip_hash);
        $this->assertSame(64, strlen((string) $message->ip_hash));
        $this->assertNotSame((string) $message->reference, '');
    }

    public function test_contact_store_rejects_invalid_fields_and_honeypot_submissions(): void
    {
        $this->post('/en/contact', [])->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
        $this->post('/en/contact', [
            'name' => 'Jane Doe',
            'email' => 'not-an-email',
            'subject' => 'Hi',
            'message' => 'Too short',
        ])->assertSessionHasErrors(['email', 'subject', 'message']);

        $this->post('/en/contact', [
            'name' => 'Bot',
            'email' => 'bot@example.test',
            'subject' => 'Automated submission',
            'message' => 'This message should never be stored because it came from a bot.',
            'honeypot' => 'filled-by-bot',
        ])->assertSessionHasErrors('honeypot');

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_partners_page_hides_unapproved_and_inactive_partners(): void
    {
        $category = DirectoryPartnerCategory::factory()->create([
            'slug' => 'public-category',
            'is_active' => true,
            'display_order' => 1,
        ]);
        DirectoryPartnerCategoryTranslation::query()->updateOrCreate(
            ['directory_partner_category_id' => $category->id, 'locale' => 'en'],
            ['name' => 'Public category'],
        );

        $this->partner($category, 'Published partner', 'شريك منشور', ContentStatus::Published);
        $this->partner($category, 'Draft partner', 'شريك مسودة', ContentStatus::Draft);
        $this->partner($category, 'Archived partner', 'شريك مؤرشف', ContentStatus::Archived);
        $this->partner($category, 'Future partner', 'شريك قادم', ContentStatus::Published, now()->addDay());

        $response = $this->get('/en/partners')->assertOk();
        $directory = $this->props($response)['directory'];

        $this->assertSame(1, $directory['total'] ?? null);
        $this->assertTrue($directory['hasPartners'] ?? false);
        $this->assertSame('Published partner', $directory['groups'][0]['partners'][0]['name'] ?? null);

        $serialized = json_encode($directory, JSON_THROW_ON_ERROR);
        foreach (['Draft partner', 'Archived partner', 'Future partner'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $serialized);
        }
    }

    /** @return array<string, mixed> */
    private function props(TestResponse $response): array
    {
        return (array) ($this->inertiaPage($response)['props'] ?? []);
    }

    /** @return array<string, mixed> */
    private function inertiaPage(TestResponse $response): array
    {
        $html = (string) $response->getContent();
        $this->assertSame(
            1,
            preg_match('/<script data-page="app" type="application\/json">(.*?)<\/script>/s', $html, $matches),
            'Inertia page payload was not found in the HTML response.',
        );

        $page = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($page);

        return $page;
    }

    private function section(string $key): HomepageSection
    {
        return HomepageSection::query()->create([
            'key' => $key,
            'type' => $key,
            'status' => ContentStatus::Published->value,
            'published_at' => now()->subDay(),
            'is_enabled' => true,
            'display_order' => 1,
        ]);
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private function sectionTranslation(
        HomepageSection $section,
        string $locale,
        array $values,
    ): void {
        HomepageSectionTranslation::query()->create(array_merge([
            'homepage_section_id' => $section->id,
            'locale' => $locale,
        ], $values));
    }

    /**
     * @param  array<string, array<string, string>>  $translations
     */
    private function sectionItem(
        HomepageSection $section,
        string $key,
        int $order,
        array $translations,
        ?Media $media = null,
        bool $published = true,
    ): void {
        $item = HomepageSectionItem::query()->create([
            'homepage_section_id' => $section->id,
            'item_type' => 'inline',
            'item_key' => $key,
            'media_id' => $media?->id,
            'is_published' => $published,
            'sort_order' => $order,
        ]);

        foreach ($translations as $locale => $values) {
            HomepageSectionItemTranslation::query()->create(array_merge([
                'homepage_section_item_id' => $item->id,
                'locale' => $locale,
            ], $values));
        }
    }

    private function media(string $name, bool $restricted = false): Media
    {
        return Media::query()->create([
            'uuid' => (string) Str::ulid(),
            'disk' => $restricted ? 'private' : 'public',
            'path' => 'media/public-pages/'.$name,
            'type' => MediaType::Image->value,
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 2048,
            'width' => 800,
            'height' => 600,
            'checksum' => hash('sha256', $name),
            'original_name' => $name,
            'is_restricted' => $restricted,
        ]);
    }

    private function pillar(
        string $code,
        string $locale,
        string $title,
        int $order,
        bool $published = true,
    ): StrategicPillar {
        $pillar = StrategicPillar::query()->create([
            'code' => $code,
            'status' => $published ? ContentStatus::Published->value : ContentStatus::Draft->value,
            'published_at' => $published ? now()->subDay() : null,
            'display_order' => $order,
        ]);

        StrategicPillarTranslation::query()->create([
            'strategic_pillar_id' => $pillar->id,
            'locale' => $locale,
            'slug' => 'pillar-'.Str::lower($code),
            'title' => $title,
            'short_title' => $title,
            'description' => $title.' description',
        ]);

        return $pillar;
    }

    private function programTranslation(Program $program, string $locale, string $title, string $summary): void
    {
        ProgramTranslation::query()->create([
            'program_id' => $program->id,
            'locale' => $locale,
            'slug' => 'program-'.$program->id.'-'.$locale,
            'title' => $title,
            'summary' => $summary,
            'description' => $summary.' description',
        ]);
    }

    private function projectTranslation(Project $project, string $locale, string $title, string $summary): void
    {
        ProjectTranslation::query()->create([
            'project_id' => $project->id,
            'locale' => $locale,
            'slug' => 'project-'.$project->id.'-'.$locale,
            'title' => $title,
            'summary' => $summary,
        ]);
    }

    private function categoryTranslation(ArticleCategory $category, string $locale, string $name): void
    {
        ArticleCategoryTranslation::query()->create([
            'article_category_id' => $category->id,
            'locale' => $locale,
            'name' => $name,
        ]);
    }

    private function article(
        ArticleCategory $category,
        ContentStatus $status,
        ?Carbon $publishedAt,
        ?int $coverMediaId,
        string $title,
        string $summary,
    ): Article {
        $article = Article::query()->create([
            'article_category_id' => $category->id,
            'status' => $status->value,
            'published_at' => $publishedAt,
            'cover_media_id' => $coverMediaId,
            'display_order' => 1,
        ]);

        ArticleTranslation::query()->create([
            'article_id' => $article->id,
            'locale' => 'en',
            'slug' => 'article-'.$article->id.'-en',
            'title' => $title,
            'excerpt' => $summary,
            'body' => $summary.' body',
        ]);

        return $article;
    }

    private function setting(string $key, string $value, bool $public = true): void
    {
        WebsiteSetting::query()->create([
            'group' => Str::before($key, '.'),
            'key' => $key,
            'type' => 'string',
            'is_localized' => false,
            'is_public' => $public,
            'value' => $value,
        ]);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function localizedSetting(string $key, array $values): void
    {
        $setting = WebsiteSetting::query()->create([
            'group' => Str::before($key, '.'),
            'key' => $key,
            'type' => 'string',
            'is_localized' => true,
            'is_public' => true,
        ]);

        foreach ($values as $locale => $value) {
            WebsiteSettingTranslation::query()->create([
                'website_setting_id' => $setting->id,
                'locale' => $locale,
                'value' => $value,
            ]);
        }
    }

    private function partner(
        DirectoryPartnerCategory $category,
        string $englishName,
        string $arabicName,
        ContentStatus $status,
        ?Carbon $publishedAt = null,
    ): void {
        $partner = DirectoryPartner::factory()->create([
            'directory_partner_category_id' => $category->id,
            'status' => $status->value,
            'published_at' => $publishedAt ?? ($status === ContentStatus::Published ? now()->subDay() : null),
            'display_order' => 1,
        ]);

        DirectoryPartnerTranslation::query()->updateOrCreate(
            ['directory_partner_id' => $partner->id, 'locale' => 'en'],
            [
                'name' => $englishName,
                'slug' => Str::slug($englishName).'-en',
                'description' => 'English description',
            ],
        );
        DirectoryPartnerTranslation::query()->updateOrCreate(
            ['directory_partner_id' => $partner->id, 'locale' => 'ar'],
            [
                'name' => $arabicName,
                'slug' => 'partner-'.$partner->id.'-ar',
                'description' => 'وصف عربي',
            ],
        );
    }
}
