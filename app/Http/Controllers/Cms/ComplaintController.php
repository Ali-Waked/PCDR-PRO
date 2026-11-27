<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\AssignComplaintRequest;
use App\Http\Requests\Cms\ResolveComplaintRequest;
use App\Http\Requests\Cms\StoreComplaintNoteRequest;
use App\Http\Requests\Cms\UpdateComplaintPriorityRequest;
use App\Http\Requests\Cms\UpdateComplaintStatusRequest;
use App\Models\Complaint;
use App\Services\CmsAccess;
use App\Services\ComplaintService;
use Illuminate\Http\RedirectResponse;

final class ComplaintController extends Controller
{
    public function __construct(
        private readonly ComplaintService $complaints,
        private readonly CmsAccess $access,
    ) {}

    public function status(UpdateComplaintStatusRequest $request, string $locale, Complaint $complaint): RedirectResponse
    {
        $this->ensureVisible($complaint);

        $this->complaints->changeStatus(
            $complaint,
            (string) $request->validated()['status'],
            $request->user(),
        );

        return back()->with('flash', ['success' => (string) __('website.cms.complaint_status_updated')]);
    }

    public function priority(UpdateComplaintPriorityRequest $request, string $locale, Complaint $complaint): RedirectResponse
    {
        $this->ensureVisible($complaint);

        $this->complaints->changePriority(
            $complaint,
            (string) $request->validated()['priority'],
            $request->user(),
        );

        return back()->with('flash', ['success' => (string) __('website.cms.complaint_priority_updated')]);
    }

    public function assign(AssignComplaintRequest $request, string $locale, Complaint $complaint): RedirectResponse
    {
        $this->ensureVisible($complaint);

        $assignedTo = $request->validated()['assigned_to'] ?? null;

        $this->complaints->assign(
            $complaint,
            $assignedTo === null ? null : (int) $assignedTo,
            $request->user(),
        );

        return back()->with('flash', ['success' => (string) __('website.cms.complaint_assigned')]);
    }

    public function note(StoreComplaintNoteRequest $request, string $locale, Complaint $complaint): RedirectResponse
    {
        $this->ensureVisible($complaint);

        $this->complaints->addNote(
            $complaint,
            (string) $request->validated()['body'],
            $request->user(),
        );

        return back()->with('flash', ['success' => (string) __('website.cms.complaint_note_added')]);
    }

    public function resolve(ResolveComplaintRequest $request, string $locale, Complaint $complaint): RedirectResponse
    {
        $this->ensureVisible($complaint);

        $details = $request->validated()['resolution_details'] ?? null;

        $this->complaints->resolve(
            $complaint,
            $details === null ? null : (string) $details,
            $request->user(),
        );

        return back()->with('flash', ['success' => (string) __('website.cms.complaint_resolved')]);
    }

    private function ensureVisible(Complaint $complaint): void
    {
        $user = auth()->user();

        abort_if($user === null, 403, (string) __('website.cms.complaint_forbidden'));

        if ($this->access->allows($user, 'complaints.view_all')) {
            return;
        }

        abort_unless(
            $this->access->allows($user, 'complaints.view_assigned')
                && (int) $complaint->assigned_to === (int) $user->id,
            403,
            (string) __('website.cms.complaint_forbidden'),
        );
    }
}
