<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cms;

use App\Enums\ComplaintStatus;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintNote;
use App\Models\ComplaintStatusHistory;
use App\Models\ContactMessage;
use App\Models\EmailDeliveryLog;
use App\Models\EmailTemplate;
use App\Models\InquiryNote;
use App\Models\InquiryReply;
use App\Models\User;
use App\Models\VolunteerInquiry;
use App\Services\AuditService;
use App\Services\CmsAccess;
use App\Services\ComplaintService;
use App\Services\InquiryMailService;
use App\Services\TemplateRenderer;
use BackedEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

final class InquiryController extends Controller
{
    private const TYPES = ['contact', 'volunteer'];

    public function __construct(
        private AuditService $audit,
        private InquiryMailService $mail,
        private TemplateRenderer $renderer,
        private ComplaintService $complaints,
    ) {}

    private function authorize(string $permission): void
    {
        abort_unless(auth()->check() && app(CmsAccess::class)->allows(auth()->user(), $permission), 403);
    }

    private function has(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && app(CmsAccess::class)->allows($user, $permission);
    }

    public function index(string $locale, Request $request): Response
    {
        $this->authorize('inquiries.view');

        $status = $this->normalizeStatus($request->string('status')->toString());
        $search = mb_strcut(trim($request->string('search')->toString()), 0, 120);
        $assigned = $request->string('assigned')->toString();
        $restrictToAssigned = ! $this->has('inquiries.view_all') && $this->has('inquiries.view_assigned');
        $statuses = $this->statuses();
        $complaintStatuses = $this->complaintStatuses();
        $canViewComplaints = $this->canViewComplaints();

        /** @var array<string, int> $counts */
        $counts = array_fill_keys(array_merge(['total', 'unassigned'], $statuses, $complaintStatuses), 0);
        /** @var array<string, int> $complaintCounts */
        $complaintCounts = array_fill_keys(array_merge(['total', 'unassigned'], $complaintStatuses), 0);
        $lists = [];
        $pagination = [];

        foreach (self::TYPES as $type) {
            $baseQuery = $this->modelClass($type)::query();

            if ($restrictToAssigned) {
                $baseQuery->where('assigned_to', auth()->id());
            }

            $counts = $this->accumulateCounts($counts, $baseQuery);
            $query = clone $baseQuery;

            if ($status !== '') {
                $query->whereIn('status', $this->statusValuesFor($status));
            }

            if ($search !== '') {
                $query->where(function ($query) use ($type, $search): void {
                    foreach ($this->searchColumns($type) as $column) {
                        $query->orWhere($column, 'like', '%'.$search.'%');
                    }
                    $query->orWhere('reference', 'like', '%'.$search.'%');
                });
            }

            if ($assigned === 'mine') {
                $query->where('assigned_to', auth()->id());
            } elseif ($assigned === 'unassigned') {
                $query->whereNull('assigned_to');
            }

            $paginator = $query->with('assignee:id,name')->latest()
                ->paginate(20, ['*'], $type.'Page')
                ->withQueryString();
            $lists[$type] = $paginator->map(fn (Model $record) => $this->summary($type, $record))->all();
            $pagination[$type] = $this->pagination($paginator);
        }

        if ($canViewComplaints) {
            $complaintQuery = Complaint::query();

            if (! $this->has('complaints.view_all') && $this->has('complaints.view_assigned')) {
                $complaintQuery->where('assigned_to', auth()->id());
            }

            $complaintCounts = $this->accumulateCounts($complaintCounts, $complaintQuery);
            $counts = $this->accumulateCounts($counts, $complaintQuery);
            $query = clone $complaintQuery;

            if ($status !== '') {
                $query->whereIn('status', $this->statusValuesFor($status));
            }

            if ($search !== '') {
                $query->where(function ($query) use ($search): void {
                    foreach (['reference', 'subject', 'description', 'category', 'complainant_name'] as $column) {
                        $query->orWhere($column, 'like', '%'.$search.'%');
                    }
                });
            }

            if ($assigned === 'mine') {
                $query->where('assigned_to', auth()->id());
            } elseif ($assigned === 'unassigned') {
                $query->whereNull('assigned_to');
            }

            $paginator = $query->with('assignee:id,name')->latest()
                ->paginate(20, ['*'], 'complaintPage')
                ->withQueryString();
            $lists['complaints'] = $paginator->map(fn (Complaint $record) => $this->summary('complaint', $record))->all();
            $pagination['complaints'] = $this->pagination($paginator);
        } else {
            $lists['complaints'] = [];
            $pagination['complaints'] = $this->emptyPagination('complaintPage');
        }

        return Inertia::render('Cms/Inquiries/Index', [
            'inquiries' => $lists,
            'pagination' => $pagination,
            'filters' => [
                'status' => $status,
                'search' => $search,
                'assigned' => $assigned,
            ],
            'statuses' => $statuses,
            'canonicalStatuses' => (array) config('cms.inquiry_statuses', ['new', 'in_progress', 'resolved', 'closed']),
            'complaintStatuses' => $complaintStatuses,
            'complaintPriorities' => (array) config('complaints.priorities', []),
            'complaintCategories' => $this->complaintCategories($locale),
            'statusesByType' => [
                'contact' => $statuses,
                'volunteer' => $statuses,
                'complaint' => $complaintStatuses,
            ],
            'complaintCounts' => $complaintCounts,
            'counts' => array_merge($counts, ['complaints' => $complaintCounts]),
            'can' => array_merge([
                'viewAll' => $this->has('inquiries.view_all'),
                'update' => $this->has('inquiries.update'),
                'assign' => $this->has('inquiries.assign'),
                'reply' => $this->has('inquiries.reply'),
                'note' => $this->has('inquiries.add_internal_note'),
            ], $this->complaintPermissions()),
        ]);
    }

