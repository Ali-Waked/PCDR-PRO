<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cms;

use App\Actions\TransitionContentStatus;
use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\ContentRevision;
use App\Models\Media;
use App\Services\AuditService;
use App\Services\CmsAccess;
use App\Services\TranslationSaver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

abstract class ContentController extends Controller
{
    protected function frontendResource(): string
    {
        return $this->uiResource !== '' ? $this->uiResource : $this->resource;
    }

    /** @var class-string<Model> */
    protected string $modelClass;

    protected string $resource;

    protected string $label;

    protected string $routeBase;

    /**
     * Translation group for the page heading. Projects share the Programs
     * permission namespace but keep their own localized page copy.
     */
    protected string $pageTranslationKey = '';

    /**
     * Frontend resource key. Modules that share a permission namespace can
     * override this without changing the authorization contract.
     */
    protected string $uiResource = '';

    /** Non-empty for modules that require a dedicated audit trail. */
    protected string $auditPrefix = '';

    protected string $foreignKey;

    /**
     * Column that stores the publication workflow state. Projects keep their
     * lifecycle in `status` and therefore override this to `workflow_status`.
     */
    protected string $statusColumn = 'status';

    /** @var list<string> */
    protected array $translationFields = [
        'title', 'slug', 'summary', 'body', 'seo_title', 'seo_description',
    ];

    /** @var array<string, mixed> */
    protected array $extraRules = [];

    /** @var list<string> */
    protected array $extraFillable = [];

