<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class LocalizedUrlFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Locale cookies are excluded from encryption, so send them as the
        // browser would: plain values.
        $this->disableCookieEncryption();
    }

    #[DataProvider('localeProvider')]
    public function test_unprefixed_page_redirects_to_the_expected_locale(string $header, string $expected): void
    {
        $request = $this->noAcceptLanguage();

        if ($header !== '') {
            $request = $request->withHeader('Accept-Language', $header);
        }

        $response = $request->get('/cms/login');

        $response->assertRedirect($expected);
        $this->assertStringContainsString('Accept-Language', (string) $response->headers->get('Vary'));
        $this->assertStringContainsString('Cookie', (string) $response->headers->get('Vary'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $setCookie = (string) $response->headers->get('Set-Cookie');
        $this->assertStringNotContainsString('pcdr_locale=', $setCookie);
        $this->assertStringNotContainsString('locale=', $setCookie);
    }

    /** @return array<string, array{string, string}> */
    public static function localeProvider(): array
    {
        return [
            'no usable header' => ['', '/ar/cms/login'],
            'english' => ['en-US,en;q=0.9', '/en/cms/login'],
            'arabic region variant' => ['ar-PS', '/ar/cms/login'],
            'unsupported only' => ['fr-FR', '/ar/cms/login'],
            'quality values' => ['fr-FR;q=1,en-GB;q=0.8,ar;q=0.6', '/en/cms/login'],
        ];
    }

    public function test_locale_cookie_wins_over_accept_language(): void
    {
        $this->withCookie('pcdr_locale', 'en')
            ->withHeader('Accept-Language', 'ar')
            ->get('/cms/login')
            ->assertRedirect('/en/cms/login');
    }

    public function test_unencrypted_locale_cookie_is_honored(): void
    {
        $this->withUnencryptedCookie('pcdr_locale', 'en')
            ->withHeader('Accept-Language', 'ar')
            ->get('/cms/login')
            ->assertRedirect('/en/cms/login');
    }

    public function test_authenticated_user_locale_wins_over_cookie_and_header(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->withCookie('pcdr_locale', 'ar')
            ->withHeader('Accept-Language', 'ar')
            ->get('/cms/login')
            ->assertRedirect('/en/cms/login');
    }

    public function test_query_string_is_preserved_exactly(): void
    {
        $this->noAcceptLanguage()
            ->get('/cms/login?ref=home&x=1')
            ->assertRedirect('/ar/cms/login?ref=home&x=1');
    }

    #[DataProvider('pathProvider')]
    public function test_case_and_trailing_slash_follow_router_rules(string $path, ?string $expected): void
    {
        $response = $this->noAcceptLanguage()->get($path);

        if ($expected === null) {
            $response->assertNotFound();
            $this->assertNull($response->headers->get('Location'));

            return;
        }

        $response->assertRedirect($expected);
    }

    /** @return array<string, array{string, ?string}> */
    public static function pathProvider(): array
    {
        return [
            'uppercase path' => ['/CMS/LOGIN', null],
            'mixed case path with trailing slash' => ['/Cms/Login/', null],
            'trailing slash' => ['/cms/login/', '/ar/cms/login'],
            'uppercase supported locale' => ['/AR/cms/login', '/ar/cms/login'],
        ];
    }

    public function test_unknown_and_unsupported_paths_render_404_without_redirect(): void
    {
        foreach (['/xyz', '/ar/xyz', '/en/xyz', '/fr/cms/login'] as $path) {
            $response = $this->get($path);
            $response->assertNotFound();
            $this->assertNull($response->headers->get('Location'));
        }
    }

    #[DataProvider('reservedPathProvider')]
    public function test_reserved_paths_are_untouched(string $path, int $status): void
    {
        $response = $this->get($path);

        $response->assertStatus($status);
        $this->assertNull($response->headers->get('Location'));
    }

    /** @return array<string, array{string, int}> */
    public static function reservedPathProvider(): array
    {
        return [
            'favicon' => ['/favicon.ico', 404],
            'manifest' => ['/build/manifest.json', 404],
            'storage' => ['/storage/foo.png', 403],
            'health check' => ['/up', 200],
            'robots' => ['/robots.txt', 200],
            'asset extension' => ['/theme.css', 404],
        ];
    }

    public function test_non_get_methods_are_not_redirected(): void
    {
        $response = $this->withoutMiddleware(VerifyCsrfToken::class)
            ->post('/cms/login');

        $this->assertContains($response->getStatusCode(), [404, 405]);
        $this->assertNull($response->headers->get('Location'));
    }

    public function test_head_request_uses_the_same_redirect(): void
    {
        $this->noAcceptLanguage()
            ->head('/cms/login')
            ->assertRedirect('/ar/cms/login');
    }

    public function test_open_redirect_attempts_never_leave_the_application(): void
    {
        foreach (['//evil.com', '/%5Cevil.com', '/%2F%2Fevil.com', '/cms/login%0d%0aSet-Cookie:x'] as $path) {
            $response = $this->get($path);
            $response->assertNotFound();

            $location = (string) $response->headers->get('Location');
            $this->assertStringNotContainsString('evil.com', $location);
            $this->assertStringNotContainsString('http', $location);
        }
    }

    public function test_inertia_visit_follows_the_redirect_to_the_localized_page(): void
    {
        $redirect = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $this->inertiaVersion(),
            'Accept-Language' => 'en-US',
        ])->get('/cms/login');

        $redirect->assertRedirect('/en/cms/login');

        $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $this->inertiaVersion(),
        ])->get('/en/cms/login')->assertOk();
    }

    public function test_every_localized_get_route_without_parameters_has_a_locale_less_redirect(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => in_array('GET', $route->methods(), true))
            ->map(fn ($route): string => (string) $route->uri())
            ->filter(fn (string $uri): bool => str_starts_with($uri, '{locale}/'))
            ->filter(fn (string $uri): bool => substr_count($uri, '{') === 1)
            ->values();

        $this->assertGreaterThan(20, $routes->count());

        foreach ($routes as $uri) {
            $suffix = $uri === '{locale}' ? '' : substr($uri, strlen('{locale}/'));
            $unprefixed = $suffix === '' ? '/' : '/'.$suffix;

            // Root and legacy auth URLs intentionally keep their own handlers.
            $matched = Route::getRoutes()->match(
                Request::create($unprefixed, 'GET')
            );

            if (! (bool) ($matched->isFallback ?? false)) {
                continue;
            }

            foreach (['ar', 'en'] as $locale) {
                $expected = '/'.$locale.($suffix === '' ? '' : '/'.$suffix);

                $this->withCookie('pcdr_locale', $locale)
                    ->get($unprefixed)
                    ->assertRedirect($expected);
            }
        }
    }

    public function test_only_the_home_public_page_has_an_unprefixed_path(): void
    {
        $paths = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => in_array('GET', $route->methods(), true)
                && str_starts_with((string) $route->getName(), 'public.'))
            ->map(fn ($route): string => (string) $route->uri())
            ->filter(fn (string $uri): bool => str_starts_with($uri, '{locale}'))
            ->filter(fn (string $uri): bool => substr_count($uri, '{') === 1)
            ->map(fn (string $uri): string => $uri === '{locale}' ? '/' : '/'.substr($uri, strlen('{locale}/')))
            ->values();

        $this->assertSame(['/'], $paths->all());

        $this->followToOk('/', 'en')->assertOk();
    }

    public function test_root_and_localized_home_do_not_gain_extra_redirects(): void
    {
        $this->get('/')->assertRedirect(url('/ar'));
        $this->get('/ar')->assertOk();
        $this->get('/en')->assertOk();
    }

    public function test_canonical_and_alternate_tags_are_built_from_the_current_route(): void
    {
        $html = (string) $this->get('/ar')->getContent();

        $this->assertStringContainsString('<link rel="canonical" inertia href="'.url('/ar').'">', $html);
        $this->assertStringContainsString('hreflang="en" href="'.url('/en').'">', $html);
        $this->assertStringContainsString('hreflang="x-default" href="'.url('/ar').'">', $html);
    }

    public function test_not_found_pages_use_localized_public_or_admin_shell(): void
    {
        $publicHtml = (string) $this->noAcceptLanguage()->get('/xyz')->getContent();

        $this->assertStringContainsString('data-shell="public"', $publicHtml);
        $this->assertStringContainsString('href="'.route('public.home', ['locale' => 'ar']).'"', $publicHtml);
        $this->assertStringContainsString('role="status"', $publicHtml);

        $removedHtml = (string) $this->noAcceptLanguage()->get('/contact')->getContent();

        $this->assertStringContainsString('data-shell="public"', $removedHtml);
        $this->assertStringContainsString('href="'.route('public.home', ['locale' => 'ar']).'"', $removedHtml);
        $this->assertStringNotContainsString('href="'.url('/ar/contact').'"', $removedHtml);

        $adminHtml = (string) $this->get('/ar/admin/does-not-exist')->getContent();

        $this->assertStringContainsString('data-shell="admin"', $adminHtml);
        $this->assertStringContainsString('الإدارة', $adminHtml);
    }

    private function inertiaVersion(): string
    {
        $manifest = public_path('build/manifest.json');

        if (config('app.asset_url')) {
            return hash('xxh128', (string) config('app.asset_url'));
        }

        return is_file($manifest) ? hash_file('xxh128', $manifest) : '';
    }

    private function noAcceptLanguage(): static
    {
        return $this->withServerVariables(['HTTP_ACCEPT_LANGUAGE' => '']);
    }

    private function followToOk(string $path, string $locale): TestResponse
    {
        $response = $this->withCookie('pcdr_locale', $locale)->get($path);

        for ($remaining = 3; $remaining > 0 && $response->isRedirect(); $remaining--) {
            $location = (string) $response->headers->get('Location');
            $target = (string) (parse_url($location, PHP_URL_PATH) ?: '/');
            $query = parse_url($location, PHP_URL_QUERY);

            if (is_string($query) && $query !== '') {
                $target .= '?'.$query;
            }

            $response = $this->withCookie('pcdr_locale', $locale)->get($target);
        }

        $this->assertFalse($response->isRedirect(), 'Redirect loop detected for '.$path);

        return $response;
    }
}
