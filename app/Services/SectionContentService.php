<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\DirectoryPartner;
use App\Models\HomepageSection;
use App\Models\HomepageSectionItem;
use App\Models\Media;
use App\Models\Program;
use App\Models\Project;
use App\Models\StrategicPillar;
use App\Models\WebsiteOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Typed content operations for the predefined CMS sections. Every write is
 * scoped to a section declared in config/cms_structure.php; arbitrary
 * sections and arbitrary content structures are impossible here.
 */
final class SectionContentService
{
    public function __construct(private HomepageService $homepage) {}

    /** @return array<string, mixed> */
    public function definition(string $key): array
    {
        $definition = config('cms_structure.sections.'.$key);

        if (! is_array($definition)) {
            throw new InvalidArgumentException('Unknown predefined CMS section.');
        }

        return $definition;
    }

    /** @return array<string, mixed> */
    public function index(string $key, string $locale): array
    {
        $definition = $this->definition($key);
        $section = $this->section($key);
        $content = (string) ($definition['content'] ?? '');

        $sectionPayload = [
            'key' => $key,
            'name' => $definition['label'][$locale] ?? $key,
            'pageKey' => $definition['page'],
            'content' => $definition['content'],
            'source' => $definition['source'] ?? null,
            'enabled' => (bool) $section->is_enabled,
            'status' => $section->status instanceof ContentStatus ? $section->status->value : (string) $section->status,
            'recordLimit' => $definition['content'] === 'slides' ? 24 : 100,
        ];

        if ($content === 'announcements') {
            $sectionPayload['presentation'] = $this->serializePresentation($section);
        }

        return [
            'section' => $sectionPayload,
            'items' => $section->items
                ->sortBy(fn (HomepageSectionItem $item) => [(int) $item->sort_order, (int) $item->id])
                ->map(fn (HomepageSectionItem $item) => $this->serializeItem($item, $locale))
                ->values()
                ->all(),
            'media' => Media::query()->images()->public()->latest()->limit(200)->get()
                ->map(fn (Media $media) => [
                    'id' => (int) $media->id,
                    'name' => $media->t('caption', $locale) ?: $media->original_name,
                    'url' => $media->url(),
                    'alt' => $media->t('alt_text', $locale) ?? '',
                ])
                ->all(),
            'sources' => $this->sourceOptions((string) ($definition['source'] ?? ''), $locale),
            'organization' => $content === 'organization' ? $this->serializeOrganization($locale) : null,
            'can' => [
                'view' => $this->allowed('homepage.view'),
                'update' => $this->allowed('homepage.update'),
            ],
        ];
    }

    /** @param array<string, mixed> $data */
    public function save(string $key, array $data, ?HomepageSectionItem $item = null): HomepageSectionItem
    {
        $section = $this->section($key);
        $content = (string) ($this->definition($key)['content'] ?? '');

        return DB::transaction(function () use ($section, $content, $data, $item): HomepageSectionItem {
            if ($item !== null) {
                abort_unless($item->homepage_section_id === $section->id, 404);
            }

            $previous = $item !== null ? $item->only([
                'media_id', 'item_type', 'item_id', 'parent_item_id', 'item_key',
                'tab', 'is_featured', 'motif_key', 'is_published',
                'display_start_at', 'display_end_at', 'numeric_value',
            ]) : [];

            $attributes = [
                'media_id' => $this->preserved($data, $previous, 'media_id'),
                'item_type' => $data['item_type'] ?? 'inline',
                'item_id' => $this->preserved($data, $previous, 'item_id'),
                'parent_item_id' => $content === 'stories'
                    ? $this->preserved($data, $previous, 'parent_item_id')
                    : null,
                'item_key' => $this->resolveItemKey($content, $data, $previous),
                'tab' => $this->preserved($data, $previous, 'tab'),
                'is_featured' => array_key_exists('is_featured', $data)
                    ? (bool) $data['is_featured']
                    : (bool) ($previous['is_featured'] ?? false),
                'motif_key' => $this->preserved($data, $previous, 'motif_key'),
                'is_published' => array_key_exists('is_published', $data)
                    ? (bool) $data['is_published']
                    : (bool) ($previous['is_published'] ?? true),
                'display_start_at' => $this->preserved($data, $previous, 'display_start_at'),
                'display_end_at' => $this->preserved($data, $previous, 'display_end_at'),
                'numeric_value' => $this->preserved($data, $previous, 'numeric_value'),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ];

            $item ??= new HomepageSectionItem;
            $item->fill($attributes);
            $section->items()->save($item);

            foreach (config('website.locales', ['ar', 'en']) as $locale) {
                $values = $data['translations'][$locale] ?? [];
                if (! is_array($values)) {
                    continue;
                }

                $translation = $item->translations()->where('locale', $locale)->first();
                $translationValues = [];

                foreach ([
                    'label', 'title', 'alt_text', 'body', 'summary', 'display_value',
                    'url', 'date_label', 'cta_label', 'caption', 'unit',
                ] as $field) {
                    $translationValues[$field] = array_key_exists($field, $values)
                        ? $values[$field]
                        : $translation?->{$field};
                }

                $item->translations()->updateOrCreate(['locale' => $locale], $translationValues);
            }

            $saved = $item->load(['translations', 'media.translations']);
            $this->homepage->flush();

            return $saved;
        });
    }

