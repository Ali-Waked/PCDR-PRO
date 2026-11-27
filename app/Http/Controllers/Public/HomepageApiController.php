<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\HomepageResource;
use App\Models\WebsitePage;
use App\Services\HomepageService;
use App\Services\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HomepageApiController extends Controller
{
    public function __invoke(Request $request, HomepageService $homepage, SiteSettings $settings): JsonResponse
    {
        abort_unless(WebsitePage::isVisible('home'), 404);

        $validated = $request->validate([
            'locale' => ['sometimes', 'string', 'in:'.implode(',', config('website.locales', ['ar', 'en']))],
        ]);

        $locale = (string) ($validated['locale'] ?? config('website.default_locale', 'ar'));
        $identity = $settings->publicIdentity($locale);

        $payload = [
            'locale' => $locale,
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'organization' => [
                'name' => $identity['name'],
                'tagline' => $identity['slogan'],
                'logo' => $identity['logo'] ? url($identity['logo']) : null,
            ],
            'sections' => $homepage->sections($locale),
        ];

        return response()->json([
            'data' => HomepageResource::make($payload)->resolve($request),
        ])->header('Cache-Control', 'public, max-age=60');
    }
}
