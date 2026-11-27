<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Services\PublicContentService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProgramsController extends PublicController
{
    public function index(string $locale, Request $request, PublicContentService $content): Response
    {
        $this->assertPageVisible('areas_of_work');

        $area = trim($request->string('area')->toString());
        $area = mb_strcut($area, 0, 120);

        $copy = $locale === 'ar'
            ? [
                'title' => 'برامجنا ومشاريعنا',
                'description' => 'برامج جمعية العمل التشاركي ومشاريعها ضمن المحاور الاستراتيجية المعلنة.',
            ]
            : [
                'title' => 'Programmes and projects',
                'description' => 'Published PCDR programmes and projects organised by strategic area.',
            ];

        return Inertia::render('Public/Programs', [
            'programs' => $content->programs($locale, $area !== '' ? $area : null),
            'meta' => $this->pageSeo(
                $locale,
                'areas_of_work',
                $copy['title'],
                $copy['description'],
                path: "{$locale}/programs",
            ),
        ]);
    }
}
