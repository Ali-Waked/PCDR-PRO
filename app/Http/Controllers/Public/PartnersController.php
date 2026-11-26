<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Services\PartnerDirectory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PartnersController extends PublicController
{
    public function __construct(private PartnerDirectory $directory) {}

    public function __invoke(string $locale, Request $request): Response
    {
        $category = trim($request->string('category')->toString());
        $category = mb_strcut($category, 0, 120);

        $title = (string) __('website.ui.partner_directory.seo_title', [], $locale);
        $description = (string) __('website.ui.partner_directory.seo_description', [], $locale);

        return Inertia::render('Public/Partners', [
            'directory' => $this->directory->overview($locale, $category !== '' ? $category : null),
            'meta' => $this->seo(
                $locale,
                $title,
                $description,
                path: "{$locale}/partners",
                overrides: [
                    'title' => $title,
                    'description' => $description,
                    'og' => ['title' => $title, 'description' => $description],
                    'twitter' => ['title' => $title, 'description' => $description],
                ],
            ),
        ]);
    }
}
