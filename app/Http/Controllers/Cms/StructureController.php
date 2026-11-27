<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\ReorderWebsiteSectionsRequest;
use App\Http\Requests\Cms\SetWebsiteVisibilityRequest;
use App\Models\HomepageSection;
use App\Models\WebsitePage;
use App\Services\AuditService;
use App\Services\CmsAccess;
use App\Services\WebsiteStructureService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Single entry point for predefined page and section visibility. This
 * controller never writes content records.
 */
final class StructureController extends Controller
{
    public function __construct(
        private WebsiteStructureService $structure,
        private AuditService $audit,
    ) {}

    public function index(string $locale): Response
    {
        $this->authorize('website_structure.view');

        return Inertia::render('Cms/ContentManagement/Index', [
            ...$this->structure->structure($locale),
            'can' => [
                'view' => $this->allowed('website_structure.view'),
                'update' => $this->allowed('website_structure.update'),
                'contentView' => $this->allowed('homepage.view'),
                'contentUpdate' => $this->allowed('homepage.update'),
            ],
        ]);
    }

    public function togglePage(
        string $locale,
        SetWebsiteVisibilityRequest $request,
        WebsitePage $websitePage,
    ): RedirectResponse {
        $enabled = (bool) $request->validated('enabled');
        $this->structure->setPageVisibility($websitePage, $enabled);
        $this->audit->record(
            'cms.website_page.visibility_updated',
            'website_page',
            (string) $websitePage->key,
            null,
            ['enabled' => $enabled],
        );

        return back()->with('flash', ['success' => __('website.cms.saved', [], $locale)]);
    }

    public function toggleSection(
        string $locale,
        SetWebsiteVisibilityRequest $request,
        HomepageSection $homepageSection,
    ): RedirectResponse {
        $enabled = (bool) $request->validated('enabled');
        $this->structure->setSectionVisibility($homepageSection, $enabled);
        $this->audit->record(
            'cms.website_section.visibility_updated',
            'homepage_section',
            (string) $homepageSection->key,
            null,
            ['enabled' => $enabled],
        );

        return back()->with('flash', ['success' => __('website.cms.saved', [], $locale)]);
    }

    public function reorder(ReorderWebsiteSectionsRequest $request): RedirectResponse
    {
        $order = $request->validated('order', []);
        $this->structure->reorderSections($order);
        $this->audit->record('cms.website_sections.reordered', 'homepage_section', 'sections');

        return back()->with('flash', ['success' => __('website.cms.saved')]);
    }

    private function authorize(string $permission): void
    {
        abort_unless($this->allowed($permission), 403);
    }

    private function allowed(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && app(CmsAccess::class)->allows($user, $permission);
    }
}