    public function show(string $locale, string $type, int $inquiry): Response
    {
        $this->authorize('inquiries.view');
        $record = $this->find($type, $inquiry);
        $this->ensureVisible($record);

        $isComplaint = $type === 'complaint' && $record instanceof Complaint;
        $users = $this->users();
        $contact = null;
        $history = [];
        $attachments = [];
        $allowedTransitions = [];
        $urls = [];

        if ($isComplaint) {
            /** @var Complaint $complaint */
            $complaint = $record;
            $allowedTransitions = $this->complaints->allowedTransitions($complaint);
            $contact = $this->has('complaints.view_contact') ? $this->complaintContact($complaint) : null;

            $history = ComplaintStatusHistory::query()->where('complaint_id', $complaint->id)
                ->with('changedBy:id,name')->oldest()->get()
                ->map(fn (ComplaintStatusHistory $entry) => [
                    'id' => $entry->id,
                    'fromStatus' => $entry->from_status,
                    'toStatus' => $entry->to_status,
                    'changedByName' => $entry->changedBy?->name,
                    'note' => $entry->note,
                    'createdAt' => $entry->created_at?->toISOString(),
                ])->all();

            if ($this->canViewComplaint($complaint)) {
                $attachments = ComplaintAttachment::query()->where('complaint_id', $complaint->id)
                    ->with('media')->latest()->get()
                    ->map(fn (ComplaintAttachment $attachment) => [
                        'id' => $attachment->id,
                        'label' => $attachment->label ?: $attachment->media?->original_name,
                        'originalName' => $attachment->media?->original_name,
                        'sizeBytes' => $attachment->media?->size_bytes,
                        'url' => $attachment->media?->url() ?? '',
                    ])->all();
            }

            foreach (['status', 'priority', 'assign', 'note', 'resolve'] as $action) {
                $routeName = $action === 'note' && Route::has('cms.complaints.notes')
                    ? 'cms.complaints.notes'
                    : 'cms.complaints.'.$action;

                if (Route::has($routeName)) {
                    $urls[$action] = route($routeName, ['locale' => $locale, 'complaint' => $complaint->id]);
                }
            }
        }

        $notes = $isComplaint
            ? ComplaintNote::query()->where('complaint_id', $record->id)
                ->with('author:id,name')->latest()->get()
                ->map(fn (ComplaintNote $note) => [
                    'id' => $note->id,
                    'body' => $note->body,
                    'author' => $note->author?->name,
                    'createdAt' => $note->created_at?->toISOString(),
                ])->all()
            : InquiryNote::query()->where('inquiry_type', $type)->where('inquiry_id', $inquiry)
                ->with('author:id,name')->latest()->get()
                ->map(fn (InquiryNote $note) => [
                    'id' => $note->id,
                    'body' => $note->body,
                    'author' => $note->author?->name,
                    'createdAt' => $note->created_at?->toISOString(),
                ])->all();
        $replies = InquiryReply::query()->where('inquiry_type', $type)->where('inquiry_id', $inquiry)
            ->with('sender:id,name')->latest()->get()
            ->map(fn (InquiryReply $reply) => [
                'id' => $reply->id,
                'subject' => $reply->subject,
                'body' => $reply->body,
                'status' => $reply->status,
                'sender' => $reply->sender?->name,
                'sentAt' => $reply->sent_at?->toISOString(),
                'error' => $reply->error,
            ])->all();
        $deliveryLog = EmailDeliveryLog::query()->where('inquiry_type', $type)->where('inquiry_id', $inquiry)
            ->latest()->get()
            ->map(fn (EmailDeliveryLog $log) => [
                'id' => $log->id,
                'purpose' => $log->purpose,
                'status' => $log->status,
                'locale' => $log->locale,
                'sentAt' => $log->sent_at?->toISOString(),
                'failedAt' => $log->failed_at?->toISOString(),
            ])->all();
        $templates = EmailTemplate::query()->where('is_active', true)->with('translations')
            ->orderBy('sort_order')->get()
            ->map(fn (EmailTemplate $template) => [
                'id' => $template->id,
                'key' => $template->key,
                'name' => $template->translationFor($locale)?->name ?: $template->key,
                'subject' => $template->translationFor($locale)?->subject ?: '',
                'body' => $template->translationFor($locale)?->body ?: '',
            ])->all();

        return Inertia::render('Cms/Inquiries/Show', [
            'type' => $type,
            'inquiry' => $this->details($type, $record, $users),
            'notes' => $notes,
            'replies' => $replies,
            'deliveryLog' => $deliveryLog,
            'templates' => $templates,
            'variables' => $this->renderer->availableVariables(),
            'statuses' => $isComplaint ? $this->complaintStatuses() : $this->statuses(),
            'contact' => $contact,
            'history' => $history,
            'attachments' => $attachments,
            'users' => $users,
            'complaintStatuses' => $this->complaintStatuses(),
            'complaintPriorities' => (array) config('complaints.priorities', []),
            'allowedTransitions' => $allowedTransitions,
            'urls' => $urls,
            'can' => array_merge([
                'update' => $this->has('inquiries.update'),
                'assign' => $this->has('inquiries.assign'),
                'note' => $this->has('inquiries.add_internal_note'),
                'reply' => $this->has('inquiries.reply'),
            ], $this->complaintPermissions()),
        ]);
    }

