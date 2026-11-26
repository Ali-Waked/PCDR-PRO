<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PublicSurfacePreservationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> */
    public static function localeProvider(): array
    {
        return [
            'arabic' => ['ar', 'rtl'],
            'english' => ['en', 'ltr'],
        ];
    }

    public function test_the_public_surface_keeps_home_and_asset_routes_only(): void
    {
        foreach ([
            'public.home', 'api.public.homepage', 'public.media.download',
            'public.operational-documents.download',
        ] as $name) {
            $this->assertTrue(Route::has($name), $name.' is missing.');
        }

        $publicRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'public.'))
            ->map(fn ($route): string => (string) $route->getName())
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'public.home', 'public.media.download', 'public.operational-documents.download',
        ], $publicRoutes);
    }

    #[DataProvider('localeProvider')]
    public function test_home_still_renders_in_both_locales(string $locale, string $direction): void
    {
        $this->get('/'.$locale)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Home')
                ->where('locale', $locale)
                ->where('meta.locale', $locale));
    }

    public function test_root_still_redirects_to_the_default_locale(): void
    {
        $this->get('/')->assertRedirect('/ar');
    }

    #[DataProvider('localeProvider')]
    public function test_auth_pages_still_render_for_guests(string $locale): void
    {
        $this->get('/'.$locale.'/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));

        $this->get('/'.$locale.'/forgot-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));

        $this->get('/'.$locale.'/cms/login')->assertOk();
    }

    public function test_auth_route_names_are_still_registered(): void
    {
        foreach ([
            'login.show', 'login.store', 'logout', 'password.request', 'password.email',
            'password.store', 'password.reset', 'cms.login', 'cms.login.attempt', 'cms.logout',
        ] as $name) {
            $this->assertTrue(Route::has($name), $name.' is missing.');
        }
    }

    public function test_admin_cms_and_operations_routes_are_still_registered_and_protected(): void
    {
        foreach ([
            'dashboard', 'admin.users.index', 'admin.permissions.index',
            'cms.dashboard', 'cms.programs.index', 'cms.homepage.index',
            'operations.dashboard', 'phase5.dashboard',
        ] as $name) {
            $this->assertTrue(Route::has($name), $name.' is missing.');
        }

        $this->get('/ar/dashboard')->assertRedirectContains('login');
        $this->get('/ar/admin/users')->assertRedirectContains('login');
        $this->get('/ar/cms/programs')->assertRedirectContains('login');
    }

    public function test_media_download_route_keeps_its_signature_gate(): void
    {
        $this->get(route('public.media.download', ['locale' => 'ar', 'uuid' => 'not-a-media-uuid']))
            ->assertForbidden();
    }
}
