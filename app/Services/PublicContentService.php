<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Article;
use App\Models\Media;
use App\Models\Program;
use App\Models\Project;
use App\Models\StrategicPillar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only query boundary for the public institutional pages.
 *
 * Every list is derived from published CMS records on each request. Draft,
 * scheduled, archived, soft-deleted and restricted records are excluded, and
 * no public page can escalate visibility through request input.
 */
final class PublicContentService
{
    public function __construct(
        private readonly HomepageService $homepage,
        private readonly SiteSettings $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function about(string $locale): array
    {
        $section = $this->homepage->section('about', $locale);
        $items = $this->sectionItems($section);
        $configOverview = (string) config('website.description.'.$locale, '');
        $body = $this->text($section, 'body') ?: $configOverview;

        return [
            'title' => $this->text($section, 'title') ?: $this->defaultTitle($locale, 'من نحن', 'About us'),
            'eyebrow' => $this->text($section, 'eyebrow'),
            'body' => $body,
            'overview' => [
                'title' => $this->text($section, 'title') ?: $this->defaultTitle($locale, 'من نحن', 'About us'),
                'body' => $body,
            ],
            'vision' => $this->textItem($items, 'vision', (string) config('website.vision.'.$locale, '')),
            'mission' => $this->textItem($items, 'mission', (string) config('website.mission.'.$locale, '')),
            'objectives' => $this->namedItems($items, ['objective-', 'goal-']),
            'board' => $this->namedItems($items, ['board-']),
            'achievements' => $this->namedItems($items, ['achievement-', 'stat-']),
            'values' => $this->namedItems($items, ['value-']),
            'pillars' => $this->publishedPillars($locale),
            'image' => $this->firstImage($items, $locale),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function programs(string $locale, ?string $area = null): array
    {
        $pillars = $this->publishedPillars($locale);
        $filter = trim((string) $area);
        $matched = $filter === '' ? null : collect($pillars)->first(
            static fn (array $pillar): bool => $pillar['code'] === $filter
                || (string) $pillar['id'] === $filter,
        );

        $query = Program::query()
            ->published()
            ->whereHas('pillar', static fn (Builder $query): Builder => $query->published())
            ->with([
                'translations',
                'pillar.translations',
                'pillar.featuredImage',
                'featuredImage',
                'projects' => static fn (Builder|HasMany $query): Builder|HasMany => $query
                    ->published()
                    ->with(['translations', 'featuredImage'])
                    ->orderBy('display_order')
                    ->orderBy('id'),
            ]);

        if ($filter !== '') {
            $query->whereHas('pillar', static function (Builder $query) use ($filter, $matched): void {
                $query->published()->where(static function (Builder $inner) use ($filter, $matched): void {
                    $inner->where('code', $matched['code'] ?? $filter);

                    if (ctype_digit($filter)) {
                        $inner->orWhere('id', (int) $filter);
                    }
                });
            });
        }

        $programs = $query
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Program $program): array => $this->serializeProgram($program, $locale))
            ->all();

        return [
            'areas' => $pillars,
            'programs' => $programs,
            'activeArea' => $matched['code'] ?? ($filter !== '' ? $filter : null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mediaCenter(string $locale): array
    {
        $articles = Article::query()
            ->published()
            ->with(['translations', 'category.translations', 'cover'])
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->orderBy('id')
            ->get();

        $news = [];
        $activities = [];

        foreach ($articles as $article) {
            $item = $this->serializeArticle($article, $locale);
            $category = $article->category;
            $categoryName = $category?->t('name', $locale) ?? '';
            $categorySlug = (string) ($category?->slug ?? '');

            if ($this->isActivity($categorySlug, $categoryName)) {
                $activities[] = $item;
            } else {
                $news[] = $item;
            }
        }

        return [
            'news' => $news,
            'activities' => $activities,
            'albums' => $this->homepageCollection('albums', $locale),
            'stories' => $this->homepageCollection('stories', $locale),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function contact(string $locale): array
    {
        $details = $this->settings->contactDetails($locale);
        $footer = $this->homepage->section('footer', $locale);
        $footerItems = $this->sectionItems($footer);

        $socialLinks = $this->footerSocialLinks($footerItems);
        if ($socialLinks === []) {
            $socialLinks = $this->settings->socialLinks($locale);
        }

        $mapLink = $this->settings->value('contact.map_link', null, $locale)
            ?? $this->footerLink($footerItems, 'map');

        return [
            'email' => $details['email'] ?? null,
            'phone' => $details['phone'] ?? null,
            'address' => $details['address'] ?? null,
            'hours' => $details['hours'] ?? null,
            'workingHours' => $details['hours'] ?? $this->settings->value('contact.working_hours', null, $locale),
            'mapLink' => $this->safeLink($mapLink),
            'socialLinks' => $socialLinks,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function publishedPillars(string $locale): array
    {
        return StrategicPillar::query()
            ->published()
            ->with(['translations', 'featuredImage'])
            ->orderBy('display_order')
            ->orderBy('code')
            ->get()
            ->map(fn (StrategicPillar $pillar): array => [
                'id' => (int) $pillar->id,
                'code' => (string) $pillar->code,
                'title' => $pillar->t('title', $locale) ?: $pillar->t('short_title', $locale),
                'shortTitle' => $pillar->t('short_title', $locale) ?: $pillar->t('title', $locale),
                'description' => $pillar->t('description', $locale),
                'objectives' => $pillar->objectiveLines(),
                'image' => $this->serializeMedia($pillar->featuredImage, $locale),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProgram(Program $program, string $locale): array
    {
        $pillar = $program->pillar;
        $pillarIsPublic = $pillar !== null && $pillar->isPublished();

        return [
            'id' => (int) $program->id,
            'title' => $program->t('title', $locale),
            'summary' => $program->t('summary', $locale) ?: $program->t('description', $locale),
            'description' => $program->t('description', $locale),
            'startDate' => $program->start_date?->toISOString(),
            'endDate' => $program->end_date?->toISOString(),
            'publishedAt' => $program->published_at?->toISOString(),
            'area' => $pillarIsPublic ? [
                'id' => (int) $pillar->id,
                'code' => (string) $pillar->code,
                'title' => $pillar->t('title', $locale) ?: $pillar->t('short_title', $locale),
            ] : null,
            'image' => $this->serializeMedia($program->featuredImage, $locale),
            'projects' => $program->relationLoaded('projects')
                ? $program->projects->map(fn (Project $project): array => [
                    'id' => (int) $project->id,
                    'title' => $project->t('title', $locale),
                    'summary' => $project->t('summary', $locale) ?: $project->t('description', $locale),
                    'status' => $project->status?->value,
                    'statusLabel' => $project->status?->label($locale),
                    'startDate' => $project->start_date?->toISOString(),
                    'endDate' => $project->end_date?->toISOString(),
                    'publishedAt' => $project->published_at?->toISOString(),
                    'image' => $this->serializeMedia($project->featuredImage, $locale),
                ])->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeArticle(Article $article, string $locale): array
    {
        $category = $article->category;

        return [
            'id' => (int) $article->id,
            'title' => $article->t('title', $locale),
            'summary' => $article->t('excerpt', $locale),
            'body' => $article->t('body', $locale),
            'publishedAt' => $article->published_at?->toISOString(),
            'eventDate' => $article->event_date?->toISOString(),
            'category' => $category === null ? null : [
                'slug' => (string) $category->slug,
                'name' => $category->t('name', $locale) ?: $category->slug,
            ],
            'image' => $this->serializeMedia($article->cover, $locale),
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

    /**
     * @return array<string, mixed>
     */
    private function homepageCollection(string $key, string $locale): array
    {
        $section = $this->homepage->section($key, $locale);

        return [
            'title' => $this->text($section, 'title'),
            'eyebrow' => $this->text($section, 'eyebrow'),
            'items' => $this->sectionItems($section),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $section
     * @return list<array<string, mixed>>
     */
    private function sectionItems(?array $section): array
    {
        $items = $section['items'] ?? null;

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>|null  $model
     */
    private function text(?array $model, string $field): ?string
    {
        $value = $model[$field] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{title: string|null, body: string|null}
     */
    private function textItem(array $items, string $key, string $fallback = ''): array
    {
        $item = collect($items)->first(
            static fn (array $candidate): bool => ($candidate['key'] ?? null) === $key
                || str_starts_with((string) ($candidate['key'] ?? ''), $key.'-'),
        );

        $title = $this->text($item, 'title') ?? $this->text($item, 'label');
        $body = $this->text($item, 'body') ?? $this->text($item, 'display');

        return [
            'title' => $title ?: $this->defaultTitle(
                app()->getLocale(),
                $key === 'vision' ? 'رؤيتنا' : 'رسالتنا',
                $key === 'vision' ? 'Our vision' : 'Our mission',
            ),
            'body' => $body ?: ($fallback !== '' ? $fallback : null),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<string>  $prefixes
     * @return list<array<string, mixed>>
     */
    private function namedItems(array $items, array $prefixes): array
    {
        return collect($items)
            ->filter(static function (array $item) use ($prefixes): bool {
                $key = (string) ($item['key'] ?? '');

                foreach ($prefixes as $prefix) {
                    if ($key !== '' && str_starts_with($key, $prefix)) {
                        return true;
                    }
                }

                return false;
            })
            ->map(static fn (array $item): array => [
                'id' => $item['id'] ?? null,
                'key' => $item['key'] ?? null,
                'title' => $item['title'] ?? $item['label'] ?? null,
                'body' => $item['body'] ?? $item['display'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function firstImage(array $items, string $locale): ?array
    {
        foreach ($items as $item) {
            $media = $item['media'] ?? null;
            if (is_array($media)) {
                /** @var array<string, mixed> $media */
                return $media;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{platform: string, label: string, url: string}>
     */
    private function footerSocialLinks(array $items): array
    {
        return collect($items)
            ->filter(static fn (array $item): bool => str_starts_with((string) ($item['key'] ?? ''), 'social-'))
            ->map(fn (array $item): ?array => $this->socialLink($item))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{platform: string, label: string, url: string}|null
     */
    private function socialLink(array $item): ?array
    {
        $url = $this->safeLink($item['url'] ?? null);

        if ($url === null) {
            return null;
        }

        $key = (string) ($item['key'] ?? '');
        $platform = str_replace('social-', '', $key);

        return [
            'platform' => $platform,
            'label' => $this->text($item, 'title') ?? $this->text($item, 'label') ?? $platform,
            'url' => $url,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function footerLink(array $items, string $key): ?string
    {
        $item = collect($items)->first(
            static fn (array $candidate): bool => str_starts_with((string) ($candidate['key'] ?? ''), $key),
        );

        return $item === null ? null : ($this->safeLink($item['url'] ?? null) ?? $this->safeLink($item['ctaLink'] ?? null));
    }

    private function isActivity(string $slug, string $name): bool
    {
        $haystack = mb_strtolower($slug.' '.$name);

        foreach (['activit', 'activity', 'event', 'workshop', 'field-visit', 'أنشطة', 'نشاط', 'فعاليات', 'ورشة'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function safeLink(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || $value !== trim($value)) {
            return null;
        }

        if (preg_match('/[\x00-\x1F\x7F\s]/', $value) === 1
            || str_contains($value, '\\')
            || stripos($value, '%5c') !== false) {
            return null;
        }

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return $value;
        }

        return preg_match('/^https:\/\/[^\s]+$/i', $value) === 1 ? $value : null;
    }

    private function defaultTitle(string $locale, string $arabic, string $english): string
    {
        return $locale === 'ar' ? $arabic : $english;
    }
}
