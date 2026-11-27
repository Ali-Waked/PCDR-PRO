<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAuditArchiveRequest;
use App\Http\Requests\Admin\TransitionAuditArchiveRequest;
use App\Http\Requests\Admin\UpdateAuditArchiveRequest;
use App\Jobs\GenerateAuditArchiveJob;
use App\Models\AuditArchive;
use App\Models\AuditCleanupRun;
use App\Services\AuditArchiveAutomationService;
use App\Services\AuditArchiveService;
use App\Services\AuditArchiveStatusService;
use App\Services\AuditLifecycleNotifier;
use App\Services\AuditRetentionService;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class AuditArchiveController extends Controller
{
    public function __construct(
        private readonly AuditArchiveService $archives,
        private readonly AuditArchiveStatusService $statuses,
        private readonly AuditArchiveAutomationService $automation,
        private readonly AuditRetentionService $retention,
        private readonly AuditLifecycleNotifier $notifier,
        private readonly AuditService $audit,
    ) {}

    public function index(string $locale, Request $request): Response
    {
        Gate::authorize('viewAny', AuditArchive::class);

        $statuses = [
            AuditArchive::STATUS_PENDING,
            AuditArchive::STATUS_PROCESSING,
            AuditArchive::STATUS_VERIFIED,
            AuditArchive::STATUS_FAILED,
            AuditArchive::STATUS_PRUNED,
        ];
        $sources = [AuditArchive::SOURCE_MANUAL, AuditArchive::SOURCE_AUTOMATIC];
        $status = $request->string('status')->trim()->toString();
        $status = in_array($status, $statuses, true) ? $status : '';

        $month = $request->string('month')->trim()->toString();
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1 ? $month : '';

        $year = $request->string('year')->trim()->toString();
        $year = preg_match('/^\d{4}$/', $year) === 1 ? $year : '';

        $source = $request->string('source')->trim()->toString();
        $source = in_array($source, $sources, true) ? $source : '';

        $sort = $request->string('sort')->trim()->toString();
        $sortMap = [
            'period' => 'period_start',
            'created' => 'created_at',
            'status' => 'status',
            'records' => 'record_count',
            'size' => 'file_size',
        ];
        $sort = array_key_exists($sort, $sortMap) ? $sort : 'period';
        $direction = $request->string('direction')->trim()->toString() === 'asc' ? 'asc' : 'desc';

        $perPage = (int) $request->integer('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 50], true) ? $perPage : 20;

        $search = $request->string('search')->trim()->toString();

        $archiveQuery = AuditArchive::query()
            ->with('initiator:id,name')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($source !== '', fn ($query) => $query->where('source', $source))
            ->when($month !== '', fn ($query) => $query->where('period_key', 'like', $month.'%'))
            ->when($year !== '', fn ($query) => $query
                ->where('period_start', '>=', $year.'-01-01 00:00:00')
                ->where('period_start', '<', ((int) $year + 1).'-01-01 00:00:00'))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', '%'.$search.'%')
                        ->orWhere('period_key', 'like', '%'.$search.'%')
                        ->orWhere('status', 'like', '%'.$search.'%')
                        ->orWhereHas('initiator', fn ($userQuery) => $userQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy($sortMap[$sort], $direction)
            ->orderByDesc('id');

        $archives = $archiveQuery
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (AuditArchive $archive): array => $this->archives->describeArchive($archive));

        $years = AuditArchive::query()
            ->pluck('period_key')
            ->map(fn ($key) => substr((string) $key, 0, 4))
            ->filter(fn ($value) => preg_match('/^\d{4}$/', (string) $value) === 1)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        $runs = AuditCleanupRun::query()
            ->with('archive:id,period_key')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (AuditCleanupRun $run): array => $this->retention->describeRun($run))
            ->all();

        $holds = $this->retention->activeHolds()
            ->map(fn ($hold): array => $this->retention->describeHold($hold))
            ->all();

        $this->audit->record('audit_archives.view', 'audit_archives', null, null, [
            'status' => $status,
            'month' => $month,
            'year' => $year,
            'source' => $source,
            'search' => $search !== '' ? 'present' : null,
            'description' => 'Audit archive registry viewed',
        ]);

        return Inertia::render('Admin/AuditArchives/Index', [
            'archives' => $archives,
            'cleanupRuns' => $runs,
            'holds' => $holds,
            'filters' => [
                'status' => $status,
                'month' => $month,
                'year' => $year,
                'source' => $source,
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'statuses' => $statuses,
            'sources' => $sources,
            'years' => $years,
            'retention' => $this->retention->configuration(),
            'automation' => $this->automation->status(),
            'can' => [
                'view' => Gate::allows('viewAny', AuditArchive::class),
                'download' => Gate::allows('download', AuditArchive::class),
                'manage' => Gate::allows('manage', AuditArchive::class),
                'cleanup' => Gate::allows('cleanup', AuditArchive::class),
            ],
        ]);
    }

    public function show(string $locale, AuditArchive $archive): JsonResponse
    {
        Gate::authorize('view', $archive);

        $transitions = $archive->statusTransitions()
            ->with('actor:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($transition): array => [
                'id' => $transition->id,
                'from_status' => $transition->from_status,
                'to_status' => $transition->to_status,
                'actor' => $transition->actor?->name ?? ($transition->actor_type === 'system' ? 'system' : '—'),
                'reason' => $transition->reason,
                'created_at' => $transition->created_at?->toISOString(),
            ])
            ->all();

        return response()->json([
            'archive' => $this->archives->describeArchive($archive->loadMissing('initiator:id,name')),
            'transitions' => $transitions,
        ]);
    }

    public function store(string $locale, StoreAuditArchiveRequest $request): RedirectResponse
    {
        Gate::authorize('manage', AuditArchive::class);

        /** @var array{month?: ?string, force?: bool, reason?: ?string} $validated */
        $validated = $request->validated();
        $month = is_string($validated['month'] ?? null) && $validated['month'] !== '' ? $validated['month'] : null;
        $force = (bool) ($validated['force'] ?? false);
        $user = $request->user();
        $actor = $user;
        $initiatedBy = $user?->id;

        try {
            $archive = $this->archives->prepareArchive(
                $month,
                $initiatedBy,
                $force,
                AuditArchive::SOURCE_MANUAL,
                null,
                $actor,
                $validated['reason'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return back()->with('flash', ['error' => $exception->getMessage()])->setStatusCode(303);
        }

        try {
            GenerateAuditArchiveJob::dispatch(
                $archive->id,
                $month,
                $initiatedBy,
                AuditArchive::SOURCE_MANUAL,
                (int) $archive->attempts + 1,
            );
        } catch (Throwable $exception) {
            $this->archives->markJobFailure($archive, $exception->getMessage());

            return back()->with('flash', ['error' => __('ui.audit_archives_page.queue_failed')])->setStatusCode(303);
        }

        $this->audit->record('audit_archives.queued', 'audit_archives', (string) $archive->id, null, [
            'period' => $archive->period_key,
            'attempt' => (int) $archive->attempts + 1,
            'description' => 'Audit archive generation queued',
        ]);

        return back()->with('flash', [
            'success' => __('ui.audit_archives_page.queued_success'),
        ]);
    }

    public function update(string $locale, AuditArchive $archive, UpdateAuditArchiveRequest $request): RedirectResponse
    {
        Gate::authorize('manage', $archive);

        /** @var array{name: string} $validated */
        $validated = $request->validated();
        $this->archives->updateName($archive, $validated['name'], $request->user());

        return back()->with('flash', ['success' => __('ui.audit_archives_page.updated_success')]);
    }

    public function transition(string $locale, AuditArchive $archive, TransitionAuditArchiveRequest $request): RedirectResponse
    {
        Gate::authorize('manage', $archive);

        /** @var array{to_status: string, reason?: ?string} $validated */
        $validated = $request->validated();
        $reason = trim((string) ($validated['reason'] ?? ''));

        try {
            if ($validated['to_status'] === AuditArchive::STATUS_VERIFIED) {
                $inspection = $this->archives->verifyArchive($archive, true, $request->user(), $reason);
                $this->notifier->archiveVerified($archive->refresh());
                $message = __('ui.audit_archives_page.verify_success', ['count' => $inspection['record_count']]);
            } else {
                $this->statuses->transition($archive, $validated['to_status'], $request->user(), $reason, [
                    'operation' => 'manual_transition',
                ]);
                $message = __('ui.audit_archives_page.transition_success');
            }
        } catch (Throwable $exception) {
            return back()->with('flash', ['error' => $exception->getMessage()])->setStatusCode(303);
        }

        return back()->with('flash', ['success' => $message]);
    }

    public function verify(string $locale, AuditArchive $archive, Request $request): RedirectResponse
    {
        Gate::authorize('manage', $archive);

        $reason = $request->string('reason')->trim()->toString();
        if ($archive->status === AuditArchive::STATUS_FAILED && $reason === '') {
            return back()->with('flash', ['error' => __('ui.audit_archives_page.reason_required')])->setStatusCode(303);
        }

        try {
            $inspection = $this->archives->verifyArchive($archive, true, $request->user(), $reason !== '' ? $reason : null);
            $this->notifier->archiveVerified($archive->refresh());
        } catch (Throwable $exception) {
            return back()->with('flash', ['error' => $exception->getMessage()])->setStatusCode(303);
        }

        return back()->with('flash', [
            'success' => __('ui.audit_archives_page.verify_success', ['count' => $inspection['record_count']]),
        ]);
    }

    public function download(string $locale, AuditArchive $archive): StreamedResponse|RedirectResponse
    {
        Gate::authorize('download', $archive);

        if ($archive->status !== AuditArchive::STATUS_VERIFIED) {
            return back()->with('flash', ['error' => __('ui.audit_archives_page.download_unavailable')])->setStatusCode(303);
        }

        $disk = Storage::disk((string) config('audit_archive.disk', 'private'));
        if (! $disk->exists($archive->file_path)) {
            return back()->with('flash', ['error' => __('ui.audit_archives_page.download_missing')])->setStatusCode(404);
        }

        try {
            $this->archives->verifyArchive($archive, false);
        } catch (RuntimeException $exception) {
            return back()->with('flash', ['error' => $exception->getMessage()])->setStatusCode(303);
        }

        $stream = $disk->readStream($archive->file_path);
        if ($stream === false) {
            return back()->with('flash', ['error' => __('ui.audit_archives_page.download_missing')])->setStatusCode(404);
        }

        $filename = str_ends_with((string) $archive->name, '.zip')
            ? (string) $archive->name
            : 'audit-logs-'.$archive->period_key.'.zip';

        $this->audit->record('audit_archives.download', 'audit_archives', (string) $archive->id, null, [
            'period' => $archive->period_key,
            'description' => 'Audit archive downloaded',
        ]);

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $filename, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
