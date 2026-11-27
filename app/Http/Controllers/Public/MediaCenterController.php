<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Services\PublicContentService;
use Inertia\Inertia;
use Inertia\Response;

final class MediaCenterController extends PublicController
{
    public function index(string $locale, PublicContentService $content): Response
    {
        $copy = $locale === 'ar'
            ? [
                'title' => 'المركز الإعلامي',
                'description' => 'أخبار الجمعية وأنشطتها وألبوماتها وقصص نجاحها.',
            ]
            : [
                'title' => 'Media centre',
                'description' => 'Published news, activities, photo albums and success stories from PCDR.',
            ];

        return Inertia::render('Public/MediaCenter', [
            'media' => $content->mediaCenter($locale),
            'meta' => $this->seo(
                $locale,
                $copy['title'],
                $copy['description'],
                path: "{$locale}/media",
            ),
        ]);
    }
}