    public function index(string $locale): Response
    {
        $this->authorizeCms($this->resource.'.view');

        $request = request();
        $search = Str::limit(trim($request->string('search')->toString()), 120, '');
        $requestedStatus = $request->string('status')->toString();
        $status = in_array($requestedStatus, ContentStatus::values(), true) ? $requestedStatus : 'all';
        $sort = $request->string('sort')->toString();
        $sortColumns = $this->sortColumns();
        $sort = array_key_exists($sort, $sortColumns) ? $sort : 'updated_desc';

        /** @var class-string<Model> $class */
        $class = $this->modelClass;
        $query = $class::query()->with($this->indexRelations());

        if ($search !== '') {
            $like = '%'.$search.'%';
            $fields = array_values(array_intersect(
                $this->translationFields,
                ['title', 'slug', 'short_title', 'summary', 'excerpt', 'description', 'body', 'objectives'],
            ));

            $query->whereHas('translations', function (Builder $translationQuery) use ($fields, $like): void {
                $translationQuery->where(function (Builder $fieldQuery) use ($fields, $like): void {
                    foreach ($fields as $index => $field) {
                        $fieldQuery->{$index === 0 ? 'where' : 'orWhere'}($field, 'like', $like);
                    }
                });
            });
        }

        if ($status !== 'all') {
            $query->where($this->statusColumn, $status);
        }

        $this->applyIndexFilters($query, $request);

        $this->applySort($query, $sort, $locale);
        $items = $query->paginate(20)->withQueryString();

        $filterOptions = $this->filterOptions($locale);
        $filters = [
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
        ];

        foreach ($filterOptions as $group) {
            $key = $group['key'];
            $value = $request->query($key);
            if (is_scalar($value) && trim((string) $value) !== '') {
                $filters[$key] = Str::limit(trim((string) $value), 120, '');
            }
        }

        $user = $request->user();
        $access = app(CmsAccess::class);

        $pageKey = $this->pageTranslationKey !== '' ? $this->pageTranslationKey : $this->resource;

        return Inertia::render('Cms/Content/Index', [
            'resource' => $this->frontendResource(),
            'label' => $this->label,
            'routeBase' => $this->routeBase,
            'pageTitle' => __("website.ui.cms_{$pageKey}_page.title", [], $locale),
            'pageDescription' => __("website.ui.cms_{$pageKey}_page.description", [], $locale),
            'items' => $items->through(fn (Model $item) => $this->indexItem($item, $locale))->items(),
            'pagination' => [
                'currentPage' => $items->currentPage(),
                'lastPage' => $items->lastPage(),
                'perPage' => $items->perPage(),
                'total' => $items->total(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
            'counts' => $this->statusCounts($class),
            'filters' => $filters,
            'statuses' => collect(ContentStatus::cases())->map(fn (ContentStatus $case) => [
                'value' => $case->value,
                'label' => $case->label($locale),
                'description' => (string) __("website.ui.cms_content_page.transition_descriptions.{$case->value}", [], $locale),
            ])->all(),
            'sortOptions' => collect($sortColumns)->map(fn (array $definition, string $key) => [
                'value' => $key,
                'label' => __("website.ui.cms_content_page.sorts.{$key}", [], $locale),
            ])->values()->all(),
            'filterOptions' => $filterOptions,
            'transitions' => $this->indexTransitions($locale),
            'can' => [
                'view' => true,
                'create' => $user !== null && $access->allows($user, $this->resource.'.create'),
                'update' => $user !== null && $access->allows($user, $this->resource.'.update'),
                'delete' => $user !== null && $access->allows($user, $this->resource.'.delete'),
                'publish' => $user !== null && $access->allows($user, $this->resource.'.publish'),
                'unpublish' => $user !== null && $access->allows($user, $this->resource.'.unpublish'),
            ],
        ]);
    }

    public function create(string $locale): Response
    {
        $this->authorizeCms($this->resource.'.create');

        return $this->editResponse($locale, null);
    }

    public function store(string $locale, Request $request): RedirectResponse
    {
        $this->authorizeCms($this->resource.'.create');
        $data = $this->validateContent($request);
        $this->authorizeSaveStatus(ContentStatus::from($data['status']));
        $this->assertUniqueSlugs($data['translations']);

        /** @var class-string<Model> $class */
        $class = $this->modelClass;
        $record = new $class;
        $record->fill($this->attributes($data));
        $record->save();

        app(TranslationSaver::class)->sync($record, $data['translations'], $this->translationFields, $this->foreignKey);
        $this->afterSave($record, $data);
        $this->revision($record, $request, 'created');
        $this->recordAudit('created', $record, null, ['status' => $data['status']]);

        return to_route($this->routeBase.'.edit', ['locale' => $locale, 'content' => $record->getKey()])
            ->with('flash', ['success' => __('website.cms.saved')]);
    }

    public function edit(string $locale, int $content): Response
    {
        $this->authorizeCms($this->resource.'.view');

        /** @var class-string<Model> $class */
        $class = $this->modelClass;
        $record = $class::query()->with('translations')->findOrFail($content);

        return $this->editResponse($locale, $record);
    }

    public function update(string $locale, Request $request, int $content): RedirectResponse
    {
        $this->authorizeCms($this->resource.'.update');
        $data = $this->validateContent($request);

        /** @var class-string<Model> $class */
        $class = $this->modelClass;
        $record = $class::query()->findOrFail($content);
        $before = $this->auditSnapshot($record);
        $this->assertUniqueSlugs($data['translations'], $record);
        $attributes = $this->attributes($data);
        unset($attributes[$this->statusColumn]);
        $record->fill($attributes);
        $record->save();

        app(TranslationSaver::class)->sync($record, $data['translations'], $this->translationFields, $this->foreignKey);
        $this->afterSave($record, $data);
        $this->revision($record, $request, 'updated');
        $this->recordAudit('updated', $record, $before, $this->auditSnapshot($record));

        return back()->with('flash', ['success' => __('website.cms.saved')]);
    }

    public function destroy(string $locale, int $content): RedirectResponse
    {
        $this->authorizeCms($this->resource.'.delete');

        /** @var class-string<Model> $class */
        $class = $this->modelClass;
        $record = $class::query()->findOrFail($content);

        abort_if((bool) ($record->is_system ?? false), 403, __('website.cms.system_page_protected'));

        $before = $this->auditSnapshot($record);
        $record->delete();
        $this->recordAudit('deleted', $record, $before);

        return to_route($this->routeBase.'.index', ['locale' => $locale])
            ->with('flash', ['success' => __('website.cms.archived')]);
    }

    public function status(string $locale, Request $request, int $content): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', ContentStatus::values())],
        ]);

        /** @var class-string<Model> $class */
        $class = $this->modelClass;
        $record = $class::query()->findOrFail($content);
        $target = ContentStatus::from($validated['status']);
        $current = ContentStatus::from(ContentStatus::valueOf($record->workflow_status ?? $record->status));
        $before = ['status' => $current->value];

        $this->authorizeCms($this->transitionPermission($current, $target));

        app(TransitionContentStatus::class)->handle(
            $record,
            $target,
            $request->user(),
        );

        $this->recordAudit('status_changed', $record, $before, ['status' => $target->value]);

