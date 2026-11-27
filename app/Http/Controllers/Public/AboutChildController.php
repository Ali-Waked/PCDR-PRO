<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Services\PublicContentService;
use Inertia\Inertia;
use Inertia\Response;

final class AboutChildController extends PublicController
{
    /**
     * Independent About pages. Each entry has its own predefined visibility
     * key, localized SEO copy and Inertia component; none is a hash view.
     *
     * @var array<string, array{page: string, component: string, method: string, title: array{ar: string, en: string}, description: array{ar: string, en: string}}>
     */
    private const PAGES = [
        'vision' => [
            'page' => 'about_vision',
            'component' => 'Public/About/Vision',
            'method' => 'aboutVision',
            'title' => ['ar' => 'رؤيتنا', 'en' => 'Our vision'],
            'description' => ['ar' => 'رؤية جمعية العمل التشاركي ورسالتها كما تنشرها المصادر المعتمدة.', 'en' => 'The published vision and mission of PCDR.'],
        ],
        'objectives' => [
            'page' => 'about_objectives',
            'component' => 'Public/About/Objectives',
            'method' => 'aboutObjectives',
            'title' => ['ar' => 'أهدافنا الاستراتيجية', 'en' => 'Our strategic objectives'],
            'description' => ['ar' => 'الأهداف والمحاور الاستراتيجية المعتمدة لعمل الجمعية.', 'en' => 'The published strategic objectives and focus areas of PCDR.'],
        ],
        'achievements' => [
            'page' => 'about_achievements',
            'component' => 'Public/About/Achievements',
            'method' => 'aboutAchievements',
            'title' => ['ar' => 'إنجازاتنا بالأرقام', 'en' => 'Our achievements in numbers'],
            'description' => ['ar' => 'إنجازات الجمعية ومحاور العمل كما تنشرها المصادر المعتمدة.', 'en' => 'Published achievements and focus areas of PCDR.'],
        ],
        'board' => [
            'page' => 'about_board',
            'component' => 'Public/About/Board',
            'method' => 'aboutBoard',
            'title' => ['ar' => 'مجلس الإدارة', 'en' => 'Board of directors'],
            'description' => ['ar' => 'أعضاء مجلس إدارة جمعية العمل التشاركي وفق المحتوى المنشور.', 'en' => 'The published members of the PCDR board of directors.'],
        ],
    ];

    public function vision(string $locale, PublicContentService $content): Response
    {
        return $this->render($locale, 'vision', $content);
    }

    public function objectives(string $locale, PublicContentService $content): Response
    {
        return $this->render($locale, 'objectives', $content);
    }

    public function achievements(string $locale, PublicContentService $content): Response
    {
        return $this->render($locale, 'achievements', $content);
    }

    public function board(string $locale, PublicContentService $content): Response
    {
        return $this->render($locale, 'board', $content);
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
                path: "{$locale}/about/{$child}",
            ),
        ]);
    }
}
