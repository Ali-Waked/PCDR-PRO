@php
    $seoLocale = \App\Support\Locale::sanitize(request()->route('locale'));
    $seoPath = ltrim(preg_replace('#^/(ar|en)(?=/|$)#', '', '/'.ltrim(request()->path(), '/')) ?? '', '/');
    $seoSuffix = $seoPath ? '/'.$seoPath : '';
    $isHome = $seoPath === '';
    $seoTitle = $isHome
        ? ($seoLocale === 'ar'
            ? 'PCDR | جمعية العمل التشاركي في الأزمات والكوارث'
            : 'PCDR | Participatory Action in Crises and Disasters')
        : ($seoLocale === 'ar'
            ? config('website.name.ar').' | PCDR'
            : config('website.name.en').' | PCDR');
    $seoDescription = $isHome && $seoLocale === 'ar'
        ? 'من الاستجابة الإنسانية إلى التعافي والتنمية المستدامة — PCDR، قطاع غزة.'
        : (string) config('website.description.'.$seoLocale);

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
        <link
            href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap"
            rel="stylesheet"
        >
        <title inertia>{{ $seoTitle }}</title>
        <meta name="description" inertia content="{{ $seoDescription }}">
        <link rel="canonical" inertia href="{{ $canonicalUrl }}">
        @foreach ($alternateUrls as $alternateLocale => $alternateUrl)
            <link rel="alternate" inertia hreflang="{{ $alternateLocale }}" href="{{ $alternateUrl }}">
        @endforeach
        <link rel="alternate" inertia hreflang="x-default" href="{{ $alternateUrls[$defaultLocale] }}">
        <meta property="og:type" inertia content="website">
        <meta property="og:site_name" inertia content="PCDR">
        <meta property="og:locale" inertia content="{{ $seoLocale }}">
        <meta property="og:title" inertia content="{{ $seoTitle }}">
        <meta property="og:description" inertia content="{{ $seoDescription }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script type="application/ld+json">
            {!! json_encode([
                '@' . 'context' => 'https://schema.org',
                '@' . 'type' => 'Organization',
                'name' => config('website.name.'.app()->getLocale()),
                'alternateName' => 'PCDR',
                'url' => url('/'.app()->getLocale()),
                'description' => config('website.description.'.app()->getLocale()),
                'inLanguage' => config('website.locales'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}
        </script>
        @inertiaHead
    </head>
    <body class="min-h-screen bg-surface text-ink antialiased">
        @inertia
        <x-devtools />
    </body>
</html>