    public function delete(string $key, HomepageSectionItem $item): void
    {
        $section = $this->section($key);
        abort_unless($item->homepage_section_id === $section->id, 404);

        DB::transaction(function () use ($item): void {
            HomepageSectionItem::query()->where('parent_item_id', $item->id)->update(['parent_item_id' => null]);
            $item->translations()->delete();
            $item->delete();
        });

        $this->homepage->flush();
    }

    public function toggle(string $key, HomepageSectionItem $item, bool $enabled): void
    {
        $this->assertOwnership($key, $item);
        $item->update(['is_published' => $enabled]);
        $this->homepage->flush();
    }

    /** @param list<array{id: int, sort_order: int}> $order */
    public function reorder(string $key, array $order): void
    {
        $section = $this->section($key);
        $ids = array_column($order, 'id');

        abort_unless($section->items()->whereIn('id', $ids)->count() === count($ids), 422, 'Invalid item identifiers.');

        DB::transaction(function () use ($section, $order): void {
            foreach ($order as $entry) {
                $section->items()->whereKey($entry['id'])->update([
                    'sort_order' => $entry['sort_order'],
                ]);
            }
        });

        $this->homepage->flush();
    }

    /** @param array<string, mixed> $data */
    public function saveOrganization(string $key, array $data): WebsiteOrganization
    {
        $definition = $this->definition($key);
        abort_unless(($definition['content'] ?? '') === 'organization', 422, 'This section does not manage shared identity.');

        return DB::transaction(function () use ($data): WebsiteOrganization {
            $organization = WebsiteOrganization::query()->firstOrCreate([]);

            if (array_key_exists('logo_media_id', $data)) {
                $organization->update(['logo_media_id' => $data['logo_media_id'] ?: null]);
            }

            if (array_key_exists('destination_url', $data)) {
                $organization->update(['destination_url' => $data['destination_url'] ?: null]);
            }

            foreach (config('website.locales', ['ar', 'en']) as $locale) {
                $values = $data['translations'][$locale] ?? [];
                if (! is_array($values)) {
                    continue;
                }

                $organization->translations()->updateOrCreate(['locale' => $locale], [
                    'title' => $values['title'] ?? null,
                    'description' => $values['description'] ?? null,
                    'vision_title' => $values['vision_title'] ?? null,
                    'vision' => $values['vision'] ?? null,
                    'mission_title' => $values['mission_title'] ?? null,
                    'mission' => $values['mission'] ?? null,
                ]);
            }

            $this->homepage->flush();

            return $organization->load(['translations', 'logo.translations']);
        });
    }

    /** @param array<string, mixed> $data */
    public function savePresentation(string $key, array $data): HomepageSection
    {
        $definition = $this->definition($key);
        abort_unless(($definition['content'] ?? '') === 'announcements', 422, 'This section does not manage presentation labels.');

        $section = $this->section($key);

        DB::transaction(function () use ($section, $data): void {
            foreach (config('website.locales', ['ar', 'en']) as $locale) {
                $values = $data['translations'][$locale] ?? [];
                if (! is_array($values)) {
                    continue;
                }

                $section->translations()->updateOrCreate(['locale' => $locale], [
                    'ticker_label' => $values['ticker_label'] ?? null,
                ]);
            }

            $this->homepage->flush();
        });

        return $section->load('translations');
    }

    public function section(string $key): HomepageSection
    {
        $this->definition($key);

        $section = HomepageSection::query()->where('key', $key)->firstOrFail();

        return $section->loadMissing(['items.translations', 'items.media.translations']);
    }

    private function assertOwnership(string $key, HomepageSectionItem $item): void
    {
        $section = $this->section($key);
        abort_unless($item->homepage_section_id === $section->id, 404);
    }

    /**
     * Keep fields owned by other editors intact when a focused editor does not
     * submit them. An explicit empty value still clears the stored value.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $previous
     */
    private function preserved(array $data, array $previous, string $field): mixed
    {
        if (! array_key_exists($field, $data)) {
            return $previous[$field] ?? null;
        }

        return $data[$field] === '' ? null : $data[$field];
    }

