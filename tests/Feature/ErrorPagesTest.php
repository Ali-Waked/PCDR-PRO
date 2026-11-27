<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET_EXCEPTION = 'SECRET_EXCEPTION_MARKER_7f3a91';

    private const SECRET_FORBIDDEN = 'SECRET_FORBIDDEN_MARKER_9c2d48';

    /** @return array<string, array{string, string}> */
    public static function localeProvider(): array
    {
        return [
            'arabic' => ['ar', 'rtl'],
            'english' => ['en', 'ltr'],
        ];
    }

    /** @return array<string, array{string, int, string, string, string}> */
    public static function errorPageProvider(): array
    {
        return [
            'arabic 403' => ['/ar/__pcdr-403-probe', 403, '403', 'ar', 'rtl'],
            'arabic 404' => ['/ar/pcdr-missing-page-probe', 404, '404', 'ar', 'rtl'],
            'arabic 500' => ['/ar/__pcdr-500-probe', 500, '500', 'ar', 'rtl'],
            'english 403' => ['/en/__pcdr-403-probe', 403, '403', 'en', 'ltr'],
            'english 404' => ['/en/pcdr-missing-page-probe', 404, '404', 'en', 'ltr'],
            'english 500' => ['/en/__pcdr-500-probe', 500, '500', 'en', 'ltr'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([])
            ->where(['locale' => 'ar|en'])
            ->get('/{locale}/__pcdr-403-probe', function (string $_locale): never {
                abort(403, self::SECRET_FORBIDDEN);
            });

        Route::middleware([])
            ->where(['locale' => 'ar|en'])
            ->get('/{locale}/__pcdr-500-probe', function (string $_locale): never {
                throw new RuntimeException(self::SECRET_EXCEPTION);
            });
    }

    #[DataProvider('localeProvider')]
    public function test_missing_public_route_renders_localized_404(string $locale, string $direction): void
    {
        $response = $this->get('/'.$locale.'/pcdr-missing-page-probe');
        $response->assertNotFound();

        $html = (string) $response->getContent();
        $expected = $locale === 'ar'
            ? [
                'عذراً، لم نتمكن من العثور على هذه الصفحة',
                'قد يكون الرابط غير صحيح، أو أن الصفحة لم تعد متاحة. يمكنك العودة إلى الصفحة الرئيسية ومتابعة استكشاف موقعنا.',
            ]
            : [
                "We couldn't find this page.",
                'The link may be incorrect, or the page may no longer be available. You can return to the homepage and continue exploring our website.',
            ];

        $this->assertStringContainsString('<html lang="'.$locale.'" dir="'.$direction.'" data-locale="'.$locale.'">', $html);
        $this->assertStringContainsString(e($expected[0]), $html);
        $this->assertStringContainsString(e($expected[1]), $html);
        $this->assertStringContainsString('href="'.route('public.home', ['locale' => $locale]).'"', $html);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringNotContainsString('@vite', $html);
        $this->assertNoDebugDetails($html);
    }

    #[DataProvider('localeProvider')]
    public function test_forbidden_probe_renders_localized_403_without_authorization_details(string $locale, string $direction): void
    {
        $response = $this->get('/'.$locale.'/__pcdr-403-probe');
        $response->assertForbidden();

        $html = (string) $response->getContent();
        $expected = $locale === 'ar'
            ? [
                'ليس لديك صلاحية للوصول إلى هذه الصفحة',
                'عذراً، لا تملك الصلاحيات اللازمة للوصول إلى هذا المحتوى. إذا كنت تعتقد أن هذا غير صحيح، يرجى التواصل مع المسؤول المختص عبر قنوات التواصل الرسمية.',
            ]
            : [
                "You don't have permission to access this page.",
                'Sorry, you do not have the required permissions to access this content. If you believe this is an error, please contact the appropriate administrator through the official channels.',
            ];

        $this->assertStringContainsString('<html lang="'.$locale.'" dir="'.$direction.'" data-locale="'.$locale.'">', $html);
        $this->assertStringContainsString(e($expected[0]), $html);
        $this->assertStringContainsString(e($expected[1]), $html);
        $this->assertStringContainsString('href="'.route('public.home', ['locale' => $locale]).'"', $html);
        $this->assertStringNotContainsString(self::SECRET_FORBIDDEN, $html);
        $this->assertStringNotContainsString('You do not have access to this CMS area.', $html);
        $this->assertStringNotContainsString('auth.forbidden', $html);
        $this->assertNoDebugDetails($html);
    }

    public function test_real_authorization_forbidden_response_uses_the_shared_403_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/ar/admin/users');
        $response->assertForbidden();

        $html = (string) $response->getContent();

        $this->assertStringContainsString('ليس لديك صلاحية للوصول إلى هذه الصفحة', $html);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringNotContainsString('users.view', $html);
        $this->assertStringNotContainsString('You do not have access to this CMS area.', $html);
        $this->assertNoDebugDetails($html);
    }

    #[DataProvider('localeProvider')]
    public function test_controlled_exception_renders_localized_500_without_debug_details(string $locale, string $direction): void
    {
        config()->set('app.debug', false);
        Log::spy();

        $response = $this->get('/'.$locale.'/__pcdr-500-probe');
        $response->assertStatus(500);

        Log::shouldHaveReceived('error')
            ->with(self::SECRET_EXCEPTION, Mockery::type('array'))
            ->once();

        $html = (string) $response->getContent();
        $expected = $locale === 'ar'
            ? [
                'حدث خطأ غير متوقع',
                'نواجه مشكلة تقنية أثناء معالجة طلبك. نعتذر عن هذا الإزعاج. يرجى المحاولة مرة أخرى بعد قليل.',
            ]
            : [
                'Something went wrong.',
                'We encountered a technical problem while processing your request. We apologize for the inconvenience. Please try again shortly.',
            ];

        $this->assertStringContainsString('<html lang="'.$locale.'" dir="'.$direction.'" data-locale="'.$locale.'">', $html);
        $this->assertStringContainsString(e($expected[0]), $html);
        $this->assertStringContainsString(e($expected[1]), $html);
        $this->assertStringContainsString('href="'.route('public.home', ['locale' => $locale]).'"', $html);
        $this->assertStringContainsString('data-error-action="reload"', $html);
        $this->assertStringNotContainsString(
            e($locale === 'ar' ? 'العودة إلى الصفحة السابقة' : 'Go back'),
            $html,
        );
        $this->assertNoDebugDetails($html);
    }

    #[DataProvider('errorPageProvider')]
    public function test_error_pages_share_an_edge_to_edge_layout_and_responsive_artwork(
        string $path,
        int $status,
        string $code,
        string $locale,
        string $direction,
    ): void {
        config()->set('app.debug', false);
        Log::spy();

        $html = (string) $this->get($path)->assertStatus($status)->getContent();

        $this->assertStringContainsString('<html lang="'.$locale.'" dir="'.$direction.'"', $html);
        $this->assertMatchesRegularExpression('/html,\s*body\s*\{\s*margin:\s*0;\s*padding:\s*0;\s*background:\s*#f7f2ea;/', $html);
        $this->assertStringContainsString('min-height: 100dvh;', $html);
        $this->assertStringNotContainsString('inset-inline-end: -4%', $html);
        $this->assertStringNotContainsString('<span class="pcdr-error__code">', $html);
        $this->assertStringContainsString('preserveAspectRatio="xMidYMid meet"', $html);
        $this->assertStringContainsString(
            '<text class="pcdr-error__code" x="50%" y="50%" text-anchor="middle" dominant-baseline="central">'.$code.'</text>',
            $html,
        );

        if ($code === '404') {
            $this->assertStringNotContainsString('cx="390" cy="190" r="150"', $html);
            $this->assertStringContainsString('cx="360" cy="190" r="150"', $html);
        }
    }

    public function test_error_rendering_does_not_require_database_queries(): void
    {
        config()->set('app.debug', false);
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->get('/ar/pcdr-no-database-404-probe')->assertNotFound();
            $this->get('/ar/__pcdr-403-probe')->assertForbidden();
            $this->get('/ar/__pcdr-500-probe')->assertStatus(500);
        } finally {
            DB::disableQueryLog();
        }

        $this->assertSame([], DB::getQueryLog());
    }

    public function test_existing_public_and_dashboard_routes_remain_available(): void
    {
        $this->get('/ar')->assertOk();

        $owner = User::factory()->platformOwner()->create();

        $this->actingAs($owner)->get('/ar/dashboard')->assertOk();
    }

    private function assertNoDebugDetails(string $html): void
    {
        $needles = [
            self::SECRET_EXCEPTION,
            self::SECRET_FORBIDDEN,
            'RuntimeException',
            'Stack trace',
            'SQLSTATE',
            'APP_KEY',
            'DB_PASSWORD',
            'vendor/',
            base_path(),
        ];

        foreach ($needles as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }
    }
}
