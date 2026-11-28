<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\HomepageSection;
use App\Models\WebsitePage;
use Illuminate\Support\Facades\DB;

/**
 * Owns the predefined page/section registry and its presentation state.
 * It never writes to content tables: visibility changes are reversible.
 */
final class WebsiteStructureService
{
    public function __construct(
        private HomepageService $homepage,
        private NavigationService $navigation,
    ) {}

    /** @return array{pages: list<array<string, mixed>>, sections: list<array<string, mixed>>} */
    public function structure(string $locale): array
    {
        $registry = config('cms_structure.sections', []);
        $pageRegistry = config('cms_structure.pages', []);
        $allPageKeys = WebsitePage::query()->pluck('key')->all();
        $visiblePageKeys = WebsitePage::query()->visible()->pluck('key')->all();

        $sections = HomepageSection::query()
            ->with(['translations', 'items.media.translations'])
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        $pages = WebsitePage::query()
            ->with('translations')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(function (WebsitePage $page) use ($locale, $pageRegistry, $registry, $sections, $allPageKeys, $visiblePageKeys): array {
                $key = (string) $page->key;

                return [
                    'id' => (int) $page->id,
                    'key' => $key,
                    'path' => $page->path,
                    'name' => $page->t('name', $locale) ?: ($pageRegistry[$key]['label'][$locale] ?? $key),
                    'enabled' => (bool) $page->is_enabled,
                    'order' => (int) $page->display_order,
                    'sections' => $sections
                        ->where('page_key', $key)
                        ->values()
                        ->map(fn (HomepageSection $section): array => $this->serializeSection(
                            $section,
                            $locale,
                            $registry,
                            $allPageKeys,
                            $visiblePageKeys,
                        ))
                        ->all(),
                ];
            })
            ->all();

        return [
            'pages' => $pages,
            'sections' => $sections
                ->map(fn (HomepageSection $section): array => $this->serializeSection(
                    $section,
                    $locale,
                    $registry,
                    $allPageKeys,
                    $visiblePageKeys,
                ))
                ->all(),
        ];
    }

    public function setPageVisibility(WebsitePage $page, bool $enabled): WebsitePage
    {
        $page->update(['is_enabled' => $enabled]);
        $this->flush();

        return $page->refresh();
    }

    public function setSectionVisibility(HomepageSection $section, bool $enabled): HomepageSection
    {
        $section->update(['is_enabled' => $enabled]);
        $this->flush();

        return $section->refresh();
    }

    /** @param list<array{id: int, display_order: int}> $order */
    public function reorderSections(array $order): void
    {
        DB::transaction(function () use ($order): void {
            foreach ($order as $entry) {
                HomepageSection::query()->findOrFail($entry['id'])->update([
                    'display_order' => $entry['display_order'],
                ]);
            }
        });

        $this->flush();
    }

    private function flush(): void
    {
        $this->homepage->flush();
        $this->navigation->flush();
    }

    /**
     * @param  array<string, mixed>  $registry
     * @param  list<string>  $allPageKeys
     * @param  list<string>  $visiblePageKeys
     * @return array<string, mixed>
     */
    private function serializeSection(
        HomepageSection $section,
        string $locale,
        array $registry,
        array $allPageKeys,
        array $visiblePageKeys,
    ): array {
        $key = (string) $section->key;
        $definition = $registry[$key] ?? [];
        $content = (string) ($definition['content'] ?? 'content');
        $source = (string) ($definition['source'] ?? '');
        $status = $section->status instanceof ContentStatus
            ? $section->status->value
            : (string) $section->status;
        $pageKey = (string) $section->page_key;
        $pageVisible = $allPageKeys === []
            || ! in_array($pageKey, $allPageKeys, true)
            || in_array($pageKey, $visiblePageKeys, true);
        $sectionPublished = $status === ContentStatus::Published->value;

        $counts = [
            'hidden' => 0,
            'scheduled' => 0,
            'expired' => 0,
            'eligible' => 0,
        ];
        $slideCount = 0;
        $eligibleSlideCount = 0;

        foreach ($section->items as $item) {
            $state = $item->scheduleState();
            $counts[$state]++;

            if ($key === 'hero' && $item->isHeroSlide()) {
                $slideCount++;
                if ($state === 'eligible') {
                    $eligibleSlideCount++;
                }
            }
        }

        return [
            'id' => (int) $section->id,
            'key' => $key,
            'pageKey' => $pageKey,
            'name' => ($definition['label'][$locale] ?? $section->t('title', $locale)) ?: $key,
            'content' => $content,
            'source' => $source,
            'enabled' => (bool) $section->is_enabled,
            'status' => $status,
            'published' => $sectionPublished,
            'pageVisible' => $pageVisible,
            'order' => (int) $section->display_order,
            'recordCount' => $section->items->count(),
            'visibleCount' => $sectionPublished && $section->is_enabled && $pageVisible ? $counts['eligible'] : 0,
            'eligibleCount' => $counts['eligible'],
            'hiddenCount' => $counts['hidden'],
            'unpublishedCount' => $counts['hidden'],
            'scheduledCount' => $counts['scheduled'],
            'expiredCount' => $counts['expired'],
            'slideCount' => $slideCount,
            'eligibleSlideCount' => $eligibleSlideCount,
            'manageUrl' => route('cms.content.index', ['locale' => $locale, 'section' => $key]),
        ];
    }
}
