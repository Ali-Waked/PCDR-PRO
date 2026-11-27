<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AdminNavigationService;
use App\Services\CmsAccess;
use App\Services\HomepageService;
use App\Services\NavigationService;
use App\Services\RbacService;
use App\Services\SettingsService;
use App\Services\SiteSettings;
use App\Support\Locale;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly RbacService $rbac,
        private readonly SettingsService $settings,
        private readonly AdminNavigationService $adminNavigation,
        private readonly HomepageService $homepage,
    ) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $user = $request->user();
        // The locale middleware chain has already resolved the active locale
        // from the route segment, falling back to user and settings defaults.
        $locale = app()->getLocale();
        $direction = Locale::direction($locale);
        $siteSettings = app(SiteSettings::class);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user?->toAccessArray(),
                'permissions' => $user ? $this->rbac->dashboardPermissionsFor($user)->all() : [],
                'roles' => $user ? $this->rbac->rolesFor($user)->all() : [],
                'scopes' => $user ? $this->rbac->publicScopesFor($user) : [],
                'cmsPermissions' => $user ? app(CmsAccess::class)->permissionsFor($user) : [],
            ],
            'authority' => [
                'is_platform_owner' => $user?->isPlatformOwner() === true,
                'institutional_approval' => [
                    'granted' => false,
                    'reason' => $this->rbac->authorityReason($locale),
                ],
                'denied_permission_patterns' => array_merge(
                    (array) config('governance.authority.permission_prefixes', []),
                    (array) config('governance.authority.permission_suffixes', []),
                ),
            ],
            'adminNavigation' => $user ? $this->adminNavigation->visibleFor($user, $locale) : [],
            'locale' => $locale,
            'direction' => $direction,
            'theme' => $user?->theme ?: User::THEME_SYSTEM,
            'availableLocales' => ['ar', 'en'],
            'translations' => [
                'ui' => __('ui'),
            ],
            'site' => $siteSettings->publicIdentity($locale),
            'navigation' => app(NavigationService::class)->all($locale),
            // Already filtered for section status, item publication and page
            // visibility by HomepageService. Inner pages reuse this instead of
            // bypassing the CMS with an unconditional footer render.
            'footer' => fn () => array_values(array_filter(
                $this->homepage->sections($locale),
                static fn (array $section): bool => ($section['key'] ?? null) === 'footer',
            )),
            'ui' => fn () => __('website.ui', [], $locale),
            'nav' => fn () => __('website.navigation', [], $locale),
            'phase5' => fn () => __('phase5', [], $locale),
            'publicationCategories' => fn () => __('website.publication.categories', [], $locale),
            'flash' => [
                'success' => fn () => $request->session()->get('success') ?? $request->session()->get('flash.success'),
                'error' => fn () => $request->session()->get('error') ?? $request->session()->get('flash.error'),
                'warning' => fn () => $request->session()->get('flash.warning'),
                'workflow' => fn () => $request->session()->get('flash.workflow'),
            ],
            'settings' => [
                'public' => $this->settings->publicSettings($locale),
            ],
        ];
    }
}
