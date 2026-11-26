<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDirectoryPartnerCategoryRequest;
use App\Http\Requests\UpdateDirectoryPartnerCategoryRequest;
use App\Models\DirectoryPartnerCategory;
use App\Services\AuditService;
use App\Services\CmsAccess;
use App\Services\TranslationSaver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DirectoryPartnerCategoryController extends Controller
{
    private const SORT_COLUMNS = [
        'display_order' => 'display_order',
        'slug' => 'slug',
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

    public function edit(string $locale, Request $request, DirectoryPartnerCategory $category): Response
    {
        $this->authorizeCms('directory_partners.view');
        $category->load('translations');

        return Inertia::render('Cms/PartnerDirectory/Index', [
            ...$this->listProps($locale, $request),
            'form' => $this->formData($category, $locale),
            'editing' => $category->id,
        ]);
    }

    public function store(string $locale, StoreDirectoryPartnerCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $category = new DirectoryPartnerCategory;
        $category->fill([
            'slug' => $data['slug'],
            'is_active' => (bool) ($data['is_active'] ?? false),
            'display_order' => (int) ($data['display_order'] ?? 0),
        ]);
        $category->save();

        $this->translations->sync($category, $data['translations'], ['name', 'description'], 'directory_partner_category_id', ['description']);
        $this->audit->record('directory_partners.category_created', 'directory_partner_categories', (string) $category->id, after: $this->auditable($category));

        return $this->listRedirect($locale, $request)
            ->with('flash', ['success' => __('website.cms.saved', [], $locale)]);
    }

    public function update(string $locale, UpdateDirectoryPartnerCategoryRequest $request, DirectoryPartnerCategory $category): RedirectResponse
    {
        $before = $this->auditable($category);
        $data = $request->validated();
        $category->fill([
            'slug' => $data['slug'],
            'is_active' => (bool) ($data['is_active'] ?? false),
            'display_order' => (int) ($data['display_order'] ?? 0),
        ]);
        $category->save();

        $this->translations->sync($category, $data['translations'], ['name', 'description'], 'directory_partner_category_id', ['description']);
        $this->audit->record('directory_partners.category_updated', 'directory_partner_categories', (string) $category->id, $before, $this->auditable($category));

        return $this->listRedirect($locale, $request)
            ->with('flash', ['success' => __('website.cms.saved', [], $locale)]);
    }

    public function destroy(string $locale, Request $request, DirectoryPartnerCategory $category): RedirectResponse
    {
        $this->authorizeCms('directory_partners.delete');

        if ($category->partners()->exists()) {
            return back()->with('flash', [
                'error' => __('website.ui.partner_directory.category_has_partners', [], $locale),
            ]);
        }

        $before = $this->auditable($category);
        $category->delete();
        $this->audit->record('directory_partners.category_deleted', 'directory_partner_categories', (string) $category->id, $before);

        return $this->listRedirect($locale, $request)
            ->with('flash', ['success' => __('website.cms.archived', [], $locale)]);
    }

    public function status(string $locale, Request $request, DirectoryPartnerCategory $category): RedirectResponse
    {
        $this->authorizeCms('directory_partners.update');
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        $before = $this->auditable($category);
        $category->update(['is_active' => (bool) $data['is_active']]);
        $this->audit->record('directory_partners.category_status_updated', 'directory_partner_categories', (string) $category->id, $before, $this->auditable($category));

        return back()->with('flash', ['success' => __('website.cms.status_updated', [], $locale)]);
    }

    public function reorder(string $locale, Request $request): RedirectResponse
    {
        $this->authorizeCms('directory_partners.update');
        $data = $request->validate([
            'id' => ['required', 'integer', 'exists:directory_partner_categories,id'],
            'display_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $category = DirectoryPartnerCategory::query()->findOrFail((int) $data['id']);
        $before = ['display_order' => $category->display_order];
        $category->update(['display_order' => (int) $data['display_order']]);
        $this->audit->record('directory_partners.category_reordered', 'directory_partner_categories', (string) $category->id, $before, ['display_order' => $category->display_order]);

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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listProps(string $locale, Request $request): array
    {
        $search = mb_strcut(trim($request->string('search')->toString()), 0, 120);
        $status = strtolower($request->string('status')->toString());
        $status = in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all';
        $sort = $request->string('sort')->toString();
        $sort = array_key_exists($sort, self::SORT_COLUMNS) ? $sort : 'display_order';
        $direction = strtolower($request->string('direction')->toString());
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';
        $perPage = $request->integer('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 20;

        $query = DirectoryPartnerCategory::query()
            ->with(['translations', 'partners' => fn ($query) => $query->select('id', 'directory_partner_category_id', 'status')]);

        if ($search !== '') {
            $query->where(static function ($query) use ($search): void {
                $query->where('slug', 'like', '%'.$search.'%')
                    ->orWhereHas('translations', static fn ($translationQuery) => $translationQuery
                        ->where('name', 'like', '%'.$search.'%'));
            });
        }
        if ($status !== 'all') {
            $query->where('is_active', $status === 'active');
        }

        $query->orderBy(self::SORT_COLUMNS[$sort], $direction)->orderBy('id', $direction);
        $categories = $query->paginate($perPage)->withQueryString();

        return [
            'view' => 'categories',
            'items' => $categories->through(fn (DirectoryPartnerCategory $category): array => $this->item($category, $locale))->items(),
            'pagination' => $this->pagination($categories),
            'categories' => [],
            'media' => [],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'counts' => [
                'all' => DirectoryPartnerCategory::query()->count(),
                'active' => DirectoryPartnerCategory::query()->where('is_active', true)->count(),
                'inactive' => DirectoryPartnerCategory::query()->where('is_active', false)->count(),
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
        foreach (['search', 'status', 'sort', 'direction', 'per_page', 'page'] as $key) {
            $value = $request->query($key);
            if (is_scalar($value) && $value !== '') {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    private function listRedirect(string $locale, Request $request): RedirectResponse
    {
        return to_route('cms.partner-directory.categories.index', ['locale' => $locale] + $this->listQuery($request));
    }

    /** @return array<string, mixed> */
    private function blankForm(): array
    {
        return [
            'slug' => '',
            'is_active' => true,
            'display_order' => 0,
            'translations' => [
                'ar' => ['name' => '', 'description' => ''],
                'en' => ['name' => '', 'description' => ''],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function formData(DirectoryPartnerCategory $category, string $locale): array
    {
        return [
            'slug' => $category->slug,
            'is_active' => $category->is_active,
            'display_order' => $category->display_order,
            'translations' => [
                'ar' => [
                    'name' => $category->t('name', 'ar') ?? '',
                    'description' => $category->t('description', 'ar') ?? '',
                ],
                'en' => [
                    'name' => $category->t('name', 'en') ?? '',
                    'description' => $category->t('description', 'en') ?? '',
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function auditable(DirectoryPartnerCategory $category): array
    {
        return [
            'slug' => $category->slug,
            'name' => $category->t('name', 'ar'),
            'is_active' => $category->is_active,
            'display_order' => $category->display_order,
        ];
    }

    /** @return array<string, mixed> */
    private function item(DirectoryPartnerCategory $category, string $locale): array
    {
        $partners = $category->relationLoaded('partners') ? $category->getRelation('partners') : collect();

        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $category->t('name', $locale) ?: $category->slug,
            'description' => $category->t('description', $locale),
            'isActive' => $category->is_active,
            'displayOrder' => $category->display_order,
            'partnerCount' => $partners->count(),
            'publishedCount' => $partners->where('status', 'published')->count(),
        ];
    }

    /** @param LengthAwarePaginator<DirectoryPartnerCategory> $paginator @return array<string, mixed> */
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
