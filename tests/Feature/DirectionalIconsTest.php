<?php

namespace Tests\Feature;

use Tests\TestCase;

final class DirectionalIconsTest extends TestCase
{
    public function test_ui_icon_maps_forward_and_back_arrows_to_document_direction(): void
    {
        $icon = (string) file_get_contents(resource_path('js/Components/Dashboard/UiIcon.vue'));
        $pagination = (string) file_get_contents(resource_path('js/Components/Pagination.vue'));
        $header = (string) file_get_contents(resource_path('js/Components/Dashboard/PageHeader.vue'));

        $this->assertStringContainsString("import { useI18n } from '../../composables/useI18n'", $icon);
        $this->assertStringContainsString('const { isRtl } = useI18n()', $icon);

        foreach ([
            "arrowLeft: ['M19 12H5', 'm11 6-6 6 6 6']",
            'arrowForward: (rtl) => rtl ? \'arrowLeft\' : \'arrowRight\'',
            'arrowBack: (rtl) => rtl ? \'arrowRight\' : \'arrowLeft\'',
            'chevronForward: (rtl) => rtl ? \'chevronLeft\' : \'chevronRight\'',
            'chevronBack: (rtl) => rtl ? \'chevronRight\' : \'chevronLeft\'',
            'const resolvedName = computed(() => directionAliases[props.name]?.(isRtl.value) ?? props.name)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $icon);
        }

        $this->assertStringNotContainsString('isRtl ?', $pagination);
        $this->assertSame(2, substr_count($pagination, 'name="chevronBack"'));
        $this->assertSame(2, substr_count($pagination, 'name="chevronForward"'));
        $this->assertStringContainsString('name="chevronForward"', $header);
    }

    public function test_navigation_arrows_use_semantic_direction_aliases(): void
    {
        $forwardFiles = [
            'js/Pages/Dashboard.vue',
            'js/Pages/Cms/Inquiries/Show.vue',
            'js/Pages/Cms/Content/Preview.vue',
            'js/Pages/Cms/Content/Edit.vue',
            'js/Components/Dashboard/WorkflowAlert.vue',
            'js/Components/Dashboard/AccountActivityWidget.vue',
            'js/Components/EmailTemplates/TemplateWizard.vue',
        ];

        foreach ($forwardFiles as $file) {
            $this->assertStringContainsString('name="arrowForward"', (string) file_get_contents(resource_path($file)), $file);
        }

        $backFiles = [
            'js/Pages/Admin/Users/Show.vue' => 'name="chevronBack"',
            'js/Components/EmailTemplates/TemplateWizard.vue' => 'name="chevronBack"',
            'js/Components/Phase5/Phase5Shell.vue' => 'name="arrowBack"',
            'js/Pages/Operations/ResourceForm.vue' => 'name="arrowBack"',
        ];

        foreach ($backFiles as $file => $needle) {
            $source = (string) file_get_contents(resource_path($file));
            $this->assertStringContainsString($needle, $source, $file);
            $this->assertStringNotContainsString('←', $source, $file);
        }
    }

    public function test_public_and_error_page_arrows_flip_in_rtl(): void
    {
        $sprite = (string) file_get_contents(resource_path('js/Components/RedesignHome/ProPrototypeSprite.vue'));
        $impact = (string) file_get_contents(resource_path('js/Components/RedesignHome/ProImpact.vue'));
        $actions = (string) file_get_contents(resource_path('views/errors/partials/actions.blade.php'));
        $layout = (string) file_get_contents(resource_path('views/errors/layout.blade.php'));

        $this->assertStringContainsString('id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"', $sprite);
        $this->assertStringContainsString('<svg class="directional-flip" aria-hidden="true"><use href="#i-arrow" /></svg>', $impact);
        $this->assertStringContainsString('<svg class="directional-flip" viewBox="0 0 24 24"', $actions);
        $this->assertStringContainsString("[dir='rtl'] .pcdr-error .directional-flip {", $layout);
        $this->assertStringContainsString('transform: scaleX(-1);', $layout);
    }
}
