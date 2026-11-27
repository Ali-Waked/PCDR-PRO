<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Services\HomepageService;
use Inertia\Inertia;
use Inertia\Response;

final class HomeController extends PublicController
{
    public function __invoke(string $locale, HomepageService $homepage): Response
    {
        $this->assertPageVisible('home');

        return Inertia::render('Public/Home', [
            'sections' => $homepage->sections($locale),
            'meta' => $this->pageSeo(
                $locale,
                'home',
                (string) config("website.slogan.{$locale}"),
                (string) config("website.description.{$locale}", ''),
                path: "{$locale}",
            ),
        ]);
    }
}
