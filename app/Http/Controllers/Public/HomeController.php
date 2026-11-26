<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Services\ContentQuery;
use App\Services\HomepageService;
use Inertia\Inertia;
use Inertia\Response;

final class HomeController extends PublicController
{
    public function __invoke(string $locale, ContentQuery $content, HomepageService $homepage): Response
    {
        return Inertia::render('Public/Home', [
            'vision' => config("website.vision.{$locale}"),
            'mission' => config("website.mission.{$locale}"),
            'pillars' => $content->publishedPillars()->map(fn ($pillar) => [
                'code' => $pillar->code,
                'title' => $pillar->t('title', $locale),
                'shortTitle' => $pillar->t('short_title', $locale),
                'description' => $pillar->t('description', $locale),
                'url' => $pillar->route($locale),
            ])->all(),
            'framework' => collect(config('website.impact_framework', []))->map(fn ($step) => [
                'key' => $step['key'],
                'label' => $step[$locale],
            ])->all(),
            'articles' => $content->featuredArticles(3)->map(fn ($article) => [
                'id' => $article->id,
                'title' => $article->t('title', $locale),
                'excerpt' => $article->t('excerpt', $locale),
                'publishedAt' => $article->published_at?->toDateString(),
                'category' => $article->category?->t('name', $locale),
                'url' => $article->route($locale),
                'image' => $article->cover?->url(),
            ])->all(),
            'programs' => $content->publishedPrograms(6)->map(fn ($program) => [
                'id' => $program->id,
                'title' => $program->t('title', $locale),
                'summary' => $program->t('summary', $locale),
                'pillar' => $program->pillar?->t('short_title', $locale),
                'url' => $program->route($locale),
            ])->all(),
            'publications' => $content->featuredPublications(4)->map(fn ($publication) => [
                'id' => $publication->id,
                'title' => $publication->t('title', $locale),
                'category' => $publication->categoryLabel($locale),
                'date' => $publication->publication_date?->toDateString(),
                'version' => $publication->version,
                'url' => $publication->route($locale),
                'downloadUrl' => $publication->document && ! $publication->is_restricted
                    ? $publication->document->url() : null,
                'restricted' => $publication->is_restricted,
            ])->all(),
            'sections' => $homepage->sections($locale),
            'meta' => $this->seo($locale, (string) config("website.slogan.{$locale}"), path: "{$locale}"),
        ]);
    }
}
