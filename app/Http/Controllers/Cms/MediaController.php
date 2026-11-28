<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cms;

use App\Actions\DeleteMedia;
use App\Actions\UploadMedia;
use App\Enums\MediaKind;
use App\Exceptions\MediaInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadMediaRequest;
use App\Models\Media;
use App\Services\AuditService;
use App\Services\CmsAccess;
use App\Services\HomepageService;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class MediaController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly HomepageService $homepage,
    ) {}

    private function authorize(string $permission): void
    {
        abort_unless(auth()->check() && app(CmsAccess::class)->allows(auth()->user(), $permission), 403);
    }

    private function allowed(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && app(CmsAccess::class)->allows($user, $permission);
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function displayExtension(Media $media): string
    {
        $extension = strtolower((string) $media->extension);
        if (preg_match('/^[a-z0-9]{1,8}$/', $extension) === 1) {
            return $extension;
        }

        return $media->kind()->extensions()[0] ?? '';
    }

    public function index(string $locale, Request $request): Response
    {
        $this->authorize('media.view');

        $type = in_array($request->string('type')->toString(), ['image', 'document'], true)
            ? $request->string('type')->toString()
            : '';
        $kind = in_array($request->string('kind')->toString(), MediaKind::values(), true)
            ? $request->string('kind')->toString()
            : '';
        $visibility = in_array($request->string('visibility')->toString(), ['public', 'restricted'], true)
            ? $request->string('visibility')->toString()
            : '';
        $search = Str::limit(trim($request->string('q')->toString()), 120, '');
        $dateFrom = $this->parseDate($request->string('date_from')->toString());
        $dateTo = $this->parseDate($request->string('date_to')->toString());
        $sort = in_array($request->string('sort')->toString(), [
            'newest', 'oldest', 'name_asc', 'name_desc', 'size_asc', 'size_desc',
        ], true)
            ? $request->string('sort')->toString()
            : 'newest';
        $escapedSearch = addcslashes($search, '\\%_');

        $media = Media::query()
            ->with('translations')
            ->when($type === 'image', fn ($q) => $q->images())
            ->when($type === 'document', fn ($q) => $q->documents())
            ->when($kind !== '', fn ($q) => $q->kind($kind))
            ->when($visibility === 'public', fn ($q) => $q->where('is_restricted', false))
            ->when($visibility === 'restricted', fn ($q) => $q->where('is_restricted', true))
            ->when($search !== '', fn ($q) => $q->whereRaw("original_name LIKE ? ESCAPE '\\'", ["%{$escapedSearch}%"]))
            ->when($dateFrom !== null, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($sort === 'oldest', fn ($q) => $q->oldest())
            ->when($sort === 'name_asc', fn ($q) => $q->orderBy('original_name')->orderByDesc('id'))
            ->when($sort === 'name_desc', fn ($q) => $q->orderByDesc('original_name')->orderByDesc('id'))
            ->when($sort === 'size_asc', fn ($q) => $q->orderBy('size_bytes')->orderByDesc('id'))
            ->when($sort === 'size_desc', fn ($q) => $q->orderByDesc('size_bytes')->orderByDesc('id'))
            ->when($sort === 'newest', fn ($q) => $q->latest())
            ->paginate(24)
            ->withQueryString();

        $base = Media::query();
        $stats = [
            'all' => (clone $base)->count(),
            'images' => (clone $base)->images()->count(),
            'documents' => (clone $base)->documents()->count(),
            'restricted' => (clone $base)->where('is_restricted', true)->count(),
        ];

        return Inertia::render('Cms/Media/Index', [
            'items' => $media->through(fn (Media $item) => [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'name' => $item->original_name,
                'type' => $item->type->value,
                'kind' => $item->kind()->value,
                'mime' => $item->mime_type,
                'extension' => $this->displayExtension($item),
                'size' => $item->humanSize(),
                'sizeBytes' => (int) $item->size_bytes,
                'createdAt' => $item->created_at?->toISOString(),
                'canPreview' => $item->canPreview(),
                'usage' => app(DeleteMedia::class)->usage($item),
                'restricted' => $item->is_restricted,
                'url' => $item->is_restricted ? null : $item->url(),
                'accessUrl' => $item->is_restricted
                    ? URL::temporarySignedRoute(
                        'public.media.download',
                        now()->addMinutes(30),
                        ['locale' => $locale, 'uuid' => $item->uuid],
                    )
                    : $item->url(),
                'width' => $item->width,
                'height' => $item->height,
                'alt' => $item->t('alt_text', $locale),
                'caption' => $item->t('caption', $locale),
                'translations' => collect(config('website.locales', ['ar', 'en']))
                    ->mapWithKeys(fn (string $code) => [$code => [
                        'alt_text' => $item->t('alt_text', $code) ?: '',
                        'caption' => $item->t('caption', $code) ?: '',
                    ]])
                    ->all(),
            ])->items(),
            'pagination' => [
                'currentPage' => $media->currentPage(),
                'lastPage' => $media->lastPage(),
                'perPage' => $media->perPage(),
                'total' => $media->total(),
                'from' => $media->firstItem(),
                'to' => $media->lastItem(),
            ],
            'filters' => [
                'type' => $type,
                'kind' => $kind,
                'visibility' => $visibility,
                'q' => $search,
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
                'sort' => $sort,
            ],
            'stats' => $stats,
            'kindOptions' => collect(MediaKind::cases())
                ->map(fn (MediaKind $case) => [
                    'value' => $case->value,
                    'label' => __($case->labelKey(), [], $locale),
                ])->all(),
            'sortOptions' => collect([
                'newest', 'oldest', 'name_asc', 'name_desc', 'size_asc', 'size_desc',
            ])->map(fn (string $key) => [
                'value' => $key,
                'label' => __("website.ui.cms_media_sort_{$key}", [], $locale),
            ])->all(),
            'uploadFormats' => [
                'image' => array_values(array_unique(array_filter(array_map(
                    fn (string $mime): string => (string) (config('website.uploads.mime_extensions', [])[$mime] ?? ''),
                    config('website.uploads.allowed_image_mimes', []),
                )))),
                'document' => array_values(array_unique(array_filter(array_map(
                    fn (string $mime): string => (string) (config('website.uploads.mime_extensions', [])[$mime] ?? ''),
                    config('website.uploads.allowed_document_mimes', []),
                )))),
                'maxKb' => (int) config('website.uploads.max_file_size_kb', 10240),
            ],
            'can' => [
                'view' => true,
                'upload' => $this->allowed('media.upload'),
                'update' => $this->allowed('media.update'),
                'delete' => $this->allowed('media.delete'),
            ],
        ]);
    }

    public function store(UploadMediaRequest $request, UploadMedia $action): RedirectResponse
    {
        try {
            $media = $action->handle(
                $request->file('file'),
                (bool) $request->boolean('is_restricted'),
                $request->user(),
                $request->input('translations', []),
            );
        } catch (\InvalidArgumentException $exception) {
            return back()->with('flash', ['error' => $exception->getMessage()]);
        }

        $this->audit->record('media.uploaded', 'media', (string) $media->id, null, [
            'name' => $media->original_name,
            'type' => $media->type->value,
            'restricted' => $media->is_restricted,
        ]);

        return back()->with('flash', [
            'success' => __('website.cms.media_uploaded', ['name' => $media->original_name]),
        ]);
    }

    public function update(string $locale, Request $request, int $media): RedirectResponse
    {
        $this->authorize('media.update');

        $validated = $request->validate([
            'translations' => ['required', 'array'],
            'translations.*.alt_text' => ['nullable', 'string', 'max:255'],
            'translations.*.caption' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var Media $record */
        $record = Media::query()->findOrFail($media);
        foreach ($validated['translations'] as $code => $values) {
            if (! in_array($code, config('website.locales', ['ar', 'en']), true)) {
                continue;
            }
            $record->translations()->updateOrCreate(['locale' => $code], $values);
        }
        $record->unsetRelation('translations');
        $this->homepage->flush();
        $this->audit->record('media.updated', 'media', (string) $record->id, null, [
            'locales' => array_keys($validated['translations']),
        ]);

        return back()->with('flash', ['success' => __('website.cms.saved')]);
    }

    public function destroy(string $locale, int $media): RedirectResponse
    {
        $this->authorize('media.delete');

        /** @var Media $record */
        $record = Media::query()->findOrFail($media);

        $before = ['name' => $record->original_name, 'type' => $record->type->value];

        try {
            app(DeleteMedia::class)->handle($record);
        } catch (MediaInUseException) {
            return back()->with('flash', ['error' => __('website.cms.media_in_use')]);
        }

        $this->audit->record('media.deleted', 'media', (string) $record->id, $before);

        return back()->with('flash', ['success' => __('website.cms.deleted')]);
    }

    public function signed(string $locale, int $media): JsonResponse
    {
        $this->authorize('media.view');

        /** @var Media $record */
        $record = Media::query()->findOrFail($media);

        $url = URL::temporarySignedRoute(
            'public.media.download', now()->addMinutes(30), [
                'locale' => $locale,
                'uuid' => $record->uuid,
            ],
        );

        $this->audit->record('media.signed_link_issued', 'media', (string) $record->id, null, [
            'restricted' => $record->is_restricted,
            'expires_in_minutes' => 30,
        ]);

        return response()->json(['url' => $url]);
    }
}
