<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\HomepageResource;
use App\Services\HomepageService;
use App\Services\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HomepageApiController extends Controller
{
    public function __invoke(Request $request, HomepageService $homepage, SiteSettings $settings): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['sometimes', 'string', 'in:'.implode(',', config('website.locales', ['ar', 'en']))],
        ]);

        $locale = (string) ($validated['locale'] ?? config('website.default_locale', 'ar'));
        $logo = $settings->value('organization.logo', null, $locale);

        $payload = [
            'locale' => $locale,
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'organization' => [
                'name' => $settings->value(
                    'organization.name',
                    (string) config("website.name.{$locale}", 'PCDR'),
                    $locale,
                ),
                'tagline' => (string) config("website.slogan.{$locale}", ''),
                'logo' => $logo ? url($logo) : null,
            ],
            'sections' => $homepage->sections($locale),
        ];

        return response()->json([
            'data' => HomepageResource::make($payload)->resolve($request),
        ])->header('Cache-Control', 'public, max-age=60');
    }
}
