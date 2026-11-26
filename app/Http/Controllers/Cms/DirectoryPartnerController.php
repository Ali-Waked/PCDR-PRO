<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDirectoryPartnerRequest;
use App\Http\Requests\UpdateDirectoryPartnerRequest;
use App\Models\DirectoryPartner;
use App\Models\DirectoryPartnerCategory;
use App\Models\Media;
use App\Services\AuditService;
use App\Services\CmsAccess;
use App\Services\TranslationSaver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class DirectoryPartnerController extends Controller
{
    private const SORT_COLUMNS = [
        'display_order' => 'display_order',
        'status' => 'status',
        'year' => 'partnership_year',
        'updated_at' => 'updated_at',
    ];

    private const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    public function __construct(
        private AuditService $audit,
        private TranslationSaver $translations,
    ) {}

    public function index(string $locale, Request $request): Response
    {
        $this->authorizeCms('directory_partners.view');

        return Inertia::render('Cms/PartnerDirectory/Index', $this->listProps($locale, $request));
    }

    public function create(string $locale, Request $request): Response
    {
        $this->authorizeCms('directory_partners.create');

        return Inertia::render('Cms/PartnerDirectory/Index', [
            ...$this->listProps($locale, $request),
            'form' => $this->blankForm(),
            'editing' => 'new',
        ]);
    }

    public function edit(string $locale, Request $request, DirectoryPartner $partner): Response
    {
        $this->authorizeCms('directory_partners.view');
        $partner->load('translations');

        return Inertia::render('Cms/PartnerDirectory/Index', [
            ...$this->listProps($locale, $request),
            'form' => $this->formData($partner, $locale),
            'editing' => $partner->id,
        ]);
    }

    public function store(string $locale, StoreDirectoryPartnerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $partner = new DirectoryPartner;
        $partner->fill($this->attributes($data));
        $partner->save();

        $this->translations->sync(
            $partner,
            $this->translationSlugs($data['translations'], $partner->id),
            ['slug', 'name', 'description'],
            'directory_partner_id',
            ['description'],
        );
        $this->audit->record('directory_partners.created', 'directory_partners', (string) $partner->id, after: $this->auditable($partner));

        return $this->listRedirect($locale, $request)
            ->with('flash', ['success' => __('website.cms.saved', [], $locale)]);
    }

    public function update(string $locale, UpdateDirectoryPartnerRequest $request, DirectoryPartner $partner): RedirectResponse
    {
        $before = $this->auditable($partner);
        $data = $request->validated();
        $partner->fill($this->attributes($data));
        $partner->save();

        $this->translations->sync(
            $partner,
            $this->translationSlugs($data['translations'], $partner->id),
            ['slug', 'name', 'description'],
            'directory_partner_id',
            ['description'],
        );
        $this->audit->record('directory_partners.updated', 'directory_partners', (string) $partner->id, $before, $this->auditable($partner));

        return $this->listRedirect($locale, $request)
            ->with('flash', ['success' => __('website.cms.saved', [], $locale)]);
    }

    public function destroy(string $locale, Request $request, DirectoryPartner $partner): RedirectResponse
    {
        $this->authorizeCms('directory_partners.delete');
        $before = $this->auditable($partner);
        $partner->delete();
        $this->audit->record('directory_partners.deleted', 'directory_partners', (string) $partner->id, $before);

        return $this->listRedirect($locale, $request)
            ->with('flash', ['success' => __('website.cms.archived', [], $locale)]);
    }

    public function status(string $locale, Request $request, DirectoryPartner $partner): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:draft,published,archived'],
        ]);
        $target = (string) $data['status'];
        $permission = match ($target) {
            'published' => 'directory_partners.publish',
            default => $partner->status === 'published' ? 'directory_partners.unpublish' : 'directory_partners.update',
        };
        $this->authorizeCms($permission);

        $before = $this->auditable($partner);
        $partner->status = $target;
        $partner->published_at = $target === 'published'
            ? ($partner->published_at?->isPast() ? $partner->published_at : now())
            : null;
        $partner->save();
        $this->audit->record('directory_partners.status_updated', 'directory_partners', (string) $partner->id, $before, $this->auditable($partner));

        return back()->with('flash', ['success' => __('website.cms.status_updated', [], $locale)]);
    }

    public function reorder(string $locale, Request $request): RedirectResponse
    {
        $this->authorizeCms('directory_partners.update');
        $data = $request->validate([
            'id' => ['required', 'integer', 'exists:directory_partners,id'],
            'display_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $partner = DirectoryPartner::query()->findOrFail((int) $data['id']);
        $before = ['display_order' => $partner->display_order];
        $partner->update(['display_order' => (int) $data['display_order']]);
        $this->audit->record('directory_partners.reordered', 'directory_partners', (string) $partner->id, $before, ['display_order' => $partner->display_order]);

        return back()->with('flash', ['success' => __('website.cms.saved', [], $locale)]);
    }

    private function authorizeCms(string $permission): void
    {
        abort_unless(auth()->check() && app(CmsAccess::class)->allows(auth()->user(), $permission), 403);
    }

    /** @return array<string, bool> */
    private function capabilities(): array
    {
        $user = auth()->user();
        $access = app(CmsAccess::class);

        return [
            'create' => $user !== null && $access->allows($user, 'directory_partners.create'),
            'update' => $user !== null && $access->allows($user, 'directory_partners.update'),
            'delete' => $user !== null && $access->allows($user, 'directory_partners.delete'),
            'publish' => $user !== null && $access->allows($user, 'directory_partners.publish'),
            'unpublish' => $user !== null && $access->allows($user, 'directory_partners.unpublish'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listProps(string $locale, Request $request): array
    {
        $search = mb_strcut(trim($request->string('search')->toString()), 0, 120);
        $status = $request->string('status')->toString();
        $status = in_array($status, ['all', 'draft', 'published', 'archived'], true) ? $status : 'all';
        $category = mb_strcut($request->string('category')->toString(), 0, 120);
        $sort = $request->string('sort')->toString();
        $sort = array_key_exists($sort, self::SORT_COLUMNS) ? $sort : 'display_order';
        $direction = strtolower($request->string('direction')->toString());
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';
        $perPage = $request->integer('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 20;

        $categoryId = DirectoryPartnerCategory::query()->where('slug', $category)->value('id');

        $query = DirectoryPartner::query()->with(['translations', 'category.translations', 'logo']);
        if ($search !== '') {
            $query->whereHas('translations', fn ($translationQuery) => $translationQuery
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('slug', 'like', '%'.$search.'%'));
        }
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($categoryId !== null) {
            $query->where('directory_partner_category_id', $categoryId);
        }

        $sortColumn = self::SORT_COLUMNS[$sort];
        $query->orderBy($sortColumn, $direction);
        if ($sort === 'display_order') {
            $query->orderBy('id', $direction);
        }

        $partners = $query->paginate($perPage)->withQueryString();

        return [
            'view' => 'partners',
            'items' => $partners->through(fn (DirectoryPartner $partner): array => $this->item($partner, $locale))->items(),
            'pagination' => $this->pagination($partners),
            'categories' => $this->categoryOptions($locale),
            'media' => $this->mediaOptions($locale),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'category' => $category,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'counts' => [
                'all' => DirectoryPartner::query()->count(),
                'published' => DirectoryPartner::query()->where('status', 'published')->count(),
                'draft' => DirectoryPartner::query()->where('status', 'draft')->count(),
                'archived' => DirectoryPartner::query()->where('status', 'archived')->count(),
            ],
            'form' => $this->blankForm(),
            'editing' => null,
            'can' => $this->capabilities(),
        ];
    }

    /**
     * @return array<string, scalar>
     */
    private function listQuery(Request $request): array
    {
        $query = [];
        foreach (['search', 'status', 'category', 'sort', 'direction', 'per_page', 'page'] as $key) {
            $value = $request->query($key);
            if (is_scalar($value) && $value !== '') {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    private function listRedirect(string $locale, Request $request): RedirectResponse
    {
        return to_route('cms.partner-directory.index', ['locale' => $locale] + $this->listQuery($request));
    }

    /** @return array<string, mixed> */
    private function blankForm(): array
    {
        return [
            'directory_partner_category_id' => '',
            'logo_media_id' => '',
            'website_url' => '',
            'partnership_year' => '',
            'status' => 'draft',
            'published_at' => '',
            'display_order' => 0,
            'translations' => [
                'ar' => ['slug' => '', 'name' => '', 'description' => ''],
                'en' => ['slug' => '', 'name' => '', 'description' => ''],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function formData(DirectoryPartner $partner, string $locale): array
    {
        return [
            'directory_partner_category_id' => $partner->directory_partner_category_id ?? '',
            'logo_media_id' => $partner->logo_media_id ?? '',
            'website_url' => $partner->website_url ?? '',
            'partnership_year' => $partner->partnership_year ?? '',
            'status' => $partner->status,
            'published_at' => $partner->published_at?->toDateString() ?? '',
            'display_order' => $partner->display_order,
            'translations' => [
                'ar' => [
                    'slug' => $partner->t('slug', 'ar') ?? '',
                    'name' => $partner->t('name', 'ar') ?? '',
                    'description' => $partner->t('description', 'ar') ?? '',
                ],
                'en' => [
                    'slug' => $partner->t('slug', 'en') ?? '',
                    'name' => $partner->t('name', 'en') ?? '',
                    'description' => $partner->t('description', 'en') ?? '',
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function attributes(array $data): array
    {
        return [
            'directory_partner_category_id' => $this->nullableValue($data['directory_partner_category_id'] ?? null),
            'logo_media_id' => $this->nullableValue($data['logo_media_id'] ?? null),
            'website_url' => $this->nullableValue($data['website_url'] ?? null),
            'partnership_year' => $this->nullableValue($data['partnership_year'] ?? null),
            'status' => $data['status'],
            'published_at' => $data['status'] === 'published'
                ? ($data['published_at'] ?? now())
                : null,
            'display_order' => (int) ($data['display_order'] ?? 0),
        ];
    }

    private function nullableValue(mixed $value): mixed
    {
        return $value === '' || $value === null ? null : $value;
    }

    /**
     * TranslationSaver falls back to a shared slug when none is supplied. Give
     * each directory record a distinct slug so blank CMS fields cannot collide.
     *
     * @param  array<string, mixed>  $translations
     * @return array<string, mixed>
     */
    private function translationSlugs(array $translations, int $partnerId): array
    {
        foreach ($translations as $locale => $values) {
            if (! is_array($values)) {
                continue;
            }

            $slug = trim((string) ($values['slug'] ?? ''));
            if ($slug === '') {
                $base = Str::slug((string) ($values['name'] ?? ''));
                $slug = ($base !== '' ? Str::limit($base, 140, '') : 'partner')
                    .'-'.$partnerId.'-'.$locale;
            }

            $translations[$locale]['slug'] = Str::limit($slug, 180, '');
        }

        return $translations;
    }

    /** @return array<string, mixed> */
    private function auditable(DirectoryPartner $partner): array
    {
        return [
            'category_id' => $partner->directory_partner_category_id,
            'logo_media_id' => $partner->logo_media_id,
            'name' => $partner->t('name', 'ar'),
            'status' => $partner->status,
            'website_url' => $partner->website_url,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function categoryOptions(string $locale): array
    {
        return DirectoryPartnerCategory::query()->with('translations')->orderBy('display_order')->orderBy('id')
            ->get(['id', 'slug', 'is_active'])
            ->map(fn (DirectoryPartnerCategory $category): array => [
                'value' => $category->id,
                'slug' => $category->slug,
                'label' => $category->t('name', $locale) ?: $category->slug,
                'active' => $category->is_active,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function mediaOptions(string $locale): array
    {
        return Media::query()->images()->public()->latest()->limit(100)->get()
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'name' => $media->t('caption', $locale) ?: $media->original_name,
                'url' => $media->url(),
                'alt' => $media->t('alt_text', $locale) ?? '',
            ])->all();
    }

    /** @return array<string, mixed> */
    private function item(DirectoryPartner $partner, string $locale): array
    {
        return [
            'id' => $partner->id,
            'name' => $partner->t('name', $locale) ?: ('Partner '.$partner->id),
            'slug' => $partner->t('slug', $locale),
            'category' => $partner->category?->t('name', $locale),
            'categorySlug' => $partner->category?->slug,
            'status' => $partner->status,
            'year' => $partner->partnership_year,
            'website' => $partner->website_url,
            'logoUrl' => $partner->logo && ! $partner->logo->is_restricted ? $partner->logo->url() : null,
            'displayOrder' => $partner->display_order,
            'locales' => $partner->availableLocales(),
            'updatedAt' => $partner->updated_at?->toISOString(),
        ];
    }

    /** @param LengthAwarePaginator<DirectoryPartner> $paginator @return array<string, mixed> */
    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
