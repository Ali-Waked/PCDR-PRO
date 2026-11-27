<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Services\PublicContentService;
use Inertia\Inertia;
use Inertia\Response;

final class AboutController extends PublicController
{
    public function index(string $locale, PublicContentService $content): Response
    {
        $this->assertPageVisible('about');

        $copy = $locale === 'ar'
            ? [
                'title' => 'من نحن',
                'description' => 'رؤية جمعية العمل التشاركي ورسالتها وأهدافها ومحاورها الاستراتيجية ومجلس إدارتها.',
            ]
            : [
                'title' => 'About us',
                'description' => 'The vision, mission, objectives, strategic pillars and board of PCDR.',
            ];

        return Inertia::render('Public/About', [
            'about' => $content->about($locale),
            'meta' => $this->pageSeo(
                $locale,
                'about',
                $copy['title'],
                $copy['description'],
                path: "{$locale}/about",
            ),
        ]);
    }
}
