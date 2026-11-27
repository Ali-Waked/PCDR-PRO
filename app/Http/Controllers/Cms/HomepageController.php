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

        return Inertia::render('Cms/Homepage/Index', [
            'sections' => $sections->map(fn (HomepageSection $section) => [
                'id' => $section->id,
                'key' => $section->key,
                'type' => $section->type,
                'status' => ContentStatus::valueOf($section->status),
                'publishedAt' => $section->published_at?->toISOString(),
                'enabled' => $section->is_enabled,
                'order' => $section->display_order,
                'translations' => $section->translations->mapWithKeys(fn ($t) => [$t->locale => [
                    'eyebrow' => $t->eyebrow,
                    'title' => $t->title,
                    'body' => $t->body,
                    'cta_label' => $t->cta_label,
                    'cta_link' => $t->cta_link,
                    'ticker_label' => $t->ticker_label,
                    'tickerLabel' => $t->ticker_label,
                    'tab_labels' => $t->tab_labels,
                    'tabLabels' => $t->tab_labels,
                    'video_url' => $t->video_url,
                    'videoUrl' => $t->video_url,
                    'video_caption' => $t->video_caption,
                    'videoCaption' => $t->video_caption,
                ]])->all(),
                'items' => $section->items->map(fn ($item) => [
                    'id' => $item->id,
                    'itemType' => $item->item_type,
                    'itemId' => $item->item_id,
                    'mediaId' => $item->media_id,
                    'itemKey' => $item->item_key,
                    'tab' => $item->tab,
                    'featured' => (bool) $item->is_featured,
                    'motifKey' => $item->motif_key,
                    'isPublished' => $item->is_published,
                    'numericValue' => $item->numeric_value,
                    'sortOrder' => $item->sort_order,
                    'translations' => $item->translations->mapWithKeys(fn ($t) => [$t->locale => [
                        'label' => $t->label,
                        'title' => $t->title,
                        'body' => $t->body,
                        'display_value' => $t->display_value,
                        'display' => $t->display_value,
                        'url' => $t->url,
                        'date_label' => $t->date_label,
                        'dateLabel' => $t->date_label,
                        'cta_label' => $t->cta_label,
                        'ctaLabel' => $t->cta_label,
                        'caption' => $t->caption,
                        'unit' => $t->unit,
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
                    'is_enabled' => $sectionData['is_enabled'] ?? $section->is_enabled,
                    'display_order' => $sectionData['display_order'] ?? $section->display_order,
                ];

                if (array_key_exists('status', $sectionData)) {
                    $status = ContentStatus::from((string) $sectionData['status']);
                    $attributes['status'] = $status;
                    $publishedAt = $sectionData['published_at'] ?? $sectionData['publishedAt'] ?? null;
                    $attributes['published_at'] = $status === ContentStatus::Published
                        ? ($publishedAt ?: ($section->published_at ?? now()))
                        : null;
                }

                $section->update($attributes);

                foreach ($sectionData['translations'] ?? [] as $code => $values) {
                    if (! in_array($code, config('website.locales', ['ar', 'en']), true)) {
                        continue;
                    }
                    $section->translations()->updateOrCreate(
                        ['locale' => $code],
                        $this->normalizeSectionTranslationValues($values),
                    );
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

                    $submittedOrder = $itemData['sort_order'] ?? $sortOrder;
                    $attributes = [
                        'item_type' => $itemData['item_type'],
                        'item_id' => $itemData['item_id'] ?? null,
                        'media_id' => $this->nullableMediaId($itemData['media_id'] ?? null),
                        'item_key' => $itemData['item_key'] ?? null,
                        'tab' => $itemData['tab'] ?? null,
                        'is_featured' => $itemData['featured'] ?? $itemData['is_featured'] ?? false,
                        'motif_key' => $itemData['motifKey'] ?? $itemData['motif_key'] ?? null,
                        'is_published' => $itemData['is_published'] ?? true,
                        'numeric_value' => $itemData['numeric_value'] ?? null,
                        'sort_order' => (int) $submittedOrder,
                    ];

                    $sortOrder = max($sortOrder, (int) $submittedOrder) + 1;

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
                        $item->translations()->updateOrCreate(
                            ['locale' => $code],
                            $this->normalizeItemTranslationValues($values),
                        );
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

    /**
     * CMS payloads expose public camel-case names, while the database columns
     * remain snake-case. Normalising here avoids recursive Eloquent mutators.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalizeSectionTranslationValues(array $values): array
    {
        return $this->normalizeTranslationKeys($values, [
            'tickerLabel' => 'ticker_label',
            'tabLabels' => 'tab_labels',
            'videoUrl' => 'video_url',
            'videoCaption' => 'video_caption',
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalizeItemTranslationValues(array $values): array
    {
        return $this->normalizeTranslationKeys($values, [
            'display' => 'display_value',
            'dateLabel' => 'date_label',
            'ctaLabel' => 'cta_label',
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $aliases
     * @return array<string, mixed>
     */
    private function normalizeTranslationKeys(array $values, array $aliases): array
    {
        foreach ($aliases as $alias => $column) {
            if (! array_key_exists($alias, $values)) {
                continue;
            }

            if (! array_key_exists($column, $values) || $values[$column] === null || $values[$column] === '') {
                $values[$column] = $values[$alias];
            }

            unset($values[$alias]);
        }

        return $values;
    }
}