    public function status(string $locale, string $type, int $inquiry, Request $request): RedirectResponse
    {
        $this->authorize('inquiries.update');
        $record = $this->find($type, $inquiry);
        $this->ensureVisible($record);
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', $this->statuses())],
        ]);
        $before = ['status' => $record->status];
        $record->update(['status' => $this->normalizeStatus($validated['status'])]);
        $this->audit->record('inquiries.status_updated', $type, (string) $inquiry, $before, ['status' => $record->status]);

        return back()->with('flash', ['success' => __('website.cms.inquiry_status_updated')]);
    }

    public function assign(string $locale, string $type, int $inquiry, Request $request): RedirectResponse
    {
        $this->authorize('inquiries.assign');
        $record = $this->find($type, $inquiry);
        $this->ensureVisible($record);
        $validated = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $before = ['assigned_to' => $record->assigned_to];
        $record->update(['assigned_to' => $validated['assigned_to'] ?? null]);
        $this->audit->record('inquiries.assigned', $type, (string) $inquiry, $before, ['assigned_to' => $record->assigned_to]);

        return back()->with('flash', ['success' => __('website.cms.saved')]);
    }

    public function note(string $locale, string $type, int $inquiry, Request $request): RedirectResponse
    {
        $this->authorize('inquiries.add_internal_note');
        $record = $this->find($type, $inquiry);
        $this->ensureVisible($record);
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $note = InquiryNote::create([
            'inquiry_type' => $type,
            'inquiry_id' => $record->id,
            'user_id' => auth()->id(),
            'body' => $validated['body'],
        ]);
        $this->audit->record('inquiries.note_added', 'inquiry_notes', (string) $note->id, extra: [
            'inquiry_type' => $type,
            'inquiry_id' => $record->id,
        ]);

        return back()->with('flash', ['success' => __('website.cms.saved')]);
    }

    public function reply(string $locale, string $type, int $inquiry, Request $request): RedirectResponse
    {
        $this->authorize('inquiries.reply');
        $record = $this->find($type, $inquiry);
        $this->ensureVisible($record);
        $validated = $request->validate([
            'template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'locale' => ['required', 'string', 'in:'.implode(',', config('website.locales', ['ar', 'en']))],
        ]);
        $reply = $this->mail->composeReply(
            $record,
            $type,
            $validated['subject'],
            $validated['message'],
            $validated['locale'],
            $validated['template_id'] ?? null,
            auth()->id(),
        );
        $log = $this->mail->sendReply($reply);
        $this->audit->record('inquiries.replied', $type, (string) $inquiry, extra: [
            'reply_id' => $reply->id,
            'delivery_status' => $log->status,
            'template_key' => $log->template_key,
        ]);

        $flash = $log->status === 'sent'
            ? ['success' => __('website.cms.reply_sent')]
            : ['error' => __('website.cms.reply_delivery_failed')];

        return back()->with('flash', $flash);
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function accumulateCounts(array $counts, Builder $query): array
    {
        $counts['total'] += (int) (clone $query)->count();
        $counts['unassigned'] += (int) (clone $query)->whereNull('assigned_to')->count();

        $grouped = (clone $query)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        foreach ($grouped as $status => $count) {
            $canonical = $this->normalizeStatus((string) $status);
            $counts[$canonical] = ($counts[$canonical] ?? 0) + (int) $count;
        }

        return $counts;
    }

    /** @return list<string> */
    private function statuses(): array
    {
        $statuses = (array) config('cms.inquiry_statuses', ['new', 'in_progress', 'resolved', 'closed']);
        $aliases = (array) config('cms.inquiry_status_aliases', []);

        return array_values(array_unique(array_merge($statuses, array_keys($aliases))));
    }

    private function normalizeStatus(string $status): string
    {
        $aliases = (array) config('cms.inquiry_status_aliases', []);

        return $aliases[$status] ?? $status;
    }

    /** @return list<string> */
    private function statusValuesFor(string $status): array
    {
        $values = [$status];

        foreach ((array) config('cms.inquiry_status_aliases', []) as $alias => $canonical) {
            if ($canonical === $status) {
                $values[] = (string) $alias;
            }
        }

        return array_values(array_unique($values));
    }

    private function modelClass(string $type): string
    {
        return match ($type) {
            'contact' => ContactMessage::class,
            'volunteer' => VolunteerInquiry::class,
            'complaint' => Complaint::class,
            default => abort(404),
        };
    }

    private function find(string $type, int $id): Model
    {
        $class = $this->modelClass($type);
        /** @var Model $record */
        $record = $class::query()->with('assignee:id,name')->findOrFail($id);

        return $record;
    }

    private function ensureVisible(Model $record): void
    {
        if ($record instanceof Complaint) {
            abort_unless($this->canViewComplaint($record), 403, (string) __('website.cms.complaint_forbidden'));

            return;
        }

        if (! $this->has('inquiries.view_all') && $this->has('inquiries.view_assigned')
            && (int) $record->getAttribute('assigned_to') !== (int) auth()->id()) {
            abort(403);
        }
    }

    /** @return list<string> */
    private function searchColumns(string $type): array
    {
        return match ($type) {
            'contact' => ['name', 'email', 'subject', 'category'],
            'volunteer' => ['name', 'email', 'area_of_interest'],
            'complaint' => ['subject', 'description', 'category', 'complainant_name'],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private function summary(string $type, Model $record): array
    {
        if ($type === 'complaint' && $record instanceof Complaint) {
            $category = $this->enumValue($record->getAttribute('category'));
            $anonymous = $record->isAnonymous();

            return [
                'id' => $record->getAttribute('id'),
                'type' => 'complaint',
                'reference' => $record->getAttribute('reference'),
                'label' => $record->getAttribute('subject'),
                'subject' => $record->getAttribute('subject'),
                'senderName' => $anonymous ? null : $record->getAttribute('complainant_name'),
                'context' => $this->complaintCategoryLabel($category),
                'email' => null,
                'status' => $this->enumValue($record->getAttribute('status')),
                'priority' => $this->enumValue($record->getAttribute('priority')),
                'category' => $category,
                'categoryLabel' => $this->complaintCategoryLabel($category),
                'isAnonymous' => $anonymous,
                'assignedTo' => $record->relationLoaded('assignee') ? $record->getRelation('assignee')?->name : null,
                'createdAt' => $record->getAttribute('created_at')?->toISOString(),
            ];
        }

        $subject = $record->getAttribute('subject')
            ?: $record->getAttribute('area_of_interest')
            ?: $record->getAttribute('interest')
            ?: $record->getAttribute('organization')
            ?: $record->getAttribute('name');

        return [
            'id' => $record->getAttribute('id'),
            'type' => $type,
            'reference' => $record->getAttribute('reference'),
            'label' => $subject,
            'subject' => $subject,
            'senderName' => $record->getAttribute('contact_name')
                ?: $record->getAttribute('name')
                ?: $record->getAttribute('organization'),
            'context' => $record->getAttribute('category')
                ?: $record->getAttribute('availability')
                ?: $record->getAttribute('organization'),
            'email' => $record->getAttribute('email'),
            'status' => $record->getAttribute('status'),
            'assignedTo' => $record->relationLoaded('assignee') ? $record->getRelation('assignee')?->name : null,
            'createdAt' => $record->getAttribute('created_at')?->toISOString(),
        ];
    }

    /** @param list<array{id: int, name: string}> $users @return array<string, mixed> */
    private function details(string $type, Model $record, array $users = []): array
    {
        $base = [
            'id' => $record->getAttribute('id'),
            'type' => $type,
            'reference' => $record->getAttribute('reference'),
            'status' => $this->enumValue($record->getAttribute('status')),
            'locale' => $record->getAttribute('locale'),
            'assignedTo' => $record->getAttribute('assigned_to'),
            'assignedToName' => $record->relationLoaded('assignee') ? $record->getRelation('assignee')?->name : null,
            'createdAt' => $record->getAttribute('created_at')?->toISOString(),
        ];

        if ($type === 'complaint' && $record instanceof Complaint) {
            $category = $this->enumValue($record->getAttribute('category'));

            return array_merge($base, [
                'subject' => $record->getAttribute('subject'),
                'description' => $record->getAttribute('description'),
                'category' => $category,
                'categoryLabel' => $this->complaintCategoryLabel($category),
                'priority' => $this->enumValue($record->getAttribute('priority')),
                'relatedProject' => $record->getAttribute('related_project'),
                'location' => $record->getAttribute('location'),
                'isAnonymous' => $record->isAnonymous(),
                'resolvedAt' => $record->getAttribute('resolved_at')?->toISOString(),
                'resolutionDetails' => $record->getAttribute('resolution_details'),
                'users' => $users,
            ]);
        }

        $fields = match ($type) {
            'contact' => [
                'name' => $record->getAttribute('name'),
                'email' => $record->getAttribute('email'),
                'subject' => $record->getAttribute('subject'),
                'message' => $record->getAttribute('message'),
                'category' => $record->getAttribute('category'),
            ],
            'volunteer' => [
                'name' => $record->getAttribute('name'),
                'email' => $record->getAttribute('email'),
                'phone' => $record->getAttribute('phone'),
                'areaOfInterest' => $record->getAttribute('area_of_interest'),
                'availability' => $record->getAttribute('availability'),
                'message' => $record->getAttribute('message'),
            ],
            default => [],
        };

        return array_merge($base, $fields, ['users' => $users]);
    }

    /** @return list<string> */
    private function complaintStatuses(): array
    {
        /** @var list<string> $statuses */
        $statuses = array_values((array) config('complaints.statuses', ComplaintStatus::values()));

        return array_values(array_unique(array_filter(
            $statuses,
            static fn (mixed $status): bool => is_string($status) && ComplaintStatus::tryFrom($status) !== null,
        )));
    }

    /** @return list<array{value: string, label: string}> */
    private function complaintCategories(string $locale): array
    {
        $categories = [];

        foreach ((array) config('complaints.categories', []) as $value) {
            $value = (string) $value;
            $categories[] = ['value' => $value, 'label' => $this->complaintCategoryLabel($value, $locale)];
        }

        return $categories;
    }

    private function canViewComplaints(): bool
    {
        return $this->has('complaints.view_all') || $this->has('complaints.view_assigned');
    }

    private function canViewComplaint(Complaint $complaint): bool
    {
        if ($this->has('complaints.view_all')) {
            return true;
        }

        return $this->has('complaints.view_assigned')
            && (int) $complaint->getAttribute('assigned_to') === (int) auth()->id();
    }

    /** @return array<string, bool> */
    private function complaintPermissions(): array
    {
        return [
            'complaintView' => $this->canViewComplaints(),
            'complaintViewAll' => $this->has('complaints.view_all'),
            'complaintViewAssigned' => $this->has('complaints.view_assigned'),
            'complaintViewContact' => $this->has('complaints.view_contact'),
            'complaintViewAttachments' => $this->canViewComplaints(),
            'complaintUpdate' => $this->has('complaints.update'),
            'complaintAssign' => $this->has('complaints.assign'),
            'complaintNote' => $this->has('complaints.add_internal_note'),
            'complaintResolve' => $this->has('complaints.resolve'),
        ];
    }

    /** @return array<string, mixed>|null */
    private function complaintContact(Complaint $complaint): ?array
    {
        if (! $this->has('complaints.view_contact') || $complaint->isAnonymous()) {
            return null;
        }

        return [
            'name' => $complaint->getAttribute('complainant_name'),
            'email' => $complaint->getAttribute('email'),
            'phone' => $complaint->getAttribute('phone'),
            'preferredContactMethod' => $this->enumValue($complaint->getAttribute('preferred_contact_method')),
            'preferredLocale' => $complaint->getAttribute('preferred_locale'),
        ];
    }

    /** @return list<array{id: int, name: string}> */
    private function users(): array
    {
        /** @var list<array{id: int, name: string}> $users */
        $users = User::query()->orderBy('name')->limit(200)->get(['id', 'name'])->all();

        return $users;
    }

    private function complaintCategoryLabel(string $category, ?string $locale = null): string
    {
        $key = 'website.complaints.categories.'.$category;
        $label = __($key, [], $locale ?? app()->getLocale());

        return is_string($label) && $label !== $key ? $label : str_replace('_', ' ', $category);
    }

    private function enumValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if (is_object($value) && method_exists($value, 'value')) {
            return (string) $value->value;
        }

        return $value === null ? '' : (string) $value;
    }

    /** @return array{currentPage: int, lastPage: int, perPage: int, total: int, from: int|null, to: int|null, pageName: string} */
    private function emptyPagination(string $pageName): array
    {
        return [
            'currentPage' => 1,
            'lastPage' => 1,
            'perPage' => 20,
            'total' => 0,
            'from' => null,
            'to' => null,
            'pageName' => $pageName,
        ];
    }

    /** @return array{currentPage: int, lastPage: int, perPage: int, total: int, from: int|null, to: int|null, pageName: string} */
    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'pageName' => $paginator->getPageName(),
        ];
    }
}
