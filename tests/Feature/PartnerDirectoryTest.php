<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Models\DirectoryPartner;
use App\Models\DirectoryPartnerCategory;
use App\Models\DirectoryPartnerCategoryTranslation;
use App\Models\DirectoryPartnerTranslation;
use App\Models\Media;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PartnerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALE = 'en';

    private const BOOTSTRAP_EMAIL = 'partner-directory-editor@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cms.bootstrap.email', self::BOOTSTRAP_EMAIL);
        config()->set('cms.local_auth_enabled', true);
        $this->withoutVite();
    }

    public function test_cms_directory_routes_require_the_distinct_directory_permissions(): void
    {
        $outsider = $this->userWith([]);
        $viewer = $this->userWith(['directory_partners.view']);

        $this->actingAs($outsider)->get($this->cmsRoute('index'))->assertForbidden();
        $this->actingAs($viewer)->get($this->cmsRoute('index'))->assertOk();
        $this->actingAs($viewer)->get($this->cmsRoute('partners.create'))->assertForbidden();
        $this->actingAs($viewer)->get($this->cmsRoute('categories.index'))->assertOk();
        $this->actingAs($viewer)->get($this->cmsRoute('categories.create'))->assertForbidden();

        $category = $this->category('restricted', true, 0, 'Restricted', 'مقيد');
        $partner = $this->partner($category, 'Existing', 'موجود', 'existing', 0);

        $this->actingAs($viewer)->post($this->cmsRoute('partners.store'), $this->partnerPayload())->assertForbidden();
        $this->actingAs($viewer)->put($this->cmsRoute('partners.update', $partner->id), $this->partnerPayload())->assertForbidden();
        $this->actingAs($viewer)->delete($this->cmsRoute('partners.destroy', $partner->id))->assertForbidden();
        $this->actingAs($viewer)->post($this->cmsRoute('categories.store'), $this->categoryPayload())->assertForbidden();
        $this->actingAs($viewer)->delete($this->cmsRoute('categories.destroy', $category->id))->assertForbidden();

        $this->assertSame(1, DirectoryPartner::query()->count());
        $this->assertSame(1, DirectoryPartnerCategory::query()->count());
    }

    public function test_cms_partner_crud_status_order_and_soft_delete_are_audited(): void
    {
        $editor = $this->bootstrapUser();
        $category = $this->category('ngos', true, 1, 'NGOs', 'المنظمات الأهلية');
        $logo = $this->media('cms-logo.png');

        $this->actingAs($editor)->get($this->cmsRoute('partners.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cms/PartnerDirectory/Index')
                ->where('view', 'partners')
                ->where('editing', 'new')
                ->where('can.create', true)
                ->has('categories'));

        $payload = $this->partnerPayload([
            'directory_partner_category_id' => $category->id,
            'logo_media_id' => $logo->id,
            'website_url' => 'https://example.org/partner',
            'partnership_year' => 2026,
            'display_order' => 7,
            'translations' => [
                'ar' => ['name' => 'شريك تجريبي', 'slug' => '', 'description' => 'وصف عربي'],
                'en' => ['name' => 'Test Partner', 'slug' => '', 'description' => 'English description'],
            ],
        ]);

        $this->actingAs($editor)->post($this->cmsRoute('partners.store'), $payload)->assertRedirect();

        $partner = DirectoryPartner::query()->firstOrFail();
        $this->assertSame($category->id, $partner->directory_partner_category_id);
        $this->assertSame($logo->id, $partner->logo_media_id);
        $this->assertSame('https://example.org/partner', $partner->website_url);
        $this->assertSame(2026, $partner->partnership_year);
        $this->assertSame(7, $partner->display_order);
        $this->assertSame('Test Partner', $partner->t('name', 'en'));
        $this->assertStringEndsWith('-'.$partner->id.'-en', (string) $partner->t('slug', 'en'));
        $this->assertStringEndsWith('-'.$partner->id.'-ar', (string) $partner->t('slug', 'ar'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'directory_partners.created',
            'subject_type' => 'directory_partners',
            'subject_id' => (string) $partner->id,
        ]);

        $update = $payload;
        $update['status'] = 'published';
        $update['published_at'] = now()->subDay()->toDateString();
        $update['translations']['en']['name'] = 'Updated Partner';
        $update['translations']['en']['slug'] = 'updated-partner';
        $this->actingAs($editor)->put($this->cmsRoute('partners.update', $partner->id), $update)->assertRedirect();

        $partner->refresh();
        $this->assertSame('published', $partner->status);
        $this->assertSame('Updated Partner', $partner->t('name', 'en'));
        $this->assertSame('updated-partner', $partner->t('slug', 'en'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'directory_partners.updated',
            'subject_id' => (string) $partner->id,
        ]);

        $this->actingAs($editor)->post($this->cmsRoute('partners.status', $partner->id), ['status' => 'draft'])->assertRedirect();
        $this->assertSame('draft', $partner->fresh()->status);
        $this->assertNull($partner->fresh()->published_at);

        $this->actingAs($editor)->post($this->cmsRoute('partners.status', $partner->id), ['status' => 'published'])->assertRedirect();
        $this->assertSame('published', $partner->fresh()->status);
        $this->assertNotNull($partner->fresh()->published_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'directory_partners.status_updated',
            'subject_id' => (string) $partner->id,
        ]);

        $this->actingAs($editor)->post($this->cmsRoute('partners.reorder'), [
            'id' => $partner->id,
            'display_order' => 2,
        ])->assertRedirect();
        $this->assertSame(2, $partner->fresh()->display_order);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'directory_partners.reordered',
            'subject_id' => (string) $partner->id,
        ]);

        $this->actingAs($editor)->delete($this->cmsRoute('partners.destroy', $partner->id))->assertRedirect();
        $this->assertSoftDeleted('directory_partners', ['id' => $partner->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'directory_partners.deleted',
            'subject_id' => (string) $partner->id,
        ]);
    }

    public function test_cms_partner_validation_rejects_unsafe_media_duplicate_slugs_and_invalid_fields(): void
    {
        $editor = $this->bootstrapUser();
        $restricted = $this->media('private-logo.png', true);
        $existing = $this->partner(null, 'Existing', 'موجود', 'taken-slug', 0);
        $existing->translations()->where('locale', 'en')->update(['slug' => 'taken-slug']);

        $this->actingAs($editor)->post($this->cmsRoute('partners.store'), $this->partnerPayload([
            'logo_media_id' => $restricted->id,
            'website_url' => 'not-a-url',
            'partnership_year' => 1800,
            'translations' => [
                'en' => ['name' => '', 'slug' => 'bad slug'],
            ],
        ]))->assertSessionHasErrors([
            'logo_media_id',
            'website_url',
            'partnership_year',
            'translations.en.name',
            'translations.en.slug',
        ]);

        $this->actingAs($editor)->post($this->cmsRoute('partners.store'), $this->partnerPayload([
            'translations' => [
                'ar' => ['name' => 'شريك آخر', 'slug' => 'other-ar'],
                'en' => ['name' => 'Another partner', 'slug' => 'taken-slug'],
            ],
        ]))->assertSessionHasErrors(['translations.en.slug']);

        $this->assertSame(1, DirectoryPartner::query()->count());
    }

    public function test_cms_partner_index_supports_search_status_category_filters_and_pagination(): void
    {
        $editor = $this->bootstrapUser();
        $first = $this->category('first-category', true, 1, 'First', 'الأولى');
        $second = $this->category('second-category', true, 2, 'Second', 'الثانية');

        for ($index = 1; $index <= 21; $index++) {
            $partner = $this->partner($index <= 11 ? $first : $second, 'Partner '.$index, 'شريك '.$index, 'partner-'.$index, $index);
            if ($index === 21) {
                $partner->forceFill(['status' => ContentStatus::Draft])->save();
            }
        }

        $this->actingAs($editor)->get($this->cmsRoute('index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('view', 'partners')
                ->where('pagination.total', 21)
                ->where('pagination.perPage', 20)
                ->where('counts.all', 21)
                ->where('counts.published', 20)
                ->has('items', 20));

        $this->actingAs($editor)->get($this->cmsRoute('index', ['search' => 'Partner 21', 'status' => 'draft', 'category' => 'second-category']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'Partner 21')
                ->where('filters.status', 'draft')
                ->where('filters.category', 'second-category')
                ->where('pagination.total', 1)
                ->where('items.0.name', 'Partner 21'));
    }

    public function test_cms_partner_index_supports_sorting_per_page_and_query_preserving_redirects(): void
    {
        $editor = $this->bootstrapUser();
        $category = $this->category('sorted-category', true, 1, 'Sorted', 'مرتب');

        $this->partner($category, 'Early year', 'سنة مبكرة', 'early-year', 5, 2023);
        $late = $this->partner($category, 'Late year', 'سنة متأخرة', 'late-year', 9, 2026);
        $this->partner($category, 'Middle year', 'سنة متوسطة', 'middle-year', 1, 2025);

        $sortQuery = ['sort' => 'year', 'direction' => 'desc', 'per_page' => 10];
        $this->actingAs($editor)->get($this->cmsRoute('index', $sortQuery))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.sort', 'year')
                ->where('filters.direction', 'desc')
                ->where('filters.per_page', 10)
                ->where('pagination.perPage', 10)
                ->where('items.0.name', 'Late year')
                ->where('items.1.name', 'Middle year')
                ->where('items.2.name', 'Early year'));

        $this->actingAs($editor)->get($this->cmsRoute('index', ['sort' => 'display_order', 'direction' => 'desc']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.0.name', 'Late year')
                ->where('items.2.name', 'Middle year'));

        $partnerQuery = [
            'search' => 'year',
            'status' => 'published',
            'category' => 'sorted-category',
            'sort' => 'year',
            'direction' => 'desc',
            'per_page' => 10,
            'page' => 1,
        ];
        $this->actingAs($editor)->put(
            $this->cmsRoute('partners.update', $late->id, $partnerQuery),
            $this->partnerPayload([
                'directory_partner_category_id' => $category->id,
                'display_order' => 9,
            ]),
        )->assertRedirect($this->cmsRoute('index', $partnerQuery));

        $categoryQuery = [
            'search' => 'sorted',
            'status' => 'active',
            'sort' => 'slug',
            'direction' => 'desc',
            'per_page' => 10,
            'page' => 1,
        ];
        $this->actingAs($editor)->put(
            $this->cmsRoute('categories.update', $category->id, $categoryQuery),
            $this->categoryPayload(['slug' => 'sorted-category']),
        )->assertRedirect($this->cmsRoute('categories.index', $categoryQuery));
    }

    public function test_cms_categories_can_be_created_updated_reordered_deactivated_and_safely_deleted(): void
    {
        $editor = $this->bootstrapUser();

        $this->actingAs($editor)->get($this->cmsRoute('categories.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cms/PartnerDirectory/Index')
                ->where('view', 'categories')
                ->where('editing', 'new'));

        $payload = $this->categoryPayload([
            'slug' => 'local-ngos',
            'is_active' => true,
            'display_order' => 4,
            'translations' => [
                'ar' => ['name' => 'المنظمات المحلية', 'description' => 'وصف عربي'],
                'en' => ['name' => 'Local NGOs', 'description' => 'English description'],
            ],
        ]);

        $this->actingAs($editor)->post($this->cmsRoute('categories.store'), $payload)->assertRedirect();

        $category = DirectoryPartnerCategory::query()->firstOrFail();
        $this->assertSame('local-ngos', $category->slug);
        $this->assertSame(4, $category->display_order);
        $this->assertTrue($category->is_active);
        $this->assertSame('Local NGOs', $category->t('name', 'en'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'directory_partners.category_created',
            'subject_type' => 'directory_partner_categories',
            'subject_id' => (string) $category->id,
        ]);

        $update = $payload;
        $update['translations']['en']['name'] = 'Updated NGOs';
        $this->actingAs($editor)->put($this->cmsRoute('categories.update', $category->id), $update)->assertRedirect();
        $this->assertSame('Updated NGOs', $category->fresh()->t('name', 'en'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'directory_partners.category_updated',
            'subject_id' => (string) $category->id,
        ]);

        $this->actingAs($editor)->post($this->cmsRoute('categories.reorder'), [
            'id' => $category->id,
            'display_order' => 9,
        ])->assertRedirect();
        $this->assertSame(9, $category->fresh()->display_order);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'directory_partners.category_reordered',
            'subject_id' => (string) $category->id,
        ]);

        $partner = $this->partner($category, 'Protected Partner', 'شريك محمي', 'protected-partner', 1);
        $this->actingAs($editor)->delete($this->cmsRoute('categories.destroy', $category->id))->assertRedirect();
        $this->assertNotSoftDeleted('directory_partner_categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'directory_partners.category_deleted',
            'subject_id' => (string) $category->id,
        ]);

        $this->actingAs($editor)->post($this->cmsRoute('categories.status', $category->id), ['is_active' => false])->assertRedirect();
        $this->assertFalse($category->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'directory_partners.category_status_updated',
            'subject_id' => (string) $category->id,
        ]);

        $partner->delete();
        $this->actingAs($editor)->delete($this->cmsRoute('categories.destroy', $category->id))->assertRedirect();
        $this->assertSoftDeleted('directory_partner_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'directory_partners.category_deleted',
            'subject_id' => (string) $category->id,
        ]);
    }

    public function test_cms_category_validation_rejects_duplicate_slugs_and_incomplete_translations(): void
    {
        $editor = $this->bootstrapUser();
        $existing = $this->category('unique-category', true, 0, 'Unique', 'فريدة');

        $this->actingAs($editor)->post($this->cmsRoute('categories.store'), $this->categoryPayload([
            'slug' => $existing->slug,
        ]))->assertSessionHasErrors(['slug']);

        $this->actingAs($editor)->post($this->cmsRoute('categories.store'), $this->categoryPayload([
            'slug' => 'missing-names',
            'translations' => [
                'ar' => ['name' => ''],
                'en' => ['name' => '   '],
            ],
        ]))->assertSessionHasErrors([
            'translations.ar.name',
            'translations.en.name',
        ]);

        $this->assertSame(1, DirectoryPartnerCategory::query()->count());
    }

    public function test_public_directory_only_exposes_published_partners_from_active_categories(): void
    {
        $active = $this->category('active-category', true, 1, 'Active category', 'فئة نشطة');
        $inactive = $this->category('inactive-category', false, 2, 'Inactive category', 'فئة غير نشطة');
        $publicLogo = $this->media('public-logo.png');
        $privateLogo = $this->media('private-logo.png', true);

        $first = $this->partner($active, 'First partner', 'الشريك الأول', 'first-public', 5, 2025, $publicLogo->id);
        $first->update(['website_url' => 'javascript:alert(1)']);
        $this->partner($active, 'Second partner', 'الشريك الثاني', 'second-public', 2);
        $this->partner(null, 'Other partner', 'شريك آخر', 'other-public', 1);
        $privateLogoPartner = $this->partner($active, 'Restricted logo partner', 'شريك بشعار خاص', 'private-logo', 8, null, $privateLogo->id);
        $privateLogoPartner->update(['logo_media_id' => $privateLogo->id]);

        foreach (['Draft partner', 'Archived partner', 'Future partner', 'Hidden category partner', 'Deleted partner'] as $name) {
            $hidden = $this->partner($name === 'Hidden category partner' ? $inactive : $active, $name, 'مخفي', Str::slug($name), 0);
            $status = match ($name) {
                'Draft partner' => ContentStatus::Draft,
                'Archived partner' => ContentStatus::Archived,
                default => ContentStatus::Published,
            };
            $hidden->forceFill([
                'status' => $status,
                'published_at' => $name === 'Future partner' ? now()->addDay() : now()->subDay(),
            ])->save();
            if ($name === 'Deleted partner') {
                $hidden->delete();
            }
        }

        $props = $this->inertiaProps($this->get('/en/partners'));
        $directory = (array) ($props['directory'] ?? []);
        $names = $this->publicPartnerNames($directory);

        $this->assertSame(4, $directory['total'] ?? null);
        $this->assertTrue($directory['hasPartners'] ?? false);
        $this->assertSame('all', $directory['activeCategory'] ?? null);
        $this->assertContains('First partner', $names);
        $this->assertContains('Other partner', $names);
        foreach (['Draft partner', 'Archived partner', 'Future partner', 'Hidden category partner', 'Deleted partner'] as $name) {
            $this->assertNotContains($name, $names);
        }

        $partners = $this->flattenPublicPartners($directory);
        $firstPublic = collect($partners)->firstWhere('name', 'First partner');
        $this->assertIsArray($firstPublic);
        $this->assertNull($firstPublic['website'], 'Unsafe website schemes must not be exposed.');
        $this->assertIsArray($firstPublic['logo']);
        $this->assertStringContainsString('public-logo.png', (string) $firstPublic['logo']['url']);

        $restricted = collect($partners)->firstWhere('name', 'Restricted logo partner');
        $this->assertIsArray($restricted);
        $this->assertNull($restricted['logo'], 'Restricted media must not be exposed publicly.');

        $this->assertStringNotContainsString('status', json_encode($directory, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('published_at', json_encode($directory, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('logo_media_id', json_encode($directory, JSON_THROW_ON_ERROR));
    }

    public function test_public_directory_groups_partners_by_display_order_and_honours_category_filters(): void
    {
        $active = $this->category('active-category', true, 1, 'Active category', 'فئة نشطة');
        $this->partner($active, 'Later partner', 'شريك لاحق', 'later', 9);
        $this->partner($active, 'Earlier partner', 'شريك سابق', 'earlier', 1);
        $this->partner(null, 'Other partner', 'شريك آخر', 'other', 2);

        $allProps = $this->inertiaProps($this->get('/ar/partners'));
        $all = (array) ($allProps['directory'] ?? []);
        $activeGroup = collect($all['groups'] ?? [])->firstWhere('slug', 'active-category');
        $this->assertIsArray($activeGroup);
        $this->assertSame(['شريك سابق', 'شريك لاحق'], array_column($activeGroup['partners'], 'name'));

        $filteredProps = $this->inertiaProps($this->get('/ar/partners?category=active-category'));
        $filtered = (array) ($filteredProps['directory'] ?? []);
        $this->assertSame('active-category', $filtered['activeCategory'] ?? null);
        $this->assertSame(['active-category'], array_column($filtered['groups'], 'slug'));

        $invalidProps = $this->inertiaProps($this->get('/ar/partners?category=does-not-exist'));
        $this->assertSame('all', ((array) ($invalidProps['directory'] ?? []))['activeCategory'] ?? null);

        $props = $this->inertiaProps($this->get('/en/partners'));
        $this->assertSame('Institutional Partners Directory', $props['meta']['title'] ?? null);
    }

    public function test_public_directory_tracks_the_cms_publication_workflow(): void
    {
        $editor = $this->bootstrapUser();
        $this->actingAs($editor)->post($this->cmsRoute('partners.store'), $this->partnerPayload([
            'translations' => [
                'ar' => ['name' => 'شريك تجريبي', 'slug' => '', 'description' => null],
                'en' => ['name' => 'Workflow partner', 'slug' => '', 'description' => null],
            ],
        ]))->assertRedirect();

        $partner = DirectoryPartner::query()->firstOrFail();
        $this->assertNotContains('Workflow partner', $this->publicPartnerNames(
            (array) ($this->inertiaProps($this->get('/en/partners'))['directory'] ?? []),
        ));

        $this->actingAs($editor)->post($this->cmsRoute('partners.status', $partner->id), ['status' => 'published'])->assertRedirect();
        $this->assertContains('Workflow partner', $this->publicPartnerNames(
            (array) ($this->inertiaProps($this->get('/en/partners'))['directory'] ?? []),
        ));

        $this->actingAs($editor)->post($this->cmsRoute('partners.status', $partner->id), ['status' => 'draft'])->assertRedirect();
        $this->assertNotContains('Workflow partner', $this->publicPartnerNames(
            (array) ($this->inertiaProps($this->get('/en/partners'))['directory'] ?? []),
        ));

        $this->actingAs($editor)->post($this->cmsRoute('partners.status', $partner->id), ['status' => 'published'])->assertRedirect();
        $this->actingAs($editor)->post($this->cmsRoute('partners.status', $partner->id), ['status' => 'archived'])->assertRedirect();
        $this->assertNotContains('Workflow partner', $this->publicPartnerNames(
            (array) ($this->inertiaProps($this->get('/en/partners'))['directory'] ?? []),
        ));

        $this->actingAs($editor)->delete($this->cmsRoute('partners.destroy', $partner->id))->assertRedirect();
        $this->assertSoftDeleted('directory_partners', ['id' => $partner->id]);
    }

    public function test_publication_cannot_be_granted_through_a_create_or_update_payload(): void
    {
        $editor = $this->bootstrapUser();
        $limited = $this->userWith(['directory_partners.create', 'directory_partners.update']);
        $published = $this->partner(null, 'Published partner', 'شريك منشور', 'published', 1);

        $this->actingAs($limited)->post($this->cmsRoute('partners.store'), $this->partnerPayload([
            'status' => 'published',
            'translations' => [
                'ar' => ['name' => 'محاولة نشر', 'slug' => '', 'description' => null],
                'en' => ['name' => 'Publish attempt', 'slug' => '', 'description' => null],
            ],
        ]))->assertForbidden();
        $this->assertSame(1, DirectoryPartner::query()->count());

        $draft = $this->partner(null, 'Draft partner', 'شريك مسودة', 'draft-bypass', 2);
        $draft->forceFill(['status' => ContentStatus::Draft, 'published_at' => null])->save();
        $this->actingAs($limited)->put($this->cmsRoute('partners.update', $draft->id), $this->partnerPayload([
            'status' => 'published',
        ]))->assertForbidden();
        $this->assertSame(ContentStatus::Draft->value, ContentStatus::valueOf($draft->fresh()->status));

        $this->actingAs($limited)->put($this->cmsRoute('partners.update', $published->id), $this->partnerPayload([
            'status' => 'draft',
        ]))->assertForbidden();
        $this->assertSame(ContentStatus::Published->value, ContentStatus::valueOf($published->fresh()->status));
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function partnerPayload(array $overrides = []): array
    {
        return array_merge([
            'directory_partner_category_id' => null,
            'logo_media_id' => null,
            'website_url' => null,
            'partnership_year' => null,
            'status' => 'draft',
            'published_at' => null,
            'display_order' => 0,
            'translations' => [
                'ar' => ['name' => 'شريك', 'slug' => '', 'description' => null],
                'en' => ['name' => 'Partner', 'slug' => '', 'description' => null],
            ],
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function categoryPayload(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'generated-category',
            'is_active' => true,
            'display_order' => 0,
            'translations' => [
                'ar' => ['name' => 'فئة', 'description' => null],
                'en' => ['name' => 'Category', 'description' => null],
            ],
        ], $overrides);
    }

    private function category(
        string $slug,
        bool $active,
        int $order,
        string $englishName,
        string $arabicName,
    ): DirectoryPartnerCategory {
        $category = DirectoryPartnerCategory::factory()->create([
            'slug' => $slug,
            'is_active' => $active,
            'display_order' => $order,
        ]);

        DirectoryPartnerCategoryTranslation::query()->updateOrCreate(
            ['directory_partner_category_id' => $category->id, 'locale' => 'en'],
            ['name' => $englishName, 'description' => 'English category description'],
        );
        DirectoryPartnerCategoryTranslation::query()->updateOrCreate(
            ['directory_partner_category_id' => $category->id, 'locale' => 'ar'],
            ['name' => $arabicName, 'description' => 'وصف الفئة'],
        );

        return $category;
    }

    private function partner(
        ?DirectoryPartnerCategory $category,
        string $englishName,
        string $arabicName,
        string $slugPrefix,
        int $order,
        ?int $year = null,
        ?int $logoMediaId = null,
    ): DirectoryPartner {
        $partner = DirectoryPartner::factory()->create([
            'directory_partner_category_id' => $category?->id,
            'logo_media_id' => $logoMediaId,
            'website_url' => 'https://example.org/'.$slugPrefix,
            'partnership_year' => $year,
            'status' => ContentStatus::Published,
            'published_at' => now()->subDay(),
            'display_order' => $order,
        ]);

        DirectoryPartnerTranslation::query()->updateOrCreate(
            ['directory_partner_id' => $partner->id, 'locale' => 'en'],
            [
                'name' => $englishName,
                'slug' => $slugPrefix.'-en',
                'description' => 'English partner description',
            ],
        );
        DirectoryPartnerTranslation::query()->updateOrCreate(
            ['directory_partner_id' => $partner->id, 'locale' => 'ar'],
            [
                'name' => $arabicName,
                'slug' => $slugPrefix.'-ar',
                'description' => 'وصف الشريك',
            ],
        );

        return $partner;
    }

    /** @return array<string, mixed> */
    private function inertiaProps(TestResponse $response): array
    {
        $html = (string) $response->getContent();
        $this->assertSame(
            1,
            preg_match('/<script data-page="app" type="application\/json">(.*?)<\/script>/s', $html, $matches),
            'Inertia page payload was not found in the HTML response.',
        );

        $page = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($page);

        return (array) ($page['props'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $directory
     * @return list<array<string, mixed>>
     */
    private function flattenPublicPartners(array $directory): array
    {
        $partners = [];
        foreach (($directory['groups'] ?? []) as $group) {
            foreach (($group['partners'] ?? []) as $partner) {
                $partners[] = (array) $partner;
            }
        }

        return $partners;
    }

    /**
     * @param  array<string, mixed>  $directory
     * @return list<string>
     */
    private function publicPartnerNames(array $directory): array
    {
        return array_values(array_filter(array_map(
            static fn (array $partner): string => (string) ($partner['name'] ?? ''),
            $this->flattenPublicPartners($directory),
        )));
    }

    private function media(string $name, bool $restricted = false): Media
    {
        return Media::query()->create([
            'uuid' => (string) Str::ulid(),
            'disk' => $restricted ? 'private' : 'public',
            'path' => 'partner-directory/'.$name,
            'type' => MediaType::Image->value,
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => 1024,
            'width' => 240,
            'height' => 80,
            'checksum' => str_repeat('a', 64),
            'original_name' => $name,
            'is_restricted' => $restricted,
        ]);
    }

    private function cmsRoute(string $name, int|string|array|null $parameterOrQuery = null, array $query = []): string
    {
        $parameters = ['locale' => self::LOCALE];

        if (is_array($parameterOrQuery)) {
            $query = $parameterOrQuery + $query;
        } elseif ($parameterOrQuery !== null) {
            $parameters[match (true) {
                str_contains($name, 'categories') => 'category',
                default => 'partner',
            }] = $parameterOrQuery;
        }

        return route('cms.partner-directory.'.$name, $parameters + $query);
    }

    private function bootstrapUser(): User
    {
        return User::factory()->create([
            'name' => 'Partner Directory Editor',
            'email' => self::BOOTSTRAP_EMAIL,
            'password' => Hash::make('password'),
        ]);
    }

    /** @param list<string> $keys */
    private function userWith(array $keys): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'key' => 'partner-directory-'.($keys === [] ? 'none' : implode('-', $keys)).'-'.$user->id,
            'name' => 'Partner directory limited user',
        ]);

        foreach ($keys as $key) {
            $definition = (array) config('permissions.'.$key, []);
            $permission = Permission::query()->firstOrCreate(['key' => $key], [
                'name' => $definition['label'] ?? $key,
                'module' => $definition['module'] ?? 'website',
                'is_protected' => false,
            ]);
            $role->permissions()->attach($permission);
        }

        app(AccessManagementService::class)->syncRoles($user, [$role->getKey()]);

        return $user;
    }
}
