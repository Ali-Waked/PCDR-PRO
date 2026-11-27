<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class RedesignHomepageFrontendTest extends TestCase
{
    /** @var list<string> */
    private const PROTOTYPE_ORDER = [
        '<ProPrototypeSprite />',
        'class="skip" href="#top"',
        '<ProTopStrip />',
        '<ProPillHeader :anchor-base="localPath()" />',
        '<main id="top">',
        '<template v-for="section in mainSections"',
        '</main>',
        'v-if="footerSection && SECTION_COMPONENTS[footerSection.key]"',
    ];

    /** @var list<string> */
    private const SECTION_ORDER = [
        'hero: ProHeroCarousel',
        'ticker: ProTicker',
        'impact: ProImpact',
        'programs: ProProgramAxes',
        'news: ProNewsTabs',
        'albums: ProMediaGallery',
        'about: ProAboutTabs',
        'stories: ProStories',
        'footer: ProPrototypeFooter',
    ];

    /** @var list<string> */
    private const PROTOTYPE_COMPONENTS = [
        'ProPrototypeSprite',
        'ProTopStrip',
        'ProPillHeader',
        'ProHeroCarousel',
        'ProTicker',
        'ProImpact',
        'ProProgramAxes',
        'ProNewsTabs',
        'ProMediaGallery',
        'ProAboutTabs',
        'ProStories',
        'ProPrototypeFooter',
    ];

    public function test_public_homepage_keeps_prototype_markers_and_component_order(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Public/Home.vue'));

        foreach ([
            'class="pcdr-prototype"',
            "import '../../../css/pcdr-prototype.css'",
            'props.sections',
            'alternateUrl',
        ] as $marker) {
            $this->assertStringContainsString($marker, $source, $marker.' marker missing.');
        }

        foreach ([
            'class="pcdr-home public-shell"',
            '<SiteHeader',
            '<SiteFooter',
            'routeFor(',
            'pcdr-pro-home.css',
            'const content = {',
            'navOpen',
            'href="#main"',
        ] as $retiredMarker) {
            $this->assertStringNotContainsString($retiredMarker, $source, $retiredMarker.' still present.');
        }

        $this->assertSame(1, substr_count($source, 'href="#top"'), 'Only the in-page skip link may target #top.');
        $this->assertStringNotContainsString('href="/ar/', $source, 'Internal links must not be hardcoded for Arabic.');
        $this->assertStringNotContainsString('href="/en/', $source, 'Internal links must not be hardcoded for English.');

        $sectionPositions = [];
        foreach (self::SECTION_ORDER as $marker) {
            $position = strpos($source, $marker);
            $this->assertNotFalse($position, $marker.' section mapping missing.');
            $sectionPositions[] = $position;
        }

        $this->assertSame($sectionPositions, array_values(array_unique($sectionPositions)), 'Section mappings must be unique.');
        $sortedSectionPositions = $sectionPositions;
        sort($sortedSectionPositions);
        $this->assertSame($sectionPositions, $sortedSectionPositions, 'Section mappings must retain canonical prototype order.');

        $positions = [];
        foreach (self::PROTOTYPE_ORDER as $marker) {
            $position = strpos($source, $marker);
            $this->assertNotFalse($position, $marker.' section missing.');
            $positions[] = $position;
        }

        $this->assertSame($positions, array_values(array_unique($positions)), 'Section markers must be unique.');
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($positions, $sorted, 'Section markers must retain canonical prototype order.');
    }

    public function test_prototype_sections_expose_required_anchors_and_canonical_keys(): void
    {
        $anchors = [
            'js/Pages/Public/Home.vue' => ['href="#top"', 'id="top"'],
            'js/Components/RedesignHome/ProImpact.vue' => ['id="impact"', 'id="targets"'],
            'js/Components/RedesignHome/ProProgramAxes.vue' => ['id="programs"'],
            'js/Components/RedesignHome/ProNewsTabs.vue' => ['id="news"'],
            'js/Components/RedesignHome/ProMediaGallery.vue' => ['id="albums"'],
            'js/Components/RedesignHome/ProAboutTabs.vue' => ['id="about"'],
            'js/Components/RedesignHome/ProStories.vue' => ['id="stories"'],
        ];

        foreach ($anchors as $path => $markers) {
            $source = (string) file_get_contents(resource_path($path));

            foreach ($markers as $marker) {
                $this->assertStringContainsString($marker, $source, $path.' is missing '.$marker.'.');
            }
        }

        $footerSource = (string) file_get_contents(resource_path('js/Components/RedesignHome/ProPrototypeFooter.vue'));
        $this->assertStringContainsString('page.props.footer', $footerSource, 'Footer must use the shared filtered CMS sections.');
        $this->assertStringContainsString('page.props.navigation?.primary', $footerSource, 'Footer must use the shared navigation source.');
        $this->assertStringContainsString("navigationItem('about')", $footerSource, 'Footer About links must come from navigation.');
        $this->assertStringNotContainsString("localPath('about')", $footerSource, 'Footer must not hardcode the About URL.');
        $this->assertStringNotContainsString("localPath('complaints')", $footerSource, 'Footer must not hardcode the complaints URL.');

        $sectionKeys = [
            'ProHeroCarousel.vue' => "content.items('hero')",
            'ProTicker.vue' => "content.section('ticker')",
            'ProImpact.vue' => "content.section('impact')",
            'ProProgramAxes.vue' => "content.section('programs')",
            'ProNewsTabs.vue' => "content.section('news')",
            'ProMediaGallery.vue' => "content.section('albums')",
            'ProAboutTabs.vue' => "content.section('about')",
            'ProStories.vue' => "content.section('stories')",
            'ProPrototypeFooter.vue' => "content.visibleSection('footer')",
        ];

        foreach ($sectionKeys as $component => $marker) {
            $source = (string) file_get_contents(resource_path('js/Components/RedesignHome/'.$component));
            $contentSource = $component === 'ProPrototypeFooter.vue'
                ? 'useHomepageContent(() => effectiveSections.value)'
                : 'useHomepageContent(() => props.sections)';
            $this->assertStringContainsString($contentSource, $source, $component.' must read CMS sections through the composable.');
            $this->assertStringContainsString($marker, $source, $component.' is missing '.$marker.'.');
        }
    }

    public function test_homepage_content_composable_accepts_getters_and_non_array_sources(): void
    {
        $composable = (string) file_get_contents(resource_path('js/composables/useHomepageContent.js'));

        $this->assertStringContainsString('toValue', $composable, 'Getters must be resolved with toValue.');
        $this->assertStringContainsString('Array.isArray', $composable, 'Non-array sources must degrade safely.');
        $this->assertStringNotContainsString('unref(', $composable, 'unref does not invoke getter sources.');

        $this->assertStringContainsString('itemsByTab', $composable, 'Prototype tabs must be grouped by CMS tab.');
        $this->assertStringContainsString('safeUrl', $composable, 'Prototype links must be filtered through safeUrl.');
    }

    public function test_prototype_components_share_one_css_namespace(): void
    {
        $css = (string) file_get_contents(resource_path('css/pcdr-prototype.css'));

        $this->assertStringContainsString('.pcdr-prototype {', $css);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        $this->assertStringContainsString('@media (prefers-color-scheme: dark)', $css);

        foreach (preg_split('/\R/', $css) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '@')) {
                continue;
            }

            if (preg_match('/^[.#\[a-zA-Z][^{}]*\{/', $trimmed) === 1) {
                $this->assertStringContainsString('.pcdr-prototype', $trimmed, 'Unscoped CSS selector: '.$trimmed);
            }
        }

        foreach (self::PROTOTYPE_COMPONENTS as $component) {
            $path = resource_path('js/Components/RedesignHome/'.$component.'.vue');
            $this->assertFileExists($path);
            $source = (string) file_get_contents($path);

            $this->assertSame(1, substr_count($source, '<template>'), $component.' must have one template root.');
            $this->assertStringNotContainsString('class="pcdr-pro-home', $source, $component.' reintroduces the old CSS namespace.');
            $this->assertStringNotContainsString('public-shell', $source, $component.' reintroduces the shared shell.');
            $this->assertStringNotContainsString('<SiteHeader', $source, $component.' reintroduces the shared header.');
            $this->assertStringNotContainsString('<SiteFooter', $source, $component.' reintroduces the shared footer.');
        }
    }
}
