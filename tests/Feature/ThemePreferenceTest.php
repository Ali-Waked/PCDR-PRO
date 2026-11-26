<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', '12345678901234567890123456789012');

        $this->withoutVite();
    }

    public function test_guest_receives_the_system_theme_prop(): void
    {
        $this->get('/ar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('theme', 'system'));
    }

    public function test_shell_remains_pinned_and_runtime_applies_the_theme(): void
    {
        $shell = (string) file_get_contents(resource_path('views/app.blade.php'));

        $this->assertSame(
            '2a7affa19d09acda482e3d3dbce2ef97d374c259eaaa677848145c24ba871765',
            hash('sha256', $shell),
        );
        $this->assertStringContainsString('<meta name="color-scheme" content="light">', $shell);
        $this->assertStringNotContainsString('pcdr-theme-preference', $shell);

        $app = (string) file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('applyThemePreference(preference, { persist: false, transition: false })', $app);
        $this->assertStringContainsString('readStoredPreference()', $app);
    }

    public function test_runtime_leaves_system_theme_to_the_css_media_query(): void
    {
        $composable = (string) file_get_contents(resource_path('js/composables/useTheme.js'));

        $this->assertStringContainsString("if (preference === 'system') {", $composable);
        $this->assertStringContainsString('delete root.dataset.theme', $composable);
        $this->assertStringContainsString('root.dataset.theme = preference', $composable);
        $this->assertStringContainsString('root.dataset.themePreference = preference', $composable);
        $this->assertStringContainsString("preference === 'system' ? 'light dark' : preference", $composable);
        $this->assertStringContainsString('prefers-color-scheme: dark', $composable);
    }

    public function test_selector_reads_translations_from_the_shared_locale_helper(): void
    {
        $selector = (string) file_get_contents(resource_path('js/Components/Dashboard/ThemeSelector.vue'));

        $this->assertStringContainsString("from '../../Composables/useLocale.js'", $selector);
        $this->assertStringContainsString('const { t, localePath } = useLocale()', $selector);
        $this->assertStringContainsString(':aria-label="t(option.labelKey)"', $selector);
    }

    public function test_selector_labels_are_localized(): void
    {
        $expected = [
            'en' => ['Appearance', 'Light', 'Dark', 'System'],
            'ar' => ['المظهر', 'فاتح', 'داكن', 'النظام'],
        ];

        foreach ($expected as $locale => $labels) {
            /** @var array{account_profile: array<string, mixed>} $translations */
            $translations = require lang_path($locale.'/ui.php');
            $keys = ['theme', 'theme_light', 'theme_dark', 'theme_system'];

            foreach ($keys as $index => $key) {
                $this->assertSame($labels[$index], $translations['account_profile'][$key]);
            }
        }
    }

    public function test_light_theme_text_tokens_avoid_the_axe_contrast_failures(): void
    {
        $tokens = (string) file_get_contents(resource_path('css/tokens.css'));
        $identity = (string) file_get_contents(resource_path('css/pcdr-identity-tokens.css'));
        $chrome = (string) file_get_contents(resource_path('js/Components/Dashboard/DashboardChrome.vue'));

        $this->assertStringContainsString('--pcdr-text-secondary: #5f6a7d;', $tokens);
        $this->assertStringContainsString('--pcdr-muted: #5f6a7d;', $tokens);
        $this->assertStringContainsString('--pcdr-secondary: var(--pcdr-teal-ink);', $tokens);
        $this->assertStringContainsString('--pcdr-id-brand-teal: var(--pcdr-teal-ink);', $identity);
        $this->assertStringContainsString('--pcdr-id-brand-teal: var(--pcdr-teal);', $identity);
        $this->assertStringContainsString('const userTriggerLabel = computed(() => {', $chrome);
        $this->assertStringContainsString(':aria-label="userTriggerLabel"', $chrome);
    }

    public function test_authenticated_user_can_persist_a_theme_without_resubmitting_other_profile_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Theme User',
            'email' => 'theme@example.test',
            'locale' => 'ar',
        ]);

        $this->actingAs($user)
            ->put(route('account.profile.update', ['locale' => 'ar']), [
                'theme' => 'dark',
            ])
            ->assertRedirect();

        $this->assertSame('dark', $user->fresh()->theme);

        $this->actingAs($user)
            ->get('/ar/dashboard/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('theme', 'dark')
                ->where('auth.user.theme', 'dark')
                ->where('profile.theme', 'dark')
            );
    }

    public function test_system_theme_is_stored_as_null_and_resolved_to_system(): void
    {
        $user = User::factory()->create([
            'locale' => 'ar',
            'theme' => 'dark',
        ]);

        $this->actingAs($user)
            ->put(route('account.profile.update', ['locale' => 'ar']), [
                'theme' => 'system',
            ])
            ->assertRedirect();

        $this->assertNull($user->fresh()->theme);

        $this->actingAs($user)
            ->get('/en/dashboard/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('theme', 'system'));
    }

    public function test_invalid_theme_values_are_rejected(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);

        $this->actingAs($user)
            ->from(route('account.profile.show', ['locale' => 'ar']))
            ->put(route('account.profile.update', ['locale' => 'ar']), [
                'theme' => 'high-contrast',
            ])
            ->assertRedirect(route('account.profile.show', ['locale' => 'ar']))
            ->assertSessionHasErrors('theme');

        $this->assertSame(User::THEME_SYSTEM, $user->fresh()->theme ?: User::THEME_SYSTEM);
    }
}
