<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\HomepageSection;
use App\Models\HomepageSectionItem;
use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

final class HomepageService
{
    private const CACHE_SECONDS = 300;

    /**
     * @return list<array<string, mixed>>
     */
    public function sections(string $locale, bool $onlyEnabled = true, bool $includeUnpublished = false): array
    {
        $key = $this->cacheKey($locale, $onlyEnabled, $includeUnpublished);

        return Cache::remember($key, self::CACHE_SECONDS, fn (): array => $this->loadSections(
            $locale,
            $onlyEnabled,
            $includeUnpublished,
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function section(string $key, string $locale, bool $includeUnpublished = false): ?array
    {
        $query = HomepageSection::query()
            ->where('key', $key)
            ->with($this->relations());

        if (! $includeUnpublished) {
            $query->published();
        }

        $section = $query->first();

        return $section ? $this->serializeSection($section, $locale) : null;
    }

    public function flush(): void
    {
        foreach (config('website.locales', ['ar', 'en']) as $locale) {
            foreach ([true, false] as $onlyEnabled) {
                foreach ([true, false] as $includeUnpublished) {
                    Cache::forget($this->cacheKey((string) $locale, $onlyEnabled, $includeUnpublished));
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'translations',
            'items.translations',
            'items.media.translations',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadSections(string $locale, bool $onlyEnabled, bool $includeUnpublished): array
    {
        $query = HomepageSection::query()->with($this->relations());

        if ($onlyEnabled) {
            $query->where('is_enabled', true);
        }

        if (! $includeUnpublished) {
            $query->published();
        }

        return $query
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn (HomepageSection $section) => $this->serializeSection($section, $locale))
            ->all();
    }

    private function cacheKey(string $locale, bool $onlyEnabled, bool $includeUnpublished): string
    {
        return sprintf(
            'homepage.sections.%s.%s.%s',
            $locale,
            $onlyEnabled ? 'enabled' : 'all',
            $includeUnpublished ? 'all-status' : 'published',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSection(HomepageSection $section, string $locale): array
    {
        $status = $section->status instanceof ContentStatus
            ? $section->status->value
            : ContentStatus::valueOf($section->status);

        return [
            'id' => (int) $section->id,
            'key' => $section->key,
            'type' => $section->type,
            'status' => $status,
            'publishedAt' => $section->published_at?->toISOString(),
            'enabled' => (bool) $section->is_enabled,
            'order' => (int) $section->display_order,
            'eyebrow' => $this->translated($section, 'eyebrow', $locale),
            'title' => $this->translated($section, 'title', $locale),
            'body' => $this->translated($section, 'body', $locale),
            'ctaLabel' => $this->translated($section, 'cta_label', $locale),
            'ctaLink' => $this->safeLink($this->translated($section, 'cta_link', $locale)),
            'tickerLabel' => $this->translated($section, 'ticker_label', $locale),
            'tabLabels' => $this->translated($section, 'tab_labels', $locale),
            'videoUrl' => $this->safeLink($this->translated($section, 'video_url', $locale)),
            'videoCaption' => $this->translated($section, 'video_caption', $locale),
            'items' => $section->items
                ->filter(fn (HomepageSectionItem $item) => (bool) $item->is_published)
                ->sortBy(fn (HomepageSectionItem $item) => [(int) $item->sort_order, (int) $item->id])
                ->map(fn (HomepageSectionItem $item) => $this->serializeItem($item, $locale))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeItem(HomepageSectionItem $item, string $locale): ?array
    {
        $media = $this->serializeMedia($item->media, $locale);

        if ($item->isInline()) {
            return [
                'id' => (int) $item->id,
                'key' => $item->item_key ?: 'item-'.$item->id,
                'type' => 'inline',
                'sortOrder' => (int) $item->sort_order,
                'title' => $this->translated($item, 'title', $locale),
                'label' => $this->translated($item, 'label', $locale),
                'body' => $this->translated($item, 'body', $locale),
                'display' => $this->translated($item, 'display_value', $locale),
                'url' => $this->safeLink($this->translated($item, 'url', $locale)),
                'value' => $item->numeric_value === null ? null : (float) $item->numeric_value,
                'tab' => $item->tab,
                'featured' => (bool) $item->is_featured,
                'motifKey' => $item->motif_key,
                'dateLabel' => $this->translated($item, 'date_label', $locale),
                'ctaLabel' => $this->translated($item, 'cta_label', $locale),
                'caption' => $this->translated($item, 'caption', $locale),
                'unit' => $this->translated($item, 'unit', $locale),
                'media' => $media,
                'published' => true,
            ];
        }

        $model = $item->resolve();

        if (! $model) {
            return null;
        }

        return [
            'id' => (int) $model->id,
            'key' => $item->item_key ?: $item->item_type.'-'.$model->id,
            'type' => $item->item_type,
            'sortOrder' => (int) $item->sort_order,
            'title' => $this->translated($model, 'title', $locale),
            'label' => $this->translated($model, 'short_title', $locale) ?? $this->translated($model, 'title', $locale),
            'body' => $this->translated($model, 'summary', $locale) ?? $this->translated($model, 'description', $locale),
            'display' => null,
            'url' => $this->safeLink(method_exists($model, 'route') ? $model->route($locale) : null),
            'value' => null,
            'tab' => $item->tab,
            'featured' => (bool) $item->is_featured,
            'motifKey' => $item->motif_key,
            'dateLabel' => $this->translated($item, 'date_label', $locale),
            'ctaLabel' => $this->translated($item, 'cta_label', $locale),
            'caption' => $this->translated($item, 'caption', $locale),
            'unit' => $this->translated($item, 'unit', $locale),
            'media' => $media,
            'published' => true,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeMedia(?Media $media, string $locale): ?array
    {
        if ($media === null || $media->is_restricted) {
            return null;
        }

        return [
            'id' => (int) $media->id,
            'url' => $this->mediaUrl($media, $locale),
            'alt' => $media->t('alt_text', $locale) ?? '',
            'caption' => $media->t('caption', $locale),
            'width' => $media->width,
            'height' => $media->height,
        ];
    }

    private function mediaUrl(Media $media, string $locale): string
    {
        if ($media->disk === config('website.uploads.private_disk')) {
            return route('public.media.download', [
                'locale' => $locale,
                'uuid' => $media->uuid,
            ]);
        }

        return Storage::disk($media->disk)->url($media->path);
    }

    private function translated(Model $model, string $field, string $locale): mixed
    {
        return $model->t($field, $locale);
    }

    /**
     * Mirror the CMS link rules at serialization time so the public payload
     * never emits a value a browser could normalize into another scheme.
     */
    private function safeLink(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || $value !== trim($value)) {
            return null;
        }

        if (preg_match('/[\x00-\x1F\x7F\s]/', $value) === 1) {
            return null;
        }

        if (str_contains($value, '\\') || stripos($value, '%5c') !== false) {
            return null;
        }

        if (preg_match('/^#[A-Za-z0-9_:-]+$/', $value) === 1) {
            return $value;
        }

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return $value;
        }

        return preg_match('/^https:\/\/[^\s]+$/i', $value) === 1 ? $value : null;
    }
}
