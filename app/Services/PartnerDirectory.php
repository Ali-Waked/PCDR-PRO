<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DirectoryPartner;
use App\Models\DirectoryPartnerCategory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the only public representation of the partner directory.
 *
 * The payload is derived from the CMS tables on every request: there is no
 * second data store and no cache that could keep an unpublished partner live.
 */
final class PartnerDirectory
{
    /**
     * @return array{
     *     categories: list<array<string, mixed>>,
     *     groups: list<array<string, mixed>>,
     *     activeCategory: string,
     *     total: int,
     *     hasPartners: bool
     * }
     */
    public function overview(string $locale, ?string $categorySlug = null): array
    {
        $partners = DirectoryPartner::query()
            ->visibleToPublic()
            ->where(static function (Builder $query): void {
                $query->whereNull('directory_partner_category_id')
                    ->orWhereHas('category', static fn (Builder $categoryQuery): Builder => $categoryQuery->active());
            })
            ->with(['translations', 'category.translations', 'logo'])
            ->ordered()
            ->get();

        $activeCategories = DirectoryPartnerCategory::query()
            ->active()
            ->with('translations')
            ->ordered()
            ->get();

        $categories = $activeCategories->map(fn (DirectoryPartnerCategory $category): array => [
            'slug' => $category->slug,
            'name' => $category->t('name', $locale) ?: $category->slug,
            'description' => $category->t('description', $locale),
            'count' => $partners->where('directory_partner_category_id', $category->id)->count(),
        ])->values()->all();

        $uncategorized = $partners->whereNull('directory_partner_category_id')->values();
        if ($uncategorized->isNotEmpty()) {
            $categories[] = [
                'slug' => 'other',
                'name' => (string) __('website.ui.partner_directory.other_partners', [], $locale),
                'description' => null,
                'count' => $uncategorized->count(),
            ];
        }

        $validSlugs = array_column($categories, 'slug');
        $active = is_string($categorySlug) && in_array($categorySlug, $validSlugs, true)
            ? $categorySlug
            : 'all';

        $groups = [];
        foreach ($activeCategories as $category) {
            if ($active !== 'all' && $active !== $category->slug) {
                continue;
            }

            $categoryPartners = $partners->where('directory_partner_category_id', $category->id)->values();
            if ($categoryPartners->isEmpty()) {
                continue;
            }

            $groups[] = [
                'slug' => $category->slug,
                'name' => $category->t('name', $locale) ?: $category->slug,
                'description' => $category->t('description', $locale),
                'partners' => $categoryPartners
                    ->map(fn (DirectoryPartner $partner): array => $this->partner($partner, $locale))
                    ->all(),
            ];
        }

        if ($uncategorized->isNotEmpty() && ($active === 'all' || $active === 'other')) {
            $groups[] = [
                'slug' => 'other',
                'name' => (string) __('website.ui.partner_directory.other_partners', [], $locale),
                'description' => null,
                'partners' => $uncategorized
                    ->map(fn (DirectoryPartner $partner): array => $this->partner($partner, $locale))
                    ->all(),
            ];
        }

        return [
            'categories' => $categories,
            'groups' => $groups,
            'activeCategory' => $active,
            'total' => $partners->count(),
            'hasPartners' => $partners->isNotEmpty(),
        ];
    }

    /**
     * Only fields that are safe and meaningful to visitors leave this method.
     *
     * @return array<string, mixed>
     */
    private function partner(DirectoryPartner $partner, string $locale): array
    {
        $logo = $partner->logo;
        $logoUrl = $logo !== null && ! $logo->is_restricted ? $logo->url() : null;
        $name = $partner->t('name', $locale);

        return [
            'name' => $name ?: ('Partner '.$partner->id),
            'description' => $partner->t('description', $locale),
            'year' => $partner->partnership_year,
            'website' => $this->safeWebsite($partner->website_url),
            'logo' => $logoUrl === null ? null : [
                'url' => $logoUrl,
                'alt' => $name ?: (string) __('website.ui.partner_directory.partner_logo', [], $locale),
            ],
        ];
    }

    private function safeWebsite(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || $value !== trim($value)) {
            return null;
        }

        if (preg_match('/[\x00-\x1F\x7F\s]/', $value) === 1 || str_contains($value, '\\')) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['https', 'http'], true) ? $value : null;
    }
}
