<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class CmsContentManagementUiTest extends TestCase
{
    public function test_workspace_uses_real_structure_props_and_accessible_responsive_controls(): void
    {
        $source = $this->contentSource('Index.vue');

        foreach ([
            'pages: { type: Array, default: () => [] }',
            'sections: { type: Array, default: () => [] }',
            'can: { type: Object, default: () => ({}) }',
            'section.recordCount',
            'section.manageUrl',
            'aria-label',
            'role="group"',
            'aria-pressed',
            '@media (max-width: 960px)',
            '@media (max-width: 640px)',
            'padding-inline',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source, $needle);
        }

        foreach (['window.alert(', 'window.confirm(', 'alert('] as $needle) {
            $this->assertStringNotContainsString($needle, $source, $needle);
        }
    }

    public function test_section_component_dispatches_to_dedicated_and_generic_editors(): void
    {
        $dispatcher = $this->contentSource('Section.vue');

        foreach ([
            "import HeroSection from './HeroSection.vue'",
            "import TickerSection from './TickerSection.vue'",
            "import DefaultSection from './DefaultSection.vue'",
            "props.section?.content === 'slides'",
            "props.section?.content === 'announcements'",
            '<component :is="editor" v-bind="$props" />',
        ] as $needle) {
            $this->assertStringContainsString($needle, $dispatcher, $needle);
        }
    }

    public function test_hero_editor_uses_media_library_and_backend_slide_membership(): void
    {
        $source = $this->contentSource('HeroSection.vue');

        foreach ([
            'ContentMediaField',
            'item.isSlide === true',
            'form.post(base, options)',
            'form.put(`${base}/${editing.value}`, options)',
            'router.delete(',
            'router.patch(',
            'router.post(',
            'aria-describedby="hero-schedule-help"',
            'اترك التاريخ فارغاً ليبدأ العرض من اليوم أو يستمر دون تاريخ انتهاء.',
            '@media (max-width: 480px)',
            'inset-inline-end',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source, $needle);
        }
    }

    public function test_ticker_editor_persists_labels_and_only_links_published_articles(): void
    {
        $source = $this->contentSource('TickerSection.vue');

        foreach ([
            'props.section.presentation?.translations?.ar?.tickerLabel',
            'ticker_label',
            'presentationForm.put(',
            '/presentation',
            'source.status !== \'published\'',
            'source.publishedAt',
            'form.post(base, options)',
            'form.put(`${base}/${editing.value}`, options)',
            'aria-describedby="ticker-schedule-help"',
            'اترك التاريخ فارغاً ليبدأ العرض من اليوم أو يستمر دون تاريخ انتهاء.',
            '@media (max-width: 480px)',
            'border-inline-start',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source, $needle);
        }
    }

    public function test_generic_editor_keeps_the_same_schedule_contract(): void
    {
        $source = $this->contentSource('DefaultSection.vue');

        foreach ([
            'اترك التاريخ فارغاً ليبدأ العرض من اليوم أو يستمر دون تاريخ انتهاء.',
            'form.assign(values)',
            'form.defaults(values)',
            'form.reset()',
            'aria-describedby="section-schedule-help"',
            'pc-form-grid',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source, $needle);
        }

        $styles = (string) File::get(resource_path('css/dashboard.css'));
        $this->assertStringContainsString('@media (max-width: 820px)', $styles);
        $this->assertStringContainsString('.pc-form-grid', $styles);
    }

    public function test_public_hero_trusts_backend_slide_membership(): void
    {
        $this->assertSame('slides', config('cms_structure.sections.hero.content'));
        $this->assertSame('announcements', config('cms_structure.sections.ticker.content'));
        $this->assertSame('links', config('cms_structure.sections.news.content'));

        $source = (string) File::get(resource_path('js/Components/RedesignHome/ProHeroCarousel.vue'));
        $this->assertStringContainsString('item.slide === true', $source);
        $this->assertStringNotContainsString('startsWith(\'slide-\')', $source);
    }

    private function contentSource(string $file): string
    {
        return (string) File::get(resource_path('js/Pages/Cms/ContentManagement/'.$file));
    }
}
