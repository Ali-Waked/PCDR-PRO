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
