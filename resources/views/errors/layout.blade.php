@php
    $error ??= \App\Support\ErrorPage::context(request(), 404);
    $locale = $error['locale'];
    $direction = $error['direction'];
    try {
        $siteIdentity = app(\App\Services\SiteSettings::class)->cachedPublicIdentity($locale);
    } catch (\Throwable) {
        $siteIdentity = null;
    }

    if (! is_array($siteIdentity)) {
        $siteIdentity = [
            'name' => config('website.name.'.$locale),
            'shortName' => config('website.short_name.'.$locale, 'PCDR'),
            'logo' => null,
            'region' => config('website.region.'.$locale),
        ];
    }
    $organization = (string) ($siteIdentity['name'] ?? '');
    $shortName = (string) ($siteIdentity['shortName'] ?? 'PCDR');
    $logo = (string) (($siteIdentity['logo'] ?? null) ?: asset('assets/pcdr-homepage/logo/pcdr-logo.png'));
    $region = (string) ($siteIdentity['region'] ?? '');
    $homeUrl = \App\Support\ErrorPage::homeUrl($locale);
    $homeLabel = $error['homeLabel'];
    $localeLabel = $error['localeLabel'];
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $direction }}" data-locale="{{ $locale }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="color-scheme" content="light">
        <meta name="robots" content="noindex">
        <meta name="theme-color" content="#25334B">
        <link rel="icon" href="/favicon.ico">
        <title>@yield('page-title')</title>
        <style>
            .pcdr-error {
                --pcdr-error-navy-900: #25334b;
                --pcdr-error-navy-800: #30415e;
                --pcdr-error-navy: #3e5172;
                --pcdr-error-navy-100: #e9edf2;
                --pcdr-error-teal: #71bcb7;
                --pcdr-error-teal-700: #397f7b;
                --pcdr-error-teal-100: #eaf5f4;
                --pcdr-error-sand: #dac3a3;
                --pcdr-error-sand-100: #f7f2ea;
                --pcdr-error-white: #ffffff;
                --pcdr-error-muted: #4b5668;
                --pcdr-error-line: #d9dfe7;
                --pcdr-error-gutter: clamp(1.25rem, 5vw, 4rem);
                --pcdr-error-max: 1240px;
                --pcdr-error-font: 'IBM Plex Sans Arabic', 'Inter', 'Tajawal', 'Cairo', 'Noto Sans Arabic', 'Segoe UI', system-ui, sans-serif;

                min-height: 100vh;
                min-height: 100svh;
                display: flex;
                flex-direction: column;
                background: var(--pcdr-error-sand-100);
                color: var(--pcdr-error-navy-900);
                font-family: var(--pcdr-error-font);
                font-size: 1rem;
                line-height: 1.7;
                -webkit-font-smoothing: antialiased;
            }

            .pcdr-error *,
            .pcdr-error *::before,
            .pcdr-error *::after {
                box-sizing: border-box;
            }

            .pcdr-error__skip {
                position: absolute;
                inset-block-start: -100px;
                inset-inline-start: var(--pcdr-error-gutter);
                z-index: 10;
                padding: 0.65rem 1rem;
                border-radius: 0 0 8px 8px;
                background: var(--pcdr-error-navy-900);
                color: var(--pcdr-error-white);
                font-weight: 600;
                text-decoration: none;
            }

            .pcdr-error__skip:focus {
                inset-block-start: 0;
            }

            .pcdr-error__header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                padding: clamp(1.25rem, 4vw, 2.5rem) var(--pcdr-error-gutter);
            }

            .pcdr-error__brand {
                display: inline-flex;
                align-items: center;
                gap: 0.85rem;
                color: inherit;
                text-decoration: none;
            }

            .pcdr-error__brand img {
                display: block;
                inline-size: clamp(2.75rem, 5vw, 3.5rem);
                block-size: auto;
            }

            .pcdr-error__brand-text {
                display: grid;
                gap: 0.1rem;
            }

            .pcdr-error__brand-text strong {
                font-size: 1rem;
                letter-spacing: 0.14em;
                line-height: 1.2;
            }

            .pcdr-error__brand-text small {
                max-width: 26ch;
                color: var(--pcdr-error-muted);
                font-size: 0.78rem;
                line-height: 1.4;
            }

            .pcdr-error__locale {
                flex: none;
                padding: 0.35rem 0.85rem;
                border: 1px solid var(--pcdr-error-line);
                border-radius: 999px;
                background: var(--pcdr-error-white);
                color: var(--pcdr-error-navy);
                font-size: 0.8rem;
                font-weight: 600;
            }

            .pcdr-error__main {
                flex: 1;
                inline-size: min(100%, var(--pcdr-error-max));
                margin-inline: auto;
                padding: clamp(2rem, 6vw, 5rem) var(--pcdr-error-gutter) clamp(3rem, 8vw, 6rem);
            }

            .pcdr-error__grid {
                display: grid;
                grid-template-columns: minmax(0, 1fr);
                align-items: center;
                gap: clamp(2.5rem, 6vw, 5rem);
            }

            @media (min-width: 900px) {
                .pcdr-error__grid {
                    grid-template-columns: minmax(0, 1.05fr) minmax(320px, 0.95fr);
                }
            }

            .pcdr-error__content {
                max-width: 42rem;
            }

            .pcdr-error__eyebrow {
                display: inline-flex;
                align-items: center;
                gap: 0.65rem;
                margin: 0 0 1.25rem;
                color: var(--pcdr-error-teal-700);
                font-size: 0.8rem;
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            .pcdr-error__eyebrow::before {
                content: '';
                inline-size: 1.75rem;
                block-size: 2px;
                background: var(--pcdr-error-sand);
            }

            [dir='rtl'] .pcdr-error__eyebrow {
                letter-spacing: 0;
                text-transform: none;
            }

            [dir='rtl'] .pcdr-error .directional-flip {
                transform: scaleX(-1);
            }

            .pcdr-error__title {
                margin: 0;
                color: var(--pcdr-error-navy-900);
                font-size: clamp(2rem, 5vw, 3.75rem);
                font-weight: 700;
                letter-spacing: -0.02em;
                line-height: 1.22;
                text-wrap: balance;
            }

            [dir='rtl'] .pcdr-error__title {
                letter-spacing: 0;
            }

            .pcdr-error__description {
                max-width: 38rem;
                margin: 1.5rem 0 0;
                color: var(--pcdr-error-muted);
                font-size: clamp(1rem, 1.6vw, 1.15rem);
            }

            .pcdr-error__sr {
                position: absolute;
                inline-size: 1px;
                block-size: 1px;
                padding: 0;
                margin: -1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
                border: 0;
            }

            .pcdr-error__actions {
                display: flex;
                flex-wrap: wrap;
                gap: 0.85rem;
                margin-block-start: 2rem;
            }

            .pcdr-error__button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.65rem;
                min-block-size: 3rem;
                padding: 0.75rem 1.25rem;
                border: 1px solid transparent;
                border-radius: 999px;
                font: inherit;
                font-size: 0.95rem;
                font-weight: 600;
                line-height: 1.4;
                text-decoration: none;
                cursor: pointer;
                transition: background-color 200ms ease, border-color 200ms ease, transform 200ms ease;
            }

            .pcdr-error__button svg {
                inline-size: 1.1rem;
                block-size: 1.1rem;
                flex: none;
            }

            .pcdr-error__button--primary {
                background: var(--pcdr-error-navy-900);
                color: var(--pcdr-error-white);
            }

            .pcdr-error__button--primary:hover {
                background: var(--pcdr-error-navy-800);
                transform: translateY(-1px);
            }

            .pcdr-error__button--secondary {
                border-color: var(--pcdr-error-navy-100);
                background: transparent;
                color: var(--pcdr-error-navy-900);
            }

            .pcdr-error__button--secondary:hover {
                border-color: var(--pcdr-error-teal-700);
                background: var(--pcdr-error-white);
            }

            .pcdr-error__button[hidden] {
                display: none;
            }

            .pcdr-error__brand:focus-visible,
            .pcdr-error__skip:focus-visible,
            .pcdr-error__button:focus-visible {
                outline: 3px solid var(--pcdr-error-teal-700);
                outline-offset: 3px;
            }

            .pcdr-error__visual {
                position: relative;
                display: grid;
                place-items: center;
                min-block-size: 280px;
            }

            .pcdr-error__visual::before {
                content: '';
                position: absolute;
                inset-block-start: 4%;
                inset-inline-end: -4%;
                inline-size: min(28rem, 72vw);
                aspect-ratio: 1;
                border-radius: 50%;
                background: var(--pcdr-error-teal-100);
                opacity: 0.65;
            }

            .pcdr-error__art {
                position: relative;
                inline-size: 100%;
                max-inline-size: 32rem;
            }

            .pcdr-error__art svg {
                display: block;
                inline-size: 100%;
                block-size: auto;
            }

            .pcdr-error__code {
                position: absolute;
                inset-block-start: 33%;
                inset-inline-start: 13%;
                color: var(--pcdr-error-navy-900);
                font-size: clamp(5rem, 13vw, 9rem);
                font-weight: 700;
                letter-spacing: -0.08em;
                line-height: 1;
                opacity: 0.12;
                pointer-events: none;
            }

            .pcdr-error__footer {
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                gap: 0.35rem 1.5rem;
                padding: 1.5rem var(--pcdr-error-gutter);
                border-block-start: 1px solid var(--pcdr-error-line);
                color: var(--pcdr-error-muted);
                font-size: 0.82rem;
            }

            .pcdr-error__footer p {
                margin: 0;
            }

            .pcdr-error__header-meta {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                flex: none;
            }

            .pcdr-error__admin {
                display: inline-flex;
                align-items: center;
                padding: 0.35rem 0.85rem;
                border: 1px solid var(--pcdr-error-teal-100);
                border-radius: 999px;
                background: var(--pcdr-error-teal-100);
                color: var(--pcdr-error-teal-700);
                font-size: 0.78rem;
                font-weight: 700;
            }

            .pcdr-error__suggestions {
                margin-block-start: 2.5rem;
                padding-block-start: 1.5rem;
                border-block-start: 1px solid var(--pcdr-error-line);
            }

            .pcdr-error__suggestions h2 {
                margin: 0 0 0.85rem;
                color: var(--pcdr-error-navy-900);
                font-size: 0.9rem;
                font-weight: 700;
            }

            .pcdr-error__suggestions-list {
                display: flex;
                flex-wrap: wrap;
                gap: 0.4rem 1.25rem;
                margin: 0;
                padding: 0;
                list-style: none;
            }

            .pcdr-error__suggestions-list a {
                color: var(--pcdr-error-teal-700);
                font-size: 0.92rem;
                font-weight: 600;
                text-decoration-color: var(--pcdr-error-sand);
                text-underline-offset: 0.25em;
            }

            @media (max-width: 600px) {
                .pcdr-error__header {
                    align-items: flex-start;
                }

                .pcdr-error__brand-text small {
                    display: none;
                }

                .pcdr-error__actions {
                    flex-direction: column;
                    align-items: stretch;
                }

                .pcdr-error__button {
                    inline-size: 100%;
                }

                .pcdr-error__visual {
                    min-block-size: 220px;
                }

                .pcdr-error__code {
                    inset-block-start: 29%;
                    inset-inline-start: 10%;
                }
            }

            @media (prefers-reduced-motion: reduce) {
                .pcdr-error *,
                .pcdr-error *::before,
                .pcdr-error *::after {
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    scroll-behavior: auto !important;
                    transition-duration: 0.01ms !important;
                }
            }
        </style>
    </head>
    <body>
        <div class="pcdr-error" data-shell="{{ $error['admin'] ? 'admin' : 'public' }}">
            <a class="pcdr-error__skip" href="#pcdr-error-main">@yield('skip-label')</a>

            <header class="pcdr-error__header">
                <a class="pcdr-error__brand" href="{{ $homeUrl }}" aria-label="{{ $organization }} — {{ $homeLabel }}">
                    <img
                        src="{{ $logo }}"
                        alt=""
                        width="48"
                        height="48"
                        decoding="async"
                    >
                    <span class="pcdr-error__brand-text">
                        <strong>{{ $shortName }}</strong>
                        <small>{{ $organization }}</small>
                    </span>
                </a>
                <div class="pcdr-error__header-meta">
                    @if ($error['admin'])
                        <span class="pcdr-error__admin">{{ $error['adminLabel'] }}</span>
                    @endif
                    <span class="pcdr-error__locale">{{ $localeLabel }}</span>
                </div>
            </header>

            <main class="pcdr-error__main" id="pcdr-error-main">
                <div class="pcdr-error__grid">
                    <section class="pcdr-error__content" aria-labelledby="pcdr-error-title">
                        <p class="pcdr-error__eyebrow">@yield('eyebrow')</p>
                        <h1 class="pcdr-error__title" id="pcdr-error-title">@yield('title')</h1>
                        <p class="pcdr-error__description">@yield('description')</p>
                        <p class="pcdr-error__sr" role="status">@yield('sr-status')</p>

                        <nav class="pcdr-error__actions" aria-label="@yield('actions-label')">
                            @yield('actions')
                        </nav>

                        @hasSection('suggestions')
                            <section class="pcdr-error__suggestions" aria-labelledby="pcdr-error-suggestions">
                                <h2 id="pcdr-error-suggestions">@yield('suggestions-label')</h2>
                                @yield('suggestions')
                            </section>
                        @endif
                    </section>

                    <div class="pcdr-error__visual">
                        <div class="pcdr-error__art" aria-hidden="true">
                            @yield('artwork')
                            <span class="pcdr-error__code">@yield('status')</span>
                        </div>
                    </div>
                </div>
            </main>

            <footer class="pcdr-error__footer">
                <p>{{ $organization }}</p>
                <p>{{ $region }}</p>
            </footer>
        </div>

        <script>
            (function () {
                var backButton = document.querySelector('[data-error-action="back"]');

                if (backButton && (window.history.length <= 1 || ! document.referrer)) {
                    backButton.hidden = true;
                }

                document.querySelectorAll('[data-error-action]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var action = button.getAttribute('data-error-action');

                        if (action === 'back') {
                            window.history.back();
                        }

                        if (action === 'reload') {
                            window.location.reload();
                        }
                    });
                });
            })();
        </script>
    </body>
</html>
