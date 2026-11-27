<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\HomepageSection;
use App\Models\Media;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessManagementService;
use App\Services\HomepageService;
use Carbon\CarbonImmutable;
use Database\Seeders\CmsStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class HeroAndTickerCmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(CmsStructureSeeder::class);
        app(HomepageService::class)->flush();
    }

    public function test_hero_and_ticker_editors_are_authorized_and_expose_registry_metadata(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get($this->contentRoute('index', 'hero'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get($this->contentRoute('index', 'ticker'))
            ->assertForbidden();

        $this->grantPermissions($user, ['homepage.view', 'homepage.update']);

        $this->actingAs($user)
            ->get($this->contentRoute('index', 'hero'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Cms/ContentManagement/Section')
                ->where('section.key', 'hero')
                ->where('section.content', 'slides')
                ->where('can.update', true)
                ->has('items')
                ->has('media'));

        $this->actingAs($user)
            ->get($this->contentRoute('index', 'ticker'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Cms/ContentManagement/Section')
                ->where('section.key', 'ticker')
                ->where('section.content', 'announcements')
                ->where('section.source', 'article')
                ->where('can.update', true)
                ->has('sources'));
    }

    public function test_hero_save_generates_a_slide_key_replaces_media_and_preserves_legacy_metadata(): void
    {
        $user = $this->editor();
        $firstImage = $this->image('first-hero.png');
        $replacementImage = $this->image('replacement-hero.png');

        $this->actingAs($user)
            ->post($this->contentRoute('store', 'hero'), [
                'item_type' => 'inline',
                'is_published' => true,
                'sort_order' => 0,
                'media_id' => $firstImage->id,
                'motif_key' => 'm-sun',
                'translations' => [
                    'ar' => [
                        'label' => 'البرامج',
                        'title' => 'عنوان البطل',
                        'body' => 'وصف البطل',
                        'alt_text' => 'نص بديل قديم',
                        'date_label' => '٢٠٢٦',
                    ],
                    'en' => [
                        'label' => 'Programmes',
                        'title' => 'Hero title',
                        'body' => 'Hero body',
                        'alt_text' => 'Legacy alt text',
                        'date_label' => '2026',
                    ],
                ],
            ])
            ->assertRedirect();

        $slide = $this->section('hero')->items()->firstOrFail();
        $this->assertStringStartsWith('hero-', (string) $slide->item_key);
        $this->assertTrue($slide->isHeroSlide());
        $this->assertSame($firstImage->id, (int) $slide->media_id);
        $this->assertSame('m-sun', $slide->motif_key);

        $this->section('hero')->items()->create([
            'item_type' => 'inline',
            'item_key' => 'primary',
            'is_published' => true,
            'sort_order' => 1,
        ]);
        $this->section('hero')->items()->create([
            'item_type' => 'inline',
            'item_key' => 'ticker-legacy',
            'is_published' => true,
            'sort_order' => 2,
        ]);

        $hero = collect(app(HomepageService::class)->sections('en'))->firstWhere('key', 'hero');
        $slideKeys = collect($hero['items'])->filter(fn (array $item): bool => $item['slide'] === true)
            ->pluck('key')
            ->all();

        $this->assertSame([$slide->item_key], $slideKeys);

        $this->actingAs($user)
            ->put($this->contentRoute('update', 'hero', ['sectionItem' => $slide->id]), [
                'item_type' => 'inline',
                'is_published' => true,
                'sort_order' => 0,
                'media_id' => $replacementImage->id,
                'motif_key' => 'm-bars',
                'translations' => [
                    'ar' => ['title' => 'عنوان محدث', 'body' => 'وصف محدث'],
                    'en' => ['title' => 'Updated hero title', 'body' => 'Updated hero body'],
                ],
            ])
            ->assertRedirect();

        $saved = $slide->fresh();
        $this->assertSame($slide->item_key, $saved->item_key);
        $this->assertSame($replacementImage->id, (int) $saved->media_id);
        $this->assertSame('m-bars', $saved->motif_key);
        $this->assertSame('Updated hero title', $saved->t('title', 'en'));
        $this->assertSame('Legacy alt text', $saved->t('alt_text', 'en'));
        $this->assertSame('2026', $saved->t('date_label', 'en'));
        $this->assertSame('نص بديل قديم', $saved->t('alt_text', 'ar'));
        $this->assertSame('٢٠٢٦', $saved->t('date_label', 'ar'));
    }

    public function test_optional_schedule_dates_use_full_day_boundaries_and_reject_reversed_ranges(): void
    {
        config()->set('app.timezone', 'Asia/Riyadh');
        $user = $this->editor();

        $this->actingAs($user)
            ->post($this->contentRoute('store', 'ticker'), $this->tickerPayload([
                'display_start_at' => '',
                'display_end_at' => '',
            ]))
            ->assertRedirect();

        $empty = $this->section('ticker')->items()->reorder()->latest('id')->firstOrFail();
        $this->assertNull($empty->display_start_at);
        $this->assertNull($empty->display_end_at);

        $this->actingAs($user)
            ->post($this->contentRoute('store', 'ticker'), $this->tickerPayload([
                'display_start_at' => '2026-03-10',
                'display_end_at' => '',
            ]))
            ->assertRedirect();

        $startOnly = $this->section('ticker')->items()->reorder()->latest('id')->firstOrFail();
        $this->assertSame('2026-03-10 00:00:00', $startOnly->display_start_at?->toDateTimeString());
        $this->assertNull($startOnly->display_end_at);

        $this->actingAs($user)
            ->post($this->contentRoute('store', 'ticker'), $this->tickerPayload([
                'display_start_at' => '',
                'display_end_at' => '2026-03-10',
            ]))
            ->assertRedirect();

        $endOnly = $this->section('ticker')->items()->reorder()->latest('id')->firstOrFail();
        $this->assertNull($endOnly->display_start_at);
        $this->assertSame('2026-03-10 23:59:59', $endOnly->display_end_at?->toDateTimeString());

        $this->actingAs($user)
            ->post($this->contentRoute('store', 'ticker'), $this->tickerPayload([
                'display_start_at' => '2026-03-10',
                'display_end_at' => '2026-03-09',
            ]))
            ->assertSessionHasErrors('display_end_at');
    }

    public function test_public_eligibility_applies_schedule_boundaries_in_the_configured_timezone(): void
    {
        config()->set('app.timezone', 'Asia/Riyadh');
        $section = $this->section('ticker');
        $timezone = 'Asia/Riyadh';
        $section->update([
            'status' => ContentStatus::Published->value,
            'published_at' => CarbonImmutable::parse('2026-01-01 00:00:00', $timezone),
            'is_enabled' => true,
        ]);

        foreach ([
            ['always', null, null],
            ['ends-today', null, '2026-03-10 23:59:59'],
            ['starts-tomorrow', '2026-03-11 00:00:00', null],
            ['today-range', '2026-03-10 00:00:00', '2026-03-10 23:59:59'],
        ] as [$key, $start, $end]) {
            $section->items()->create([
                'item_type' => 'inline',
                'item_key' => $key,
                'is_published' => true,
                'display_start_at' => $start === null ? null : CarbonImmutable::parse($start, $timezone),
                'display_end_at' => $end === null ? null : CarbonImmutable::parse($end, $timezone),
                'sort_order' => 1,
            ])->translations()->create([
                'locale' => 'en',
                'title' => $key,
                'url' => '/en/media',
            ]);
        }

        $this->travelTo(CarbonImmutable::parse('2026-03-10 23:30:00', $timezone));
        app(HomepageService::class)->flush();

        $visible = $this->tickerKeys();
        $this->assertContains('always', $visible);
        $this->assertContains('ends-today', $visible);
        $this->assertContains('today-range', $visible);
        $this->assertNotContains('starts-tomorrow', $visible);

        $this->travelTo(CarbonImmutable::parse('2026-03-11 00:30:00', $timezone));
        app(HomepageService::class)->flush();

        $visible = $this->tickerKeys();
        $this->assertContains('always', $visible);
        $this->assertContains('starts-tomorrow', $visible);
        $this->assertNotContains('ends-today', $visible);
        $this->assertNotContains('today-range', $visible);
    }

    public function test_ticker_accepts_inline_announcements_and_published_article_links_only(): void
    {
        $user = $this->editor();
        $published = $this->article('Published announcement', true);
        $draft = $this->article('Draft announcement', false);

        $this->actingAs($user)
            ->post($this->contentRoute('store', 'ticker'), $this->tickerPayload([
                'translations' => [
                    'ar' => ['title' => 'إعلان عربي', 'url' => '/ar/media'],
                    'en' => ['title' => 'English announcement', 'url' => '/en/media'],
                ],
            ]))
            ->assertRedirect();

        $inline = $this->section('ticker')->items()->reorder()->latest('id')->firstOrFail();
        $this->assertStringStartsWith('ticker-', (string) $inline->item_key);
        $this->assertSame('English announcement', $inline->t('title', 'en'));
        $this->assertSame('/en/media', $inline->t('url', 'en'));

        $this->actingAs($user)
            ->post($this->contentRoute('store', 'ticker'), [
                'item_type' => 'article',
                'item_id' => $draft->id,
                'is_published' => true,
                'sort_order' => 2,
                'translations' => ['en' => ['title' => 'Draft link']],
            ])
            ->assertSessionHasErrors('item_id');

        $this->actingAs($user)
            ->post($this->contentRoute('store', 'ticker'), [
                'item_type' => 'article',
                'item_id' => $published->id,
                'is_published' => true,
                'sort_order' => 2,
                'translations' => ['en' => ['title' => 'Published link']],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $articleItem = $this->section('ticker')->items()->reorder()->latest('id')->firstOrFail();
        $this->assertSame('article', $articleItem->item_type);
        $this->assertSame($published->id, (int) $articleItem->item_id);

        $this->actingAs($user)
            ->post($this->contentRoute('store', 'ticker'), array_merge($this->tickerPayload(), [
                'item_type' => 'project',
                'item_id' => 1,
            ]))
            ->assertSessionHasErrors('item_type');
    }

    public function test_ticker_item_can_be_updated_hidden_and_deleted_without_losing_other_records(): void
    {
        $user = $this->editor();

        $this->actingAs($user)
            ->post($this->contentRoute('store', 'ticker'), $this->tickerPayload())
            ->assertRedirect();

        $item = $this->section('ticker')->items()->firstOrFail();

        $this->actingAs($user)
            ->put($this->contentRoute('update', 'ticker', ['sectionItem' => $item->id]), [
                'item_type' => 'inline',
                'is_published' => true,
                'sort_order' => 0,
                'translations' => [
                    'ar' => ['title' => 'إعلان محدث', 'url' => '/ar/media'],
                    'en' => ['title' => 'Updated announcement', 'url' => '/en/media'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('Updated announcement', $item->fresh()->t('title', 'en'));

        $this->actingAs($user)
            ->patch($this->contentRoute('toggle', 'ticker', ['sectionItem' => $item->id]), ['enabled' => false])
            ->assertRedirect();

        $ticker = collect(app(HomepageService::class)->sections('en'))->firstWhere('key', 'ticker');
        $this->assertSame([], $ticker['items']);
        $this->assertSame('Updated announcement', $item->fresh()->t('title', 'en'));

        $this->actingAs($user)
            ->delete($this->contentRoute('destroy', 'ticker', ['sectionItem' => $item->id]))
            ->assertRedirect();

        $this->assertNull($item->fresh());
    }

    public function test_ticker_label_is_persisted_and_published_to_the_public_payload(): void
    {
        $user = $this->editor();

        $this->actingAs($user)
            ->put(route('cms.content.presentation', ['locale' => 'en', 'section' => 'ticker']), [
                'translations' => [
                    'ar' => ['ticker_label' => 'شريط الإعلانات'],
                    'en' => ['ticker_label' => 'Announcements'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('شريط الإعلانات', $this->section('ticker')->t('ticker_label', 'ar'));

        $ticker = collect(app(HomepageService::class)->sections('en'))->firstWhere('key', 'ticker');
        $this->assertSame('Announcements', $ticker['tickerLabel']);

        $this->actingAs($user)
            ->put(route('cms.content.presentation', ['locale' => 'en', 'section' => 'hero']), [
                'translations' => ['en' => ['ticker_label' => 'Invalid']],
            ])
            ->assertSessionHasErrors('section');
    }

    /** @return array<string, mixed> */
    private function tickerPayload(array $overrides = []): array
    {
        return array_merge([
            'item_type' => 'inline',
            'is_published' => true,
            'sort_order' => 0,
            'translations' => [
                'ar' => ['title' => 'إعلان', 'url' => '/ar/media'],
                'en' => ['title' => 'Announcement', 'url' => '/en/media'],
            ],
        ], $overrides);
    }

    /** @return list<string> */
    private function tickerKeys(): array
    {
        $ticker = collect(app(HomepageService::class)->sections('en'))->firstWhere('key', 'ticker');

        return collect($ticker['items'])->pluck('key')->all();
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, ['homepage.view', 'homepage.update']);

        return $user;
    }

    /** @param list<string> $permissions */
    private function grantPermissions(User $user, array $permissions): void
    {
        $role = Role::query()->create([
            'key' => 'hero-ticker-'.Str::lower(Str::random(10)),
            'name' => 'Hero and ticker tester',
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

    /** @param array<string, mixed> $parameters */
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

    private function image(string $name): Media
    {
        return Media::query()->create([
            'uuid' => (string) Str::ulid(),
            'disk' => 'public',
            'path' => 'media/cms/'.$name,
            'type' => MediaType::Image->value,
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => 2048,
            'width' => 800,
            'height' => 600,
            'checksum' => hash('sha256', $name),
            'original_name' => $name,
            'is_restricted' => false,
        ]);
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
}
