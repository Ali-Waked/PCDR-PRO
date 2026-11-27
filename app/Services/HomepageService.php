<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\DirectoryPartner;
use App\Models\HomepageSection;
use App\Models\HomepageSectionItem;
use App\Models\Media;
use App\Models\Page;
use App\Models\Program;
use App\Models\Project;
use App\Models\Publication;
use App\Models\StrategicPillar;
use App\Models\WebsitePage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
            $query->published()->where('is_enabled', true);
            $this->applyVisiblePages($query);
        }

        $section = $query->first();

        if ($section === null) {
            return null;
        }

        $sections = new EloquentCollection([$section]);
        $this->preloadResolutions($sections);

        return $this->serializeSection($section, $locale, $includeUnpublished);
    }

    /**
     * A single section for internal public pages. It honours section and item
     * visibility but does not inherit the homepage page-level flag.
     *
     * @return array<string, mixed>|null
     */
    public function visibleSection(string $key, string $locale): ?array
    {
        $section = HomepageSection::query()
            ->where('key', $key)
            ->published()
            ->where('is_enabled', true)
            ->with($this->relations())
            ->first();

        if ($section === null) {
            return null;
        }

        $this->preloadResolutions(new EloquentCollection([$section]));

        return $this->serializeSection($section, $locale, false);
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
            $this->applyVisiblePages($query);
        }

        /** @var EloquentCollection<int, HomepageSection> $sections */
        $sections = $query
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        $this->preloadResolutions($sections);

        $payload = $sections
            ->map(fn (HomepageSection $section) => $this->serializeSection($section, $locale, $includeUnpublished))
            ->all();

        return $this->mergePresentationSections($payload, $locale);
    }

    /**
     * Restrict public sections to visible predefined pages. Installations
     * without the page registry keep their existing public pages.
     *
     * @param  Builder<HomepageSection>  $query
     */
    private function applyVisiblePages(Builder $query): void
    {
        $allKeys = WebsitePage::query()->pluck('key')->all();

        if ($allKeys === []) {
            return;
        }

        $visibleKeys = WebsitePage::query()->visible()->pluck('key')->all();

        $query->where(static function (Builder $query) use ($allKeys, $visibleKeys): void {
            $query->whereIn('page_key', $visibleKeys)
                ->orWhereNotIn('page_key', $allKeys)
                ->orWhereNull('page_key');
        });
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

    /** @param EloquentCollection<int, HomepageSection> $sections */
    private function preloadResolutions(EloquentCollection $sections): void
    {
        $items = $sections->pluck('items')->flatten()->filter(
            static fn (HomepageSectionItem $item): bool => ! $item->isInline() && $item->item_id !== null,
        );

        foreach ($items->groupBy('item_type') as $type => $group) {
            $modelClass = match ((string) $type) {
                'article' => Article::class,
                'project' => Project::class,
                'pillar' => StrategicPillar::class,
                'program' => Program::class,
                'partner' => DirectoryPartner::class,
                'publication' => Publication::class,
                'page' => Page::class,
                default => null,
            };

            if ($modelClass === null) {
                continue;
            }

            /** @var class-string<Model> $modelClass */
            $relations = match ((string) $type) {
                'article' => ['translations', 'cover'],
                'project' => ['translations', 'featuredImage'],
                'pillar' => ['translations', 'featuredImage'],
                'program' => ['translations', 'featuredImage'],
                'partner' => ['translations', 'logo'],
                default => ['translations'],
            };

            $records = $modelClass::query()
                ->published()
                ->whereIn('id', $group->pluck('item_id')->all())
                ->with($relations)
                ->get()
                ->keyBy('id');

            foreach ($group as $item) {
                $item->useResolved($records->get((int) $item->item_id));
            }
        }
    }

    /**
     * The public prototype renders several required sections inside one
     * component. Merge canonical child sections into those presentation
     * containers in the backend so independent visibility stays server-side.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    private function mergePresentationSections(array $sections, string $locale): array
    {
        $merges = (array) config('cms_structure.presentation_merges', []);

        if ($merges === []) {
            return $sections;
        }

        $result = $sections;
        $indexes = [];

        foreach ($result as $index => $section) {
            $indexes[(string) ($section['key'] ?? '')] = $index;
        }

        foreach ($merges as $targetKey => $sourceKeys) {
            $sources = [];

            foreach ((array) $sourceKeys as $sourceKey) {
                $sourceIndex = $indexes[(string) $sourceKey] ?? null;
                if ($sourceIndex !== null) {
                    $sources[] = $result[$sourceIndex];
                }
            }

            if ($sources === []) {
                continue;
            }

            $targetIndex = $indexes[(string) $targetKey] ?? null;

            if ($targetIndex === null) {
                $target = $this->syntheticSection((string) $targetKey, $sources[0], $locale);
                $result[] = $target;
                $indexes[(string) $targetKey] = array_key_last($result);
                $targetIndex = $indexes[(string) $targetKey];
            }

            /** @var array<string, mixed> $target */
            $target = $result[$targetIndex];
            $tabLabels = is_array($target['tabLabels'] ?? null) ? $target['tabLabels'] : [];

            foreach ($sources as $source) {
                foreach (is_array($source['tabLabels'] ?? null) ? $source['tabLabels'] : [] as $key => $label) {
                    if (! array_key_exists($key, $tabLabels)) {
                        $tabLabels[$key] = $label;
                    }
                }
            }

            foreach ($this->defaultTabLabels((string) $targetKey, $locale) as $key => $label) {
                if (! array_key_exists($key, $tabLabels)) {
                    $tabLabels[$key] = $label;
                }
            }

            $items = array_merge(
                is_array($target['items'] ?? null) ? $target['items'] : [],
                ...array_map(static fn (array $source): array => is_array($source['items'] ?? null) ? $source['items'] : [], $sources),
            );

            usort($items, static function (array $first, array $second): int {
                return [(int) ($first['sortOrder'] ?? 0), (int) ($first['id'] ?? 0)]
                    <=> [(int) ($second['sortOrder'] ?? 0), (int) ($second['id'] ?? 0)];
            });

            $target['items'] = $items;
            $target['tabLabels'] = $tabLabels;
            $result[$targetIndex] = $target;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function syntheticSection(string $key, array $source, string $locale): array
    {
        $registry = (array) config('cms_structure.sections.'.$key, []);

        return [
            'id' => -abs(crc32($key)),
            'key' => $key,
            'type' => $key,
            'status' => 'published',
            'publishedAt' => null,
            'enabled' => true,
            'order' => (int) ($source['order'] ?? 0),
            'eyebrow' => $source['eyebrow'] ?? null,
            'title' => $source['title'] ?? ($registry['label'][$locale] ?? $key),
            'body' => $source['body'] ?? null,
            'ctaLabel' => null,
            'ctaLink' => null,
            'tickerLabel' => null,
            'tabLabels' => $this->defaultTabLabels($key, $locale),
            'videoUrl' => null,
            'videoCaption' => null,
            'items' => [],
        ];
    }

    /** @return array<string, string> */
    private function defaultTabLabels(string $key, string $locale): array
    {
        $arabic = $locale === 'ar';

        return match ($key) {
            'about' => [
                'mission' => $arabic ? 'الرسالة' : 'Mission',
                'objectives' => $arabic ? 'الأهداف' : 'Objectives',
                'vision' => $arabic ? 'الرؤية' : 'Vision',
            ],
            'news' => [
                'news' => $arabic ? 'الأخبار' : 'News',
                'projects' => $arabic ? 'المشاريع' : 'Projects',
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSection(HomepageSection $section, string $locale, bool $includeUnpublished): array
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
                ->filter(fn (HomepageSectionItem $item) => $includeUnpublished || $item->isEligibleNow())
                ->sortBy(fn (HomepageSectionItem $item) => [(int) $item->sort_order, (int) $item->id])
                ->map(fn (HomepageSectionItem $item) => $this->serializeItem($item, $locale, $section->key))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeItem(HomepageSectionItem $item, string $locale, string $sectionKey): ?array
    {
        $media = $this->serializeMedia($item->media, $locale);
        $isSlide = $sectionKey === 'hero' && $item->isHeroSlide();

        if ($item->isInline()) {
            return [
                'id' => (int) $item->id,
                'key' => $item->item_key ?: 'item-'.$item->id,
                'type' => 'inline',
                'sortOrder' => (int) $item->sort_order,
                'slide' => $isSlide,
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

        $fallbackMedia = $model->cover ?? $model->featuredImage ?? $model->logo ?? null;

        return [
            'id' => (int) $model->id,
            'key' => $item->item_key ?: $item->item_type.'-'.$model->id,
            'type' => $item->item_type,
            'sortOrder' => (int) $item->sort_order,
            'slide' => $isSlide,
            'title' => $this->translated($model, 'title', $locale) ?? $this->translated($model, 'name', $locale),
            'label' => $this->translated($model, 'short_title', $locale)
                ?? $this->translated($model, 'title', $locale)
                ?? $this->translated($model, 'name', $locale),
            'body' => $this->translated($model, 'summary', $locale)
                ?? $this->translated($model, 'description', $locale)
                ?? $this->translated($model, 'excerpt', $locale),
            'display' => null,
            'url' => $this->publicRecordUrl((string) $item->item_type, $locale),
            'value' => null,
            'tab' => $item->tab,
            'featured' => (bool) $item->is_featured,
            'motifKey' => $item->motif_key,
            'dateLabel' => $this->translated($item, 'date_label', $locale),
            'ctaLabel' => $this->translated($item, 'cta_label', $locale),
            'caption' => $this->translated($item, 'caption', $locale),
            'unit' => $this->translated($item, 'unit', $locale),
            'media' => $media ?? $this->serializeMedia($fallbackMedia, $locale),
            'published' => true,
        ];
    }

    private function publicRecordUrl(string $type, string $locale): ?string
    {
        $path = match ($type) {
            'article' => 'media',
            'project', 'pillar', 'program' => 'programs',
            'partner' => 'partners',
            default => null,
        };

        return $path === null ? null : $this->safeLink('/'.$locale.'/'.$path);
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
            return $this->isRemovedPublicPath($value) ? null : $value;
        }

        return preg_match('/^https:\/\/[^\s]+$/i', $value) === 1 ? $value : null;
    }

    private function isRemovedPublicPath(string $value): bool
    {
        $path = (string) (parse_url($value, PHP_URL_PATH) ?: '');
        $path = '/'.ltrim($path, '/');

        if (preg_match('#^/(ar|en)/?$#', $path) === 1) {
            return false;
        }

        $path = preg_replace('#^/(ar|en)(?=/)#', '', $path) ?: '/';

        foreach (['strategic-areas', 'projects', 'news', 'publications',
            'transparency', 'partnerships', 'get-involved', 'pages'] as $prefix) {
            if ($path === '/'.$prefix || str_starts_with($path, '/'.$prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