        return back()->with('flash', ['success' => __('website.cms.status_updated')]);
    }

    protected function transitionPermission(ContentStatus $current, ContentStatus $target): string
    {
        return match (true) {
            $target === ContentStatus::Published => $this->resource.'.publish',
            $current === ContentStatus::Published
                && in_array($target, [ContentStatus::Draft, ContentStatus::Archived], true) => $this->resource.'.unpublish',
            default => $this->resource.'.update',
        };
    }

    /**
     * Creating a record is not a workflow transition, but it must not be a
     * shortcut around publish permissions.
     */
    protected function authorizeSaveStatus(ContentStatus $target): void
    {
        if ($target === ContentStatus::Draft) {
            return;
        }

        $this->authorizeCms($this->transitionPermission(ContentStatus::Draft, $target));
    }

    /** @param mixed $before @param mixed $after */
    protected function recordAudit(string $action, Model $record, $before = null, $after = null): void
    {
        if ($this->auditPrefix === '') {
            return;
        }

        app(AuditService::class)->record(
            $this->auditPrefix.'.'.$action,
            $this->resource,
            (string) $record->getKey(),
            $before,
            $after,
        );
    }

    /** @return array<string, mixed> */
    protected function auditSnapshot(Model $record): array
    {
        return [
            'title' => $record->t('title'),
            'status' => ContentStatus::valueOf($record->getAttribute($this->statusColumn)),
        ];
    }

    /**
     * Sort keys exposed to the content list. Title sorting is resolved against
     * the current translation by applyTranslationTitleSort().
     *
     * @return array<string, array{0: string, 1: string}>
     */
    protected function sortColumns(): array
    {
        return [
            'updated_desc' => ['updated_at', 'desc'],
            'updated_asc' => ['updated_at', 'asc'],
            'created_desc' => ['created_at', 'desc'],
            'published_desc' => ['published_at', 'desc'],
            'title_asc' => ['title', 'asc'],
            'title_desc' => ['title', 'desc'],
        ];
    }

    /** @return list<string> */
    protected function indexRelations(): array
    {
        return ['translations'];
    }

    protected function applyIndexFilters(Builder $query, Request $request): void
    {
        //
    }

    protected function applySort(Builder $query, string $sort, string $locale): void
    {
        if ($sort === 'title_asc' || $sort === 'title_desc') {
            $this->applyTranslationTitleSort($query, $sort, $locale);

            return;
        }

        $columns = $this->sortColumns();
        [$column, $direction] = $columns[$sort] ?? $columns['updated_desc'];
        $keyName = $query->getModel()->getKeyName();

        $query->orderBy($column, $direction)->orderByDesc($keyName);
    }

    private function applyTranslationTitleSort(Builder $query, string $sort, string $locale): void
    {
        $model = $query->getModel();
        $related = $model->translations()->getRelated();
        $table = $related->getTable();
        $keyName = $model->getKeyName();
        $direction = $sort === 'title_asc' ? 'asc' : 'desc';

        $subQuery = $related->newQuery()
            ->select($table.'.title')
            ->whereColumn($table.'.'.$this->foreignKey, $model->getTable().'.'.$keyName)
            ->where(function (Builder $localeQuery) use ($table, $locale): void {
                $localeQuery->where($table.'.locale', $locale)
                    ->orWhere($table.'.locale', config('website.fallback_locale', 'en'));
            })
            ->orderByRaw('case when '.$table.'.locale = ? then 0 else 1 end', [$locale])
            ->limit(1);

        $query->orderByRaw('('.$subQuery->toSql().') '.$direction, $subQuery->getBindings());
    }

    /** @return array<string, mixed> */
    protected function indexItem(Model $item, string $locale): array
    {
        $summary = $item->t('summary', $locale) ?: $item->t('summary');
        if (trim((string) $summary) === '') {
            $summary = $item->t('excerpt', $locale) ?: $item->t('excerpt');
        }

        $status = ContentStatus::valueOf($item->getAttribute($this->statusColumn));

        return [
            'id' => $item->getKey(),
            'title' => $item->t('title', $locale) ?: $item->t('title'),
            'summary' => Str::limit(strip_tags((string) $summary), 160),
            'status' => $status,
            'publishedAt' => $item->published_at?->toDateString(),
            'updatedAt' => $item->updated_at?->toDateString(),
            'locales' => $item->availableLocales(),
        ];
    }

    /** @return list<array{key: string, label: string, options: list<array{value: string|int, label: string}>}> */
    protected function filterOptions(string $locale): array
    {
        return [];
    }

    /**
     * Permission-aware transitions from the graph actually used by
     * TransitionContentStatus. This keeps list and form controls from offering
     * states the server would reject.
     *
     * @return array<string, list<array{value: string, label: string, description: string, permission: string, allowed: bool, confirmation: string|null}>>
     */
    protected function indexTransitions(string $locale): array
    {
        $graph = config('cms.transitions', []);
        $user = auth()->user();
        $access = app(CmsAccess::class);
        $actions = [];

        foreach (ContentStatus::cases() as $current) {
            $actions[$current->value] = [];

            foreach ($graph[$current->value] ?? [] as $targetValue) {
                $target = ContentStatus::tryFrom($targetValue);
                if ($target === null) {
                    continue;
                }

                $permission = $this->transitionPermission($current, $target);
                if ($user === null || ! $access->allows($user, $permission)) {
                    continue;
                }

                $actions[$current->value][] = [
                    'value' => $target->value,
                    'label' => $target->label($locale),
                    'description' => (string) __("website.ui.cms_content_page.transition_descriptions.{$target->value}", [], $locale),
                    'permission' => $permission,
                    'allowed' => true,
                    'confirmation' => match (true) {
                        $target === ContentStatus::Published => 'publish',
                        $target === ContentStatus::Archived => 'archive',
                        $current === ContentStatus::Published => 'reverse',
                        default => null,
                    },
                ];
            }
        }

        return $actions;
    }

    /** @return array<string, mixed> */
    protected function recordAttributes(Model $record): array
    {
        return $record->only($this->extraFillable);
    }

    /** @return array<string, mixed> */
    protected function validateContent(Request $request): array
    {
        return $request->validate(array_merge([
            'status' => ['required', 'in:'.implode(',', ContentStatus::values())],
            'published_at' => ['nullable', 'date'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.slug' => ['nullable', 'string', 'max:180'],
            'translations.*.summary' => ['nullable', 'string', 'max:1000'],
            'translations.*.excerpt' => ['nullable', 'string', 'max:1000'],
            'translations.*.body' => ['nullable', 'string', 'max:100000'],
            'translations.*.description' => ['nullable', 'string', 'max:100000'],
            'translations.*.objectives' => ['nullable', 'string', 'max:20000'],
            'translations.*.target_groups' => ['nullable', 'string', 'max:20000'],
            'translations.*.geographic_coverage' => ['nullable', 'string', 'max:255'],
            'translations.*.seo_title' => ['nullable', 'string', 'max:255'],
            'translations.*.seo_description' => ['nullable', 'string', 'max:500'],
            'translations.*.short_title' => ['nullable', 'string', 'max:120'],
        ], $this->extraRules));
    }

    /** @param class-string<Model> $class @return array<string, int> */
    private function statusCounts(string $class): array
    {
        $counts = ['all' => $class::query()->count()];

        foreach (ContentStatus::cases() as $status) {
            $counts[$status->value] = $class::query()
                ->where($this->statusColumn, $status->value)
                ->count();
        }

        return $counts;
    }

    /** @param array<string, mixed> $data */
    protected function attributes(array $data): array
    {
        $attributes = [
            $this->statusColumn => $data['status'],
            'published_at' => $data['published_at'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
        ];

        foreach ($this->extraFillable as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        return $attributes;
    }

    /** @param array<string, mixed> $data */
    protected function afterSave(Model $record, array $data): void
    {
        //
    }

    protected function revision(Model $record, Request $request, string $note): void
    {
        $payload = [];
        foreach ($record->translations()->get() as $translation) {
            $payload[$translation->locale] = ['title' => $translation->getAttribute('title')];
        }

        ContentRevision::create([
            'revisionable_type' => $record->getMorphClass(),
            'revisionable_id' => $record->getKey(),
            'locale' => app()->getLocale(),
            'title' => $record->t('title'),
            'status' => ContentStatus::valueOf($record->workflow_status ?? $record->status, ''),
            'payload' => $payload,
            'actor_id' => $request->user()?->id,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    protected function authorizeCms(string $permission): void
    {
        abort_unless(
            auth()->check() && app(CmsAccess::class)->allows(auth()->user(), $permission),
            403,
        );
    }

    /**
     * Slugs must be unique per locale within each content type. Collision-safe
     * validation happens before the shared translation saver writes anything.
     *
     * @param  array<string, array<string, mixed>>  $translations
     */
    private function assertUniqueSlugs(array $translations, ?Model $record = null): void
    {
        /** @var class-string<Model> $class */
        $class = $this->modelClass;
        $related = (new $class)->translations()->getRelated();
        $currentIds = [];

        if ($record !== null) {
            foreach ($record->translations()->get(['id', 'locale']) as $translation) {
                $currentIds[(string) $translation->locale] = $translation->getKey();
            }
        }

        foreach ($translations as $locale => $values) {
            $slug = trim((string) ($values['slug'] ?? ''));

            if ($slug === '') {
                continue;
            }

            $query = $related->newQuery()
                ->where('locale', $locale)
                ->where('slug', $slug);

            if (isset($currentIds[$locale])) {
                $query->whereKeyNot($currentIds[$locale]);
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    "translations.{$locale}.slug" => __('website.cms.slug_taken'),
                ]);
            }
        }
    }

    private function editResponse(string $locale, ?Model $record): Response
    {
        $locales = config('website.locales', ['ar', 'en']);
        $translations = [];
        foreach ($locales as $code) {
            $translation = $record?->translations->firstWhere('locale', $code);
            foreach ($this->translationFields as $field) {
                $translations[$code][$field] = $translation?->getAttribute($field) ?? '';
            }
        }

        $revisions = [];
        if ($record !== null) {
            $revisions = ContentRevision::query()
                ->with('actor:id,name')
                ->where('revisionable_type', $record->getMorphClass())
                ->where('revisionable_id', $record->getKey())
                ->latest('created_at')
                ->limit(10)
                ->get(['id', 'locale', 'status', 'note', 'created_at', 'actor_id'])
                ->map(fn (ContentRevision $revision): array => [
                    'id' => $revision->getKey(),
                    'locale' => $revision->locale,
                    'status' => ContentStatus::valueOf($revision->status),
                    'note' => $revision->note,
                    'created_at' => $revision->created_at?->toIso8601String(),
                    'actor' => $revision->actor?->name
                        ?: (string) __('website.ui.cms_unknown_actor', [], $locale),
                ])
                ->all();
        }

        $user = auth()->user();
        $access = app(CmsAccess::class);

        $pageKey = $this->pageTranslationKey !== '' ? $this->pageTranslationKey : $this->resource;

        return Inertia::render('Cms/Content/Edit', [
            'resource' => $this->frontendResource(),
            'label' => $this->label,
            'routeBase' => $this->routeBase,
            'pageTitle' => __("website.ui.cms_{$pageKey}_page.title", [], $locale),
            'pageDescription' => __("website.ui.cms_{$pageKey}_page.description", [], $locale),
            'record' => $record ? [
                'id' => $record->getKey(),
                'title' => $record->t('title', $locale) ?: $record->t('title'),
                'status' => ContentStatus::valueOf($record->getAttribute($this->statusColumn)),
                'publishedAt' => $record->published_at?->toDateString(),
                'displayOrder' => $record->display_order,
                'attributes' => $this->recordAttributes($record),
            ] : null,
            'translations' => $translations,
            'locales' => array_values($locales),
            'activeLocale' => in_array($locale, $locales, true) ? $locale : ($locales[0] ?? 'ar'),
            'statuses' => collect(ContentStatus::cases())->map(fn ($status) => [
                'value' => $status->value,
                'label' => $status->label($locale),
            ])->all(),
            'revisions' => $revisions,
            'options' => array_merge(['media' => $this->mediaOptions()], $this->options($locale)),
            'transitions' => $this->indexTransitions($locale),
            'can' => [
                'view' => true,
                'create' => $user !== null && $access->allows($user, $this->resource.'.create'),
                'update' => $user !== null && $access->allows($user, $this->resource.'.update'),
                'delete' => $user !== null && $access->allows($user, $this->resource.'.delete'),
                'publish' => $user !== null && $access->allows($user, $this->resource.'.publish'),
                'unpublish' => $user !== null && $access->allows($user, $this->resource.'.unpublish'),
            ],
        ]);
    }

    /** @return list<array{id: int, name: string, url: string, alt: string, type: string, restricted: bool, size: string}> */
    protected function mediaOptions(): array
    {
        if (! auth()->check() || ! app(CmsAccess::class)->allows(auth()->user(), 'media.view')) {
            return [];
        }

        return Media::query()
            ->with('translations')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Media $media) => [
                'id' => $media->id,
                'name' => $media->original_name,
                'url' => $media->is_restricted
                    ? URL::temporarySignedRoute(
                        'public.media.download',
                        now()->addMinutes(30),
                        ['locale' => app()->getLocale(), 'uuid' => $media->uuid],
                    )
                    : $media->url(),
                'alt' => $media->t('alt_text', app()->getLocale()),
                'type' => $media->type->value,
                'kind' => $media->kind()->value,
                'extension' => strtolower((string) $media->extension) ?: ($media->kind()->extensions()[0] ?? ''),
                'restricted' => $media->is_restricted,
                'size' => $media->humanSize(),
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    protected function options(string $locale): array
    {
        return [];
    }
}
