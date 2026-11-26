<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AdminNavigationService;
use App\Services\CmsAccess;
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
            'site' => [
                'name' => $siteSettings->value('organization.name', config("website.name.{$locale}"), $locale),
                'shortName' => $siteSettings->value('organization.short_name', config('website.short_name.en'), $locale),
                'logo' => $siteSettings->value('organization.logo', null, $locale),
                'region' => config("website.region.{$locale}"),
                'slogan' => config("website.slogan.{$locale}"),
                'footerCopyright' => $siteSettings->value('footer.copyright', null, $locale),
                'footerLegalNote' => $siteSettings->value('footer.legal_note', null, $locale),
                'contact' => $siteSettings->contactDetails($locale),
                'social' => $siteSettings->socialLinks($locale),
            ],
            'navigation' => app(NavigationService::class)->all($locale),
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
