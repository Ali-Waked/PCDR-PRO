<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\WebsitePage;
use Illuminate\Support\Facades\Cache;

final class NavigationService
{
    /** @return array<string, list<array<string, mixed>>> */
    public function all(string $locale): array
    {
        return Cache::remember("website.navigation.{$locale}", 300, function () use ($locale): array {
            $result = [];

            foreach (NavigationMenu::query()->with('items.translations')->get() as $menu) {
                $result[$menu->key] = $menu->items
                    ->where('is_enabled', true)
                    ->whereNull('parent_id')
                    ->filter(fn (NavigationItem $item) => WebsitePage::isPathVisible((string) $item->resolvedUrl($locale)))
                    ->map(fn (NavigationItem $item) => $this->serialize($item, $locale))
                    ->values()
                    ->all();
            }

            return $result;
        });
    }

    /** @return array{label: string, url: string, children: list<array<string, mixed>>} */
    private function serialize(NavigationItem $item, string $locale): array
    {
        return [
            'label' => $item->t('label', $locale) ?: $item->link_value,
            'url' => $item->resolvedUrl($locale),
            'external' => $item->link_type === 'external',
            'children' => $item->children->where('is_enabled', true)
                ->filter(fn (NavigationItem $child) => WebsitePage::isPathVisible((string) $child->resolvedUrl($locale)))
                ->map(fn (NavigationItem $child) => $this->serialize($child, $locale))
                ->values()
                ->all(),
        ];
    }

    public function flush(): void
    {
        foreach (config('website.locales', ['ar', 'en']) as $locale) {
            Cache::forget("website.navigation.{$locale}");
        }
    }
}
