<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class RedesignHomepageFrontendTest extends TestCase
{
    public function test_public_homepage_keeps_all_pinned_markers_and_order(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Public/Home.vue'));

        foreach ([
            'class="pcdr-home public-shell"',
            '<SiteHeader :transparent="true" />',
            'class="sec cta"',
            '<SiteFooter />',
            'props.sections',
            'useHomepageContent',
            'pcdr-pro-home.css',
        ] as $marker) {
            $this->assertStringContainsString($marker, $source, $marker.' marker missing.');
        }

        $this->assertSame(1, substr_count($source, 'href="#main"'), 'Only the in-page skip link may remain an anchor.');
        $this->assertStringNotContainsString('const content = {', $source);
        $this->assertStringNotContainsString('navOpen', $source);

        $markers = [
            'id="top"',
            'id="about"',
            'id="areas"',
            'id="approach"',
            'id="programs"',
            'id="targets"',
            'id="community"',
            'id="partners"',
            'id="knowledge"',
            'class="sec cta"',
        ];

        $positions = [];
        foreach ($markers as $marker) {
            $position = strpos($source, $marker);
            $this->assertNotFalse($position, $marker.' section missing.');
            $positions[] = $position;
        }

        $this->assertSame($positions, array_values(array_unique($positions)), 'Section markers must be unique.');
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($positions, $sorted, 'Section markers must retain canonical order.');
    }

    public function test_homepage_content_composable_accepts_getters_and_non_array_sources(): void
    {
        $composable = (string) file_get_contents(resource_path('js/composables/useHomepageContent.js'));

        $this->assertStringContainsString('toValue', $composable, 'Getters must be resolved with toValue.');
        $this->assertStringContainsString('Array.isArray', $composable, 'Non-array sources must degrade safely.');
        $this->assertStringNotContainsString('unref(', $composable, 'unref does not invoke getter sources.');
    }

    public function test_redesign_components_are_scoped_under_one_css_namespace(): void
    {
        $css = (string) file_get_contents(resource_path('css/pcdr-pro-home.css'));

        $this->assertStringContainsString('.pcdr-pro-home {', $css);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        $this->assertStringContainsString('@media (prefers-color-scheme: dark)', $css);

        foreach (preg_split('/\R/', $css) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '@')) {
                continue;
            }

            if (preg_match('/^[.#\[a-zA-Z][^{}]*\{/', $trimmed) === 1) {
                $this->assertStringContainsString('.pcdr-pro-home', $trimmed, 'Unscoped CSS selector: '.$trimmed);
            }
        }

        $componentDirectory = resource_path('js/Components/RedesignHome');
        $components = glob($componentDirectory.'/Pro*.vue') ?: [];

        $this->assertNotEmpty($components, 'No redesign components found.');

        foreach ($components as $component) {
            $source = (string) file_get_contents($component);
            $this->assertStringContainsString('class="pcdr-pro-home', $source, basename($component).' is not scoped.');
            $this->assertSame(1, substr_count($source, '<template>'), basename($component).' must have one template root.');
        }
    }
}
