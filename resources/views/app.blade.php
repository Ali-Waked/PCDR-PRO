@php
    $seoLocale = \App\Support\Locale::sanitize(request()->route('locale')) ?: app()->getLocale();
    $siteIdentity = app(\App\Services\SiteSettings::class)->publicIdentity($seoLocale);
    $seoPath = ltrim(preg_replace('#^/(ar|en)(?=/|$)#', '', '/'.ltrim(request()->path(), '/')) ?? '', '/');
    $seoSuffix = $seoPath ? '/'.$seoPath : '';
    $isHome = $seoPath === '';
    $seoTitle = $isHome
        ? $siteIdentity['shortName'].' | '.$siteIdentity['name']
        : $siteIdentity['name'].' | '.$siteIdentity['shortName'];
    $seoDescription = (string) $siteIdentity['description'];

    // Build localized links from the matched route and its parameters so the
    // same page is addressed in every locale, including model-bound routes.
    $seoRoute = request()->route();
    $seoRouteName = $seoRoute?->getName();
    $seoRouteParameters = $seoRoute?->parameters() ?? [];
    $seoRouteName = $seoRouteName === 'root' ? 'public.home' : $seoRouteName;
    $supportedLocales = (array) config('locales.supported', config('website.locales', ['ar', 'en']));
    $alternateUrls = [];

    foreach ($supportedLocales as $alternateLocale) {
        $alternateLocale = strtolower((string) $alternateLocale);
        $generated = null;

        if ($seoRouteName && \Illuminate\Support\Facades\Route::has($seoRouteName)) {
            try {
                $generated = route($seoRouteName, array_replace($seoRouteParameters, ['locale' => $alternateLocale]), false);
            } catch (\Throwable) {
                $generated = null;
            }
        }

        $alternateUrls[$alternateLocale] = url($generated ?? '/'.$alternateLocale.$seoSuffix);
    }

    $canonicalUrl = $alternateUrls[$seoLocale] ?? reset($alternateUrls);
    $defaultLocale = strtolower((string) config('locales.default', config('website.default_locale', 'ar')));

    if (! array_key_exists($defaultLocale, $alternateUrls)) {
        $defaultLocale = array_key_first($alternateUrls) ?? $seoLocale;
    }
@endphp
@php
    $iconV = trim((string) @file_get_contents(public_path('icons-version.txt'))) ?: '1';
    $bootLoginIntro = request()->routeIs('login.show');
    $bootIntroLogo = $siteIdentity['logo'] ?: '/assets/pcdr-homepage/logo/pcdr-logo-animated.svg';
    $cssSafeLogo = str_replace(['\'', '\\', "\n", "\r"], ['%27', '%5C', '', ''], (string) $bootIntroLogo);
    $bootIntroBackground = "url('{$cssSafeLogo}'), url('/assets/pcdr-homepage/logo/pcdr-logo.png')";
    $fontStylesheet = 'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Locale::direction(app()->getLocale()) }}" data-locale="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, viewport-fit=cover">
        <meta name="color-scheme" content="light">
        <meta name="theme-color" content="#3E5172">
        <link rel="icon" href="/favicon.ico?v={{ $iconV }}" sizes="48x48">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png?v={{ $iconV }}">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png?v={{ $iconV }}">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v={{ $iconV }}">
        <link rel="manifest" href="/site.webmanifest?v={{ $iconV }}">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="preload" as="style" href="{{ $fontStylesheet }}">
        <link rel="stylesheet" href="{{ $fontStylesheet }}" media="print" onload="this.media='all'">
        <noscript><link rel="stylesheet" href="{{ $fontStylesheet }}"></noscript>
        @if ($bootLoginIntro)
            <link rel="preload" as="image" href="{{ $bootIntroLogo }}" fetchpriority="high">
            <style>
                .pcdr-boot-intro{position:fixed;inset:0;z-index:1599;display:grid;place-items:center;background:#3E5172;overflow:hidden}
                .pcdr-boot-intro__disc{box-sizing:border-box;display:grid;place-items:center;inline-size:clamp(156px,30vmin,300px);block-size:clamp(156px,30vmin,300px);padding:10px;border-radius:50%;background:#fff;box-shadow:0 24px 60px rgba(24,34,50,.28),0 0 0 1px rgba(218,195,163,.62)}
                .pcdr-boot-intro__mark{inline-size:100%;block-size:100%;background-image:{{ $bootIntroBackground }};background-position:center;background-repeat:no-repeat;background-size:contain;animation:pcdr-boot-mark 900ms cubic-bezier(.16,1,.3,1) 180ms both}
                @keyframes pcdr-boot-mark{from{opacity:0;transform:scale(.965)}to{opacity:1;transform:scale(1)}}
                @media(prefers-reduced-motion:reduce){.pcdr-boot-intro__mark{animation:none}}
            </style>
        @endif
        <title inertia>{{ $seoTitle }}</title>
        <meta name="description" inertia content="{{ $seoDescription }}">
        <link rel="canonical" inertia href="{{ $canonicalUrl }}">
        @foreach ($alternateUrls as $alternateLocale => $alternateUrl)
            <link rel="alternate" inertia hreflang="{{ $alternateLocale }}" href="{{ $alternateUrl }}">
        @endforeach
        <link rel="alternate" inertia hreflang="x-default" href="{{ $alternateUrls[$defaultLocale] }}">
        <meta property="og:type" inertia content="website">
        <meta property="og:site_name" inertia content="{{ $siteIdentity['seoTitle'] }}">
        <meta property="og:locale" inertia content="{{ $seoLocale }}">
        <meta property="og:title" inertia content="{{ $seoTitle }}">
        <meta property="og:description" inertia content="{{ $seoDescription }}">
        @if ($siteIdentity['ogImage'])
            <meta property="og:image" inertia content="{{ $siteIdentity['ogImage'] }}">
        @endif
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script type="application/ld+json">
            {!! json_encode([
                '@' . 'context' => 'https://schema.org',
                '@' . 'type' => 'Organization',
                'name' => $siteIdentity['name'],
                'alternateName' => $siteIdentity['shortName'],
                'url' => $canonicalUrl,
                'description' => $seoDescription,
                'inLanguage' => config('website.locales'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}
        </script>
        @inertiaHead
    </head>
    <body class="min-h-screen bg-surface text-ink antialiased">
        @if ($bootLoginIntro)
            <div id="pcdr-boot-intro" class="pcdr-boot-intro" data-pcdr-boot-intro aria-hidden="true">
                <span class="pcdr-boot-intro__disc">
                    <span class="pcdr-boot-intro__mark"></span>
                </span>
            </div>
        @endif
        @inertia
        <x-devtools />
    </body>
</html>
