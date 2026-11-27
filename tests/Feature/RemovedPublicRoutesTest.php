<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RemovedPublicRoutesTest extends TestCase
{
    use RefreshDatabase;

    private const REMOVED_GET_PATHS = [
        'strategic-areas', 'strategic-areas/health',
        'programs/relief', 'projects/relief-project', 'news', 'news/activity',
        'publications', 'publications/report', 'publications/report/download',
        'transparency', 'partnerships', 'get-involved', 'pages/institutional',
    ];

    private const REMOVED_ROUTE_NAMES = [
        'public.about.focus', 'public.inquiry.contact', 'public.inquiry.volunteer', 'public.involve',
        'public.news.index', 'public.news.show', 'public.page',
        'public.partnerships', 'public.programs.index', 'public.programs.show',
        'public.projects.show', 'public.publications.download', 'public.publications.index',
        'public.publications.show', 'public.pillars.index', 'public.pillars.show',
        'public.transparency',
    ];

    /** @return array<string, array{string, string}> */
    public static function localeProvider(): array
    {
        return [
            'arabic' => ['ar', 'rtl'],
            'english' => ['en', 'ltr'],
        ];
    }

    #[DataProvider('localeProvider')]
    public function test_removed_public_paths_render_localized_real_404(string $locale, string $direction): void
    {
        foreach (self::REMOVED_GET_PATHS as $path) {
            $response = $this->get('/'.$locale.'/'.$path);

            $response->assertNotFound();
            $this->assertNull($response->headers->get('Location'));

            $html = (string) $response->getContent();
            $this->assertStringContainsString('<html lang="'.$locale.'" dir="'.$direction.'"', $html);
            $this->assertStringContainsString('href="'.route('public.home', ['locale' => $locale]).'"', $html);
            $this->assertStringNotContainsString('href="'.url('/'.$locale.'/'.$path).'"', $html);
        }
    }

    public function test_removed_locale_less_paths_are_not_redirected(): void
    {
        foreach (self::REMOVED_GET_PATHS as $path) {
            $response = $this->get('/'.$path);

            $response->assertNotFound();
            $this->assertNull($response->headers->get('Location'));
        }
    }

    public function test_removed_public_post_paths_are_not_redirected(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        foreach (['contact/inquiry', 'volunteer/inquiry'] as $path) {
            foreach (['', 'ar/', 'en/'] as $prefix) {
                $response = $this->post('/'.$prefix.$path);

                $this->assertContains($response->getStatusCode(), [404, 405]);
                $this->assertNull($response->headers->get('Location'));
            }
        }
    }

    public function test_removed_public_route_names_are_unregistered(): void
    {
        $registered = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route): string => (string) $route->getName())
            ->all();

        foreach (self::REMOVED_ROUTE_NAMES as $name) {
            $this->assertFalse(in_array($name, $registered, true), $name.' is still registered.');
            $this->assertFalse(Route::has($name), $name.' still resolves.');
        }
    }

    public function test_removed_public_controllers_and_frontend_stack_are_absent(): void
    {
        foreach ([
            'PillarController', 'ProgramController', 'NewsController',
            'PublicationController', 'StaticPageController', 'PageController',
        ] as $controller) {
            $this->assertFalse(File::exists(app_path('Http/Controllers/Public/'.$controller.'.php')));
        }

        $this->assertFalse(File::exists(app_path('Http/Controllers/Public/InquiryController.php')));

        foreach ([
            'resources/css/public-site.css', 'resources/css/complaints-box.css',
            'resources/js/Layouts/PublicLayout.vue', 'resources/js/Components/Public/SiteHeader.vue',
            'resources/js/Components/Public/SiteFooter.vue',
            'resources/js/Pages/Public/News/Index.vue', 'resources/js/Pages/Public/Programs/Index.vue',
            'resources/js/Pages/Public/Pillars/Index.vue', 'resources/js/Pages/Public/Publications/Index.vue',
            'resources/js/composables/usePublicNavigation.js', 'resources/js/Support/complaintsEmail.js',
        ] as $relativePath) {
            $this->assertFalse(File::exists(base_path($relativePath)), $relativePath.' still exists.');
        }
    }

    public function test_restored_public_controllers_and_pages_are_present(): void
    {
        foreach ([
            'AboutController', 'ComplaintController', 'ContactController',
            'MediaCenterController', 'PartnersController', 'ProgramsController',
        ] as $controller) {
            $this->assertTrue(File::exists(app_path('Http/Controllers/Public/'.$controller.'.php')));
        }

        foreach ([
            'About.vue', 'Complaints.vue', 'Contact.vue',
            'Home.vue', 'MediaCenter.vue', 'Partners.vue', 'Programs.vue',
        ] as $page) {
            $this->assertTrue(File::exists(resource_path('js/Pages/Public/'.$page)), $page.' is missing.');
        }
    }
}
