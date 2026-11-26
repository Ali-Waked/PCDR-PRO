<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cms;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\ReorderHomepageRequest;
use App\Http\Requests\Cms\ToggleHomepageSectionRequest;
use App\Http\Requests\Cms\UpdateHomepageRequest;
use App\Http\Requests\Cms\UpdateHomepageStatusRequest;
use App\Models\HomepageSection;
use App\Models\Media;
use App\Services\AuditService;
use App\Services\CmsAccess;
use App\Services\HomepageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class HomepageController extends Controller
{
    /** @var list<string> */
    private const CANONICAL_KEYS = [
        'hero', 'intro', 'areas', 'framework', 'programs',
        'targets', 'community', 'partners', 'knowledge', 'cta',
    ];

    public function __construct(private AuditService $audit) {}

    private function authorize(string $permission): void
    {
        abort_unless(auth()->check() && app(CmsAccess::class)->allows(auth()->user(), $permission), 403);
    }

    public function index(string $locale): Response
    {
        $this->authorize('homepage.view');

        $sections = HomepageSection::query()
            ->with('translations', 'items.translations', 'items.media.translations')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        if ($sections->isEmpty()) {
            foreach (self::CANONICAL_KEYS as $index => $key) {
                HomepageSection::create([
                    'key' => $key,
                    'type' => $key,
                    'status' => ContentStatus::Published,
                    'is_enabled' => true,
                    'display_order' => $index,
                ]);
            }

            $sections = HomepageSection::query()
                ->with('translations', 'items.translations', 'items.media.translations')
                ->orderBy('display_order')
                ->orderBy('id')
                ->get();
        }

        return Inertia::render('Cms/Homepage/Index', [
            'sections' => $sections->map(fn (HomepageSection $section) => [
                'id' => $section->id,
                'key' => $section->key,
                'type' => $section->type,
                'status' => ContentStatus::valueOf($section->status),
                'enabled' => $section->is_enabled,
                'order' => $section->display_order,
                'translations' => $section->translations->mapWithKeys(fn ($t) => [$t->locale => [
                    'eyebrow' => $t->eyebrow,
                    'title' => $t->title,
                    'body' => $t->body,
                    'cta_label' => $t->cta_label,
                    'cta_link' => $t->cta_link,
                ]])->all(),
                'items' => $section->items->map(fn ($item) => [
                    'id' => $item->id,
                    'itemType' => $item->item_type,
                    'itemId' => $item->item_id,
                    'mediaId' => $item->media_id,
                    'itemKey' => $item->item_key,
                    'isPublished' => $item->is_published,
                    'numericValue' => $item->numeric_value,
                    'sortOrder' => $item->sort_order,
                    'translations' => $item->translations->mapWithKeys(fn ($t) => [$t->locale => [
                        'label' => $t->label,
                        'title' => $t->title,
                        'body' => $t->body,
                        'display_value' => $t->display_value,
                        'url' => $t->url,
                    ]])->all(),
                ])->all(),
            ])->all(),
            'media' => $this->mediaOptions($locale),
            'can' => $this->capabilities(),
            'preview' => app(HomepageService::class)->sections($locale, false, true),
        ]);
    }

    public function update(string $locale, UpdateHomepageRequest $request, HomepageService $homepage): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            foreach ($validated['sections'] as $sectionData) {
                $section = HomepageSection::query()->findOrFail($sectionData['id']);
                $attributes = [
                    'is_enabled' => $sectionData['is_enabled'] ?? false,
                    'display_order' => $sectionData['display_order'] ?? 0,
                ];

                if (array_key_exists('status', $sectionData)) {
                    $status = ContentStatus::from((string) $sectionData['status']);
                    $attributes['status'] = $status;
                    $attributes['published_at'] = $status === ContentStatus::Published
                        ? ($sectionData['published_at'] ?? $section->published_at ?? now())
                        : null;
                }

                $section->update($attributes);

                foreach ($sectionData['translations'] ?? [] as $code => $values) {
                    if (! in_array($code, config('website.locales', ['ar', 'en']), true)) {
                        continue;
                    }
                    $section->translations()->updateOrCreate(['locale' => $code], $values);
                }

                if (! array_key_exists('items', $sectionData)) {
                    continue;
                }

                $removeIds = collect($sectionData['items'])
                    ->filter(fn ($item) => (bool) ($item['remove'] ?? false))
                    ->pluck('id')
                    ->filter()
                    ->all();

                if ($removeIds !== []) {
                    $section->items()->whereIn('id', $removeIds)->delete();
                }

                $sortOrder = 0;
                foreach ($sectionData['items'] as $itemData) {
                    if ((bool) ($itemData['remove'] ?? false)) {
                        continue;
                    }

                    $attributes = [
                        'item_type' => $itemData['item_type'],
                        'item_id' => $itemData['item_id'] ?? null,
                        'media_id' => $this->nullableMediaId($itemData['media_id'] ?? null),
                        'item_key' => $itemData['item_key'] ?? null,
                        'is_published' => $itemData['is_published'] ?? true,
                        'numeric_value' => $itemData['numeric_value'] ?? null,
                        'sort_order' => $sortOrder++,
                    ];

                    $item = ! empty($itemData['id'])
                        ? $section->items()->find($itemData['id'])
                        : null;

                    if ($item) {
                        $item->update($attributes);
                    } else {
                        $item = $section->items()->create($attributes);
                    }

                    foreach ($itemData['translations'] ?? [] as $code => $values) {
                        if (! in_array($code, config('website.locales', ['ar', 'en']), true)) {
                            continue;
                        }
                        $item->translations()->updateOrCreate(['locale' => $code], $values);
                    }
                }
            }
        });

        $homepage->flush();
        $this->audit->record('homepage.updated', 'homepage', 'sections');

        return back()->with('flash', ['success' => __('website.cms.saved')]);
    }

    public function reorder(
        string $locale,
        ReorderHomepageRequest $request,
        HomepageService $homepage,
    ): RedirectResponse {
        $order = $request->validated('order', []);

        DB::transaction(function () use ($order): void {
            foreach ($order as $entry) {
                HomepageSection::query()->findOrFail($entry['id'])->update([
                    'display_order' => $entry['display_order'],
                ]);
            }
        });

        $homepage->flush();
        $this->audit->record('homepage.reordered', 'homepage', 'sections');

        return back()->with('flash', ['success' => __('website.cms.saved', [], $locale)]);
    }

    public function status(
        string $locale,
        UpdateHomepageStatusRequest $request,
        HomepageSection $homepageSection,
        HomepageService $homepage,
    ): RedirectResponse {
        $status = ContentStatus::from((string) $request->validated('status'));
        $publishedAt = $request->validated('published_at');

        $homepageSection->update([
            'status' => $status,
            'published_at' => $status === ContentStatus::Published
                ? ($publishedAt ?? $homepageSection->published_at ?? now())
                : null,
        ]);

        $homepage->flush();
        $this->audit->record(
            'homepage.status_updated',
            'homepage',
            (string) $homepageSection->id,
            ['status' => $status->value],
        );

        return back()->with('flash', ['success' => __('website.cms.status_updated', [], $locale)]);
    }

    public function toggle(
        string $locale,
        ToggleHomepageSectionRequest $request,
        HomepageSection $homepageSection,
        HomepageService $homepage,
    ): RedirectResponse {
        $isEnabled = (bool) $request->validated('is_enabled');

        $homepageSection->update(['is_enabled' => $isEnabled]);
        $homepage->flush();
        $this->audit->record(
            'homepage.visibility_updated',
            'homepage',
            (string) $homepageSection->id,
            ['is_enabled' => $isEnabled],
        );

        return back()->with('flash', ['success' => __('website.cms.saved', [], $locale)]);
    }

    public function preview(string $locale, HomepageService $homepage): JsonResponse
    {
        $this->authorize('homepage.view');

        return response()->json([
            'data' => [
                'locale' => $locale,
                'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
                'sections' => $homepage->sections($locale, false, true),
            ],
        ]);
    }

    /** @return array<string, bool> */
    private function capabilities(): array
    {
        $user = auth()->user();
        $access = app(CmsAccess::class);

        return [
            'view' => $user !== null && $access->allows($user, 'homepage.view'),
            'update' => $user !== null && $access->allows($user, 'homepage.update'),
        ];
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
                'type' => 'image',
            ])->all();
    }

    private function nullableMediaId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
