<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\WebsitePage;
use App\Services\Seo;

abstract class PublicController extends Controller
{
    /** @param array<string, mixed> $overrides */
    protected function seo(
        string $locale,
        string $title,
        ?string $description = null,
        ?string $path = null,
        ?Media $image = null,
        array $overrides = [],
    ): array {
        return app(Seo::class)->for($locale, $title, $description, $path, $image, $overrides);
    }

    protected function assertPageVisible(string $key): void
    {
        abort_unless(WebsitePage::isVisible($key), 404);
    }

    /**
     * Resolve SEO copy from the predefined page registry, falling back to the
     * controller copy on installations that have not seeded it yet.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function pageSeo(
        string $locale,
        string $key,
        string $title,
        ?string $description,
        string $path,
        array $overrides = [],
    ): array {
        $page = WebsitePage::query()->with('translations')->where('key', $key)->first();
        $translation = $page?->translate($locale);

        $seoTitle = $translation?->getAttribute('seo_title') ?: $translation?->getAttribute('name') ?: $title;
        $seoDescription = $translation?->getAttribute('seo_description') ?: $description;

        return $this->seo($locale, (string) $seoTitle, $seoDescription, $path, overrides: $overrides);
    }
}
