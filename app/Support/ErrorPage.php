<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\LocaleResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Throwable;

final class ErrorPage
{
    /**
     * @return array{
     *     locale: string,
     *     direction: string,
     *     status: int,
     *     pageTitle: string,
     *     eyebrow: string,
     *     title: string,
     *     description: string,
     *     srStatus: string,
     *     actionsLabel: string,
     *     skipLabel: string,
     *     suggestionsLabel: string,
     *     adminLabel: string,
     *     localeLabel: string,
     *     admin: bool,
     *     homeLabel: string,
     *     suggestions: list<array{label: string, href: string}>,
     *     actions: list<array{label: string, href: ?string, type: string, variant: string, icon: string}>
     * }
     */
    public static function context(Request $request, int $status): array
    {
        $locale = self::locale($request);

        return [
            'locale' => $locale,
            'direction' => Locale::direction($locale),
            'status' => $status,
            ...self::copy($status, $locale),
            'homeLabel' => self::actionLabel('home', $locale),
            'actions' => self::actions($status, $locale, self::homeUrl($locale)),
            'suggestions' => $status === 404 ? self::suggestions($locale) : [],
            'admin' => self::isAdminPath($request),
            'localeLabel' => self::translate('errors.common.locale_name', $locale),
        ];
    }

    public static function locale(Request $request): string
    {
        $candidates = [
            $request->route('locale'),
            $request->segment(1),
            app()->getLocale(),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && Locale::isValid($candidate)) {
                return $candidate;
            }
        }

        $configured = (string) config('website.default_locale', 'ar');

        return Locale::isValid($configured) ? $configured : 'ar';
    }

    public static function homeUrl(string $locale): string
    {
        return self::localizedUrl('public.home', $locale) ?? url('/'.$locale);
    }

    public static function actionLabel(string $action, string $locale): string
    {
        $keys = [
            'home' => 'errors.common.home',
            'back' => 'errors.common.back',
            'reload' => 'errors.common.reload',
            'contact' => 'errors.common.contact',
        ];

        $key = $keys[$action] ?? $keys['home'];

        return self::translate($key, $locale);
    }

    /**
     * @return array{
     *     pageTitle: string,
     *     eyebrow: string,
     *     title: string,
     *     description: string,
     *     srStatus: string,
     *     actionsLabel: string,
     *     skipLabel: string,
     *     suggestionsLabel: string,
     *     adminLabel: string
     * }
     */
    private static function copy(int $status, string $locale): array
    {
        $key = match ($status) {
            403, 404 => (string) $status,
            default => '500',
        };

        return [
            'pageTitle' => self::translate("errors.{$key}.page_title", $locale),
            'eyebrow' => self::translate("errors.{$key}.eyebrow", $locale),
            'title' => self::translate("errors.{$key}.title", $locale),
            'description' => self::translate("errors.{$key}.description", $locale),
            'srStatus' => self::translate("errors.{$key}.sr_status", $locale),
            'actionsLabel' => self::translate('errors.common.navigation_options', $locale),
            'skipLabel' => self::translate('errors.common.skip', $locale),
            'suggestionsLabel' => self::translate('errors.common.suggestions', $locale),
            'adminLabel' => self::translate('errors.common.admin_shell', $locale),
        ];
    }

    /**
     * @return list<array{label: string, href: ?string, type: string, variant: string, icon: string}>
     */
    private static function actions(int $status, string $locale, string $homeUrl): array
    {
        $home = [
            'label' => self::actionLabel('home', $locale),
            'href' => $homeUrl,
            'type' => 'link',
            'variant' => 'primary',
            'icon' => 'home',
        ];

        $secondary = match ($status) {
            500 => [
                'label' => self::actionLabel('reload', $locale),
                'href' => null,
                'type' => 'reload',
                'variant' => 'secondary',
                'icon' => 'reload',
            ],
            default => [
                'label' => self::actionLabel('back', $locale),
                'href' => null,
                'type' => 'back',
                'variant' => 'secondary',
                'icon' => 'back',
            ],
        };

        return [$home, $secondary];
    }

    /** @return list<array{label: string, href: string}> */
    private static function suggestions(string $locale): array
    {
        $configured = (array) config('website.not_found_suggestions', []);
        $suggestions = [];

        foreach (array_slice($configured, 0, 5) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $labelKey = (string) ($item['label'] ?? '');
            $routeName = (string) ($item['route'] ?? '');
            $translationKey = 'website.navigation.'.$labelKey;
            $label = self::translate($translationKey, $locale);
            $href = self::localizedUrl($routeName, $locale);

            if ($label === $translationKey || $href === null) {
                continue;
            }

            $suggestions[] = ['label' => $label, 'href' => $href];
        }

        return $suggestions;
    }

    private static function isAdminPath(Request $request): bool
    {
        $segments = array_values(array_filter(
            explode('/', (string) $request->getPathInfo()),
            static fn (string $segment): bool => $segment !== '',
        ));

        $resolver = new LocaleResolver;

        if (isset($segments[0]) && $resolver->normalize($segments[0]) !== null) {
            array_shift($segments);
        }

        $first = strtolower((string) ($segments[0] ?? ''));

        return in_array($first, ['admin', 'cms', 'dashboard', 'operations', 'phase5'], true);
    }

    private static function localizedUrl(string $routeName, string $locale): ?string
    {
        if ($routeName === '' || ! Route::has($routeName)) {
            return null;
        }

        try {
            return route($routeName, ['locale' => $locale]);
        } catch (Throwable) {
            return null;
        }
    }

    private static function translate(string $key, string $locale): string
    {
        $value = __($key, [], $locale);

        return is_string($value) ? $value : $key;
    }
}