    /**
     * Focused editors do not ask editors to invent identifiers. Hero and
     * ticker rows still need one so the registry can classify them.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $previous
     */
    private function resolveItemKey(string $content, array $data, array $previous): ?string
    {
        $key = $this->preserved($data, $previous, 'item_key');

        if (is_string($key) && trim($key) !== '') {
            return $key;
        }

        if (! in_array($content, ['slides', 'announcements'], true)) {
            return $key;
        }

        $prefix = $content === 'slides' ? 'hero-' : 'ticker-';

        return $prefix.Str::lower(Str::random(10));
    }

    /** @return array{translations: array<string, array<string, string|null>>} */
    private function serializePresentation(HomepageSection $section): array
    {
        $translations = [];

        foreach ($section->loadMissing('translations')->translations as $translation) {
            $translations[$translation->locale] = [
                'tickerLabel' => $translation->ticker_label,
            ];
        }

        return ['translations' => $translations];
    }

    /** @return array<string, mixed> */
    private function serializeItem(HomepageSectionItem $item, string $locale): array
    {
        $translations = [];
        foreach ($item->translations as $translation) {
            $translations[$translation->locale] = [
                'label' => $translation->label,
                'title' => $translation->title,
                'alt_text' => $translation->alt_text,
                'body' => $translation->body,
                'summary' => $translation->summary,
                'display_value' => $translation->display_value,
                'url' => $translation->url,
                'date_label' => $translation->date_label,
                'cta_label' => $translation->cta_label,
                'caption' => $translation->caption,
                'unit' => $translation->unit,
            ];
        }

        return [
            'id' => (int) $item->id,
            'itemKey' => $item->item_key,
            'itemType' => $item->item_type,
            'itemId' => $item->item_id,
            'parentItemId' => $item->parent_item_id,
            'mediaId' => $item->media_id,
            'mediaUrl' => $item->media?->url(),
            'tab' => $item->tab,
            'featured' => (bool) $item->is_featured,
            'motifKey' => $item->motif_key,
            'enabled' => (bool) $item->is_published,
            'scheduleState' => $item->scheduleState(),
            'displayStartAt' => $item->display_start_at?->toISOString(),
            'displayEndAt' => $item->display_end_at?->toISOString(),
            'isSlide' => $item->isHeroSlide(),
            'sortOrder' => (int) $item->sort_order,
            'translations' => $translations,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function sourceOptions(string $source, string $locale): array
    {
        $model = match ($source) {
            'article' => Article::class,
            'project' => Project::class,
            'pillar' => StrategicPillar::class,
            'program' => Program::class,
            'partner' => DirectoryPartner::class,
            default => null,
        };

        if ($model === null) {
            return [];
        }

        /** @var class-string<Model> $model */
        return $model::query()
            ->with('translations')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function (Model $record) use ($locale): array {
                $publishedAt = $record->getAttribute('published_at');

                return [
                    'id' => (int) $record->getKey(),
                    'title' => $record->t('title', $locale)
                        ?: $record->t('name', $locale)
                        ?: ('#'.$record->getKey()),
                    'status' => (string) ($record->getAttribute('workflow_status') ?? $record->getAttribute('status') ?? ''),
                    'publishedAt' => $publishedAt !== null && method_exists($publishedAt, 'toISOString')
                        ? $publishedAt->toISOString()
                        : null,
                ];
            })
            ->all();
    }

    /** @return array<string, mixed> */
    private function serializeOrganization(string $locale): array
    {
        $organization = WebsiteOrganization::query()->with(['translations', 'logo.translations'])->first();
        $translation = $organization?->translate($locale);

        return [
            'id' => $organization?->id,
            'destinationUrl' => $organization?->destination_url,
            'logoMediaId' => $organization?->logo_media_id,
            'logoUrl' => $organization?->logo?->url(),
            'translations' => [
                'ar' => $this->organizationTranslation($organization, 'ar'),
                'en' => $this->organizationTranslation($organization, 'en'),
            ],
            'activeTranslation' => $translation === null ? null : [
                'locale' => $translation->locale,
                'title' => $translation->title,
                'description' => $translation->description,
                'vision_title' => $translation->vision_title,
                'vision' => $translation->vision,
                'mission_title' => $translation->mission_title,
                'mission' => $translation->mission,
            ],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function organizationTranslation(?WebsiteOrganization $organization, string $locale): array
    {
        $translation = $organization?->translate($locale);

        return [
            'title' => $translation?->title ?? (string) config('website.name.'.$locale, ''),
            'description' => $translation?->description ?? (string) config('website.description.'.$locale, ''),
            'vision_title' => $translation?->vision_title ?? ($locale === 'ar' ? 'رؤيتنا' : 'Our vision'),
            'vision' => $translation?->vision ?? (string) config('website.vision.'.$locale, ''),
            'mission_title' => $translation?->mission_title ?? ($locale === 'ar' ? 'رسالتنا' : 'Our mission'),
            'mission' => $translation?->mission ?? (string) config('website.mission.'.$locale, ''),
        ];
    }

    private function allowed(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && app(CmsAccess::class)->allows($user, $permission);
    }
}
