<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\ReorderSectionContentRequest;
use App\Http\Requests\Cms\SaveSectionContentRequest;
use App\Http\Requests\Cms\ToggleSectionContentRequest;
use App\Http\Requests\Cms\UpdateOrganizationContentRequest;
use App\Models\HomepageSectionItem;
use App\Services\AuditService;
use App\Services\CmsAccess;
use App\Services\SectionContentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Typed content editor for the predefined CMS sections. Section keys come from
 * the application registry; arbitrary sections and content shapes are rejected
 * by the route constraint and the Form Requests.
 */
final class SectionContentController extends Controller
{
    public function __construct(
        private SectionContentService $content,
        private AuditService $audit,
    ) {}

    public function index(string $locale, string $section): Response
    {
        $this->authorize('homepage.view');

        return Inertia::render('Cms/ContentManagement/Section', [
            'locale' => $locale,
            ...$this->content->index($section, $locale),
        ]);
    }

    public function store(
        string $locale,
        string $section,
        SaveSectionContentRequest $request,
    ): RedirectResponse {
        $item = $this->content->save($section, $request->validated());

        $this->audit->record(
            'cms.section_content.created',
            'homepage_section_item',
            (string) $item->id,
            null,
            $this->auditItem($item),
        );

        return $this->saved($locale);
    }

    public function update(
        string $locale,
        string $section,
        HomepageSectionItem $sectionItem,
        SaveSectionContentRequest $request,
    ): RedirectResponse {
        $before = $this->auditItem($sectionItem);
        $item = $this->content->save($section, $request->validated(), $sectionItem);

        $this->audit->record(
            'cms.section_content.updated',
            'homepage_section_item',
            (string) $item->id,
            $before,
            $this->auditItem($item),
        );

        return $this->saved($locale);
    }

    public function destroy(
        string $locale,
        string $section,
        HomepageSectionItem $sectionItem,
    ): RedirectResponse {
        $before = $this->auditItem($sectionItem);
        $this->content->delete($section, $sectionItem);

        $this->audit->record(
            'cms.section_content.deleted',
            'homepage_section_item',
            (string) $sectionItem->id,
            $before,
        );

        return $this->saved($locale);
    }

    public function toggle(
        string $locale,
        string $section,
        HomepageSectionItem $sectionItem,
        ToggleSectionContentRequest $request,
    ): RedirectResponse {
        $enabled = (bool) $request->validated('enabled');
        $this->content->toggle($section, $sectionItem, $enabled);

        $this->audit->record(
            'cms.section_content.visibility_updated',
            'homepage_section_item',
            (string) $sectionItem->id,
            ['enabled' => ! $enabled],
            ['enabled' => $enabled],
        );

        return $this->saved($locale);
    }

    public function reorder(
        string $locale,
        string $section,
        ReorderSectionContentRequest $request,
    ): RedirectResponse {
        $order = $request->validated('order', []);
        $this->content->reorder($section, $order);

        $this->audit->record('cms.section_content.reordered', 'homepage_section', $section, null, $order);

        return $this->saved($locale);
    }

    public function organization(
        string $locale,
        string $section,
        UpdateOrganizationContentRequest $request,
    ): RedirectResponse {
        $organization = $this->content->saveOrganization($section, $request->validated());

        $this->audit->record(
            'cms.organization.updated',
            'website_organization',
            (string) $organization->id,
        );

        return $this->saved($locale);
    }

    private function authorize(string $permission): void
    {
        $user = auth()->user();

        abort_unless($user !== null && app(CmsAccess::class)->allows($user, $permission), 403);
    }

    private function saved(string $locale): RedirectResponse
    {
        return back()->with('flash', [
            'success' => __('website.cms.saved', [], $locale) ?: 'Changes saved successfully.',
        ]);
    }

    /** @return array<string, mixed> */
    private function auditItem(HomepageSectionItem $item): array
    {
        return [
            'item_key' => $item->item_key,
            'item_type' => $item->item_type,
            'item_id' => $item->item_id,
            'media_id' => $item->media_id,
            'is_published' => (bool) $item->is_published,
            'sort_order' => (int) $item->sort_order,
        ];
    }
}
