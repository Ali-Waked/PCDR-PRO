<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class LoginExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_uses_one_generic_response_for_every_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'known@example.test']);

        $this->from('/ar/forgot-password')->post('/ar/forgot-password', [
            'email' => $user->email,
        ])->assertSessionHas('success', __('passwords.sent'));

        $knownMessage = session('success');

        $this->from('/ar/forgot-password')->post('/ar/forgot-password', [
            'email' => 'unknown@example.test',
        ])->assertSessionHas('success', __('passwords.sent'))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame($knownMessage, session('success'));
        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
        Notification::assertCount(1);
    }

    public function test_forgot_password_success_flash_reaches_the_inertia_page(): void
    {
        $this->withSession(['success' => __('passwords.sent')])
            ->get('/ar/forgot-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ForgotPassword')
                ->where('flash.success', __('passwords.sent')));
    }

    public function test_login_intro_reuses_the_exact_official_logo_assets(): void
    {
        $staticLogo = public_path('assets/pcdr-homepage/logo/pcdr-logo.png');
        $animatedLogo = public_path('assets/pcdr-homepage/logo/pcdr-logo-animated.svg');

        $this->assertFileExists($staticLogo);
        $this->assertFileExists($animatedLogo);
        $this->assertSame(
            '5834fb72446a75cd56ed6a51a06ae1dafcaa56559c62e6906603978eb27ecb9e',
            hash_file('sha256', $staticLogo),
        );
        $this->assertSame(
            '7dc83babf9f49f13e49d381d4c928e616cb41bf1285a6ead3310488aca98a80b',
            hash_file('sha256', $animatedLogo),
        );

        $loginSource = file_get_contents(resource_path('js/Pages/Auth/Login.vue'));
        $introSource = file_get_contents(resource_path('js/Components/Auth/BrandIntro.vue'));
        $layoutSource = file_get_contents(resource_path('js/Layouts/AuthLayout.vue'));

        $this->assertStringContainsString('BrandIntro.vue', $loginSource);
        $this->assertStringContainsString('pcdr-logo-animated.svg', $introSource);
        $this->assertStringContainsString('pcdr-logo.png', $layoutSource);
        $this->assertStringContainsString("provide('pcdr-auth-ready', isReady)", $layoutSource);
        $this->assertStringContainsString("inject('pcdr-auth-ready', ref(true))", $introSource);
    }

    public function test_login_intro_owns_its_tokens_and_finishes_before_marking_the_session(): void
    {
        $intro = (string) file_get_contents(resource_path('js/Components/Auth/BrandIntro.vue'));
        $css = (string) file_get_contents(resource_path('css/dashboard.css'));
        $matches = [];
        $matched = preg_match('/\.auth-intro\s*\{(.*?)\n\}/s', $css, $matches);
        $introBlock = $matches[1] ?? '';

        $this->assertSame(1, $matched);
        $this->assertStringNotContainsString('--auth-', $introBlock);

        foreach (['--intro-canvas:', '--intro-navy:', '--intro-teal-wash:', '--intro-wheat-line:'] as $token) {
            $this->assertStringContainsString($token, $introBlock);
        }

        $this->assertStringContainsString('--intro-canvas: var(--pcdr-navy-900)', $introBlock);
        $this->assertStringContainsString('--intro-logo-surface: var(--pcdr-white)', $introBlock);
        $this->assertStringNotContainsString('--intro-canvas: var(--pcdr-white)', $introBlock);

        $this->assertStringContainsString('let introStarted = false', $intro);
        $this->assertStringContainsString('const MIN_VISIBLE_MS = 3000', $intro);
        $this->assertStringContainsString('visible.value = !hasPlayed() && !prefersReducedMotion()', $intro);
        $this->assertStringContainsString('startedAt = Date.now()', $intro);
        $this->assertStringContainsString('if (!force && !authReady.value)', $intro);
        $this->assertStringContainsString('watch(authReady,', $intro);
        $this->assertStringContainsString("    finished = true\n    visible.value = false\n    markPlayed()\n    restoreMain()", $intro);
        $this->assertStringContainsString('(prefers-reduced-motion: reduce)', $intro);
        $this->assertStringContainsString('<span v-if="tagline">{{ tagline }}</span>', $intro);
        $this->assertStringContainsString('.auth-intro,', $css);

        foreach (['Auth/ForgotPassword.vue', 'Auth/ResetPassword.vue', 'Auth/VerifyEmail.vue', 'Cms/Login.vue'] as $page) {
            $this->assertStringNotContainsString('BrandIntro', (string) file_get_contents(resource_path("js/Pages/{$page}")), $page);
        }
    }

    public function test_all_existing_auth_pages_share_the_unified_design_system(): void
    {
        $pages = [
            'js/Pages/Auth/Login.vue',
            'js/Pages/Auth/ForgotPassword.vue',
            'js/Pages/Auth/ResetPassword.vue',
            'js/Pages/Auth/VerifyEmail.vue',
            'js/Pages/Cms/Login.vue',
        ];

        foreach ($pages as $page) {
            $source = file_get_contents(resource_path($page));

            $this->assertStringContainsString('AuthLayout.vue', $source, $page);
            $this->assertStringContainsString('AuthHeader.vue', $source, $page);
            $this->assertStringContainsString('pc-auth__links', $source, $page);
        }

        $passwordPages = [
            'js/Pages/Auth/Login.vue',
            'js/Pages/Auth/ResetPassword.vue',
            'js/Pages/Cms/Login.vue',
        ];

        foreach ($passwordPages as $page) {
            $this->assertStringContainsString(
                'PasswordInput.vue',
                file_get_contents(resource_path($page)),
                $page,
            );
        }
    }

    public function test_new_auth_interface_strings_are_localized_in_arabic_and_english(): void
    {
        foreach (['ar', 'en'] as $locale) {
            foreach (['show_password', 'hide_password', 'check_email_title'] as $key) {
                $this->assertNotEmpty(__("ui.auth.{$key}", [], $locale), "{$locale}.{$key}");
            }
        }

        $this->assertNotSame(__('ui.auth.show_password', [], 'ar'), __('ui.auth.show_password', [], 'en'));
    }

    public function test_password_reset_still_uses_the_laravel_broker_and_rejects_invalid_tokens(): void
    {
        $user = User::factory()->create(['email' => 'reset-experience@example.test']);
        $token = Password::broker()->createToken($user);

        $this->post('/ar/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'brand-new-password-123',
            'password_confirmation' => 'brand-new-password-123',
        ])->assertSessionHasErrors('email');

        $this->assertFalse(Hash::check('brand-new-password-123', $user->fresh()->password));

        $this->post('/ar/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'brand-new-password-123',
            'password_confirmation' => 'brand-new-password-123',
        ])->assertRedirect('/ar/login');

        $this->assertTrue(Hash::check('brand-new-password-123', $user->fresh()->password));
    }

    public function test_email_verification_signature_is_still_enforced(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get("/ar/verify-email/{$user->id}/".sha1($user->email))
            ->assertForbidden();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'locale' => 'ar',
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect('/ar/dashboard');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
