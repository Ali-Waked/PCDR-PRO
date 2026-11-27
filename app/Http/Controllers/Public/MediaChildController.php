<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Services\PublicContentService;
use Inertia\Inertia;
use Inertia\Response;

final class MediaChildController extends PublicController
{
    /**
     * Independent Media Center pages. The child keys mirror the actual seeded
     * navigation children; removed aggregate landing pages are not registered.
     *
     * @var array<string, array{page: string, component: string, method: string, title: array{ar: string, en: string}, description: array{ar: string, en: string}}>
     */
    private const PAGES = [
        'news' => [
            'page' => 'media_news',
            'component' => 'Public/Media/News',
            'method' => 'mediaNews',
            'title' => ['ar' => 'الأخبار والأنشطة', 'en' => 'News and activities'],
            'description' => ['ar' => 'الأخبار والأنشطة المنشورة لجمعية العمل التشاركي.', 'en' => 'Published news and activities from PCDR.'],
        ],
        'albums' => [
            'page' => 'media_albums',
            'component' => 'Public/Media/Albums',
            'method' => 'mediaAlbums',
            'title' => ['ar' => 'ألبوم الصور', 'en' => 'Photo album'],
            'description' => ['ar' => 'ألبومات الصور المنشورة لجمعية العمل التشاركي.', 'en' => 'Published photo albums from PCDR.'],
        ],
        'stories' => [
            'page' => 'media_stories',
            'component' => 'Public/Media/Stories',
            'method' => 'mediaStories',
            'title' => ['ar' => 'قصص نجاح', 'en' => 'Success stories'],
            'description' => ['ar' => 'قصص النجاح المنشورة عن عمل جمعية العمل التشاركي.', 'en' => 'Published success stories from the work of PCDR.'],
        ],
    ];

    public function news(string $locale, PublicContentService $content): Response
    {
        return $this->render($locale, 'news', $content);
    }

    public function albums(string $locale, PublicContentService $content): Response
    {
        return $this->render($locale, 'albums', $content);
    }

    public function stories(string $locale, PublicContentService $content): Response
    {
        return $this->render($locale, 'stories', $content);
    }

    private function render(string $locale, string $child, PublicContentService $content): Response
    {
        abort_unless(isset(self::PAGES[$child]), 404);

        /** @var array{page: string, component: string, method: string, title: array{ar: string, en: string}, description: array{ar: string, en: string}} $definition */
        $definition = self::PAGES[$child];
        $this->assertPageVisible($definition['page']);

        $title = $definition['title'][$locale] ?? $definition['title']['en'];
        $description = $definition['description'][$locale] ?? $definition['description']['en'];
        $method = $definition['method'];

        return Inertia::render($definition['component'], [
            'content' => $content->{$method}($locale),
            'meta' => $this->pageSeo(
                $locale,
                $definition['page'],
                $title,
                $description,
                path: "{$locale}/media/{$child}",
            ),
        ]);
    }
}
