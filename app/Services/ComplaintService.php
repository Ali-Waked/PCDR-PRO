<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\UploadMedia;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Intake and workflow transitions for complaints.
 *
 * Every mutation is audited without recording complainant contact details,
 * narrative text or private notes. Status changes are validated centrally so
 * callers cannot bypass the configured workflow.
 */
final class ComplaintService
{
    public function __construct(
        private readonly ComplaintReferenceService $references,
        private readonly ComplaintNotificationService $notifications,
        private readonly UploadMedia $uploads,
        private readonly AuditService $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function submit(array $data, Request $request): Complaint
    {
        /** @var list<Media> $media */
        $media = [];

        try {
            foreach ($data['attachments'] ?? [] as $file) {
                $media[] = $this->uploads->handle($file, true, null);
            }

            $complaint = DB::transaction(function () use ($data, $request, $media): Complaint {
                $complaint = Complaint::query()->create([
                    'reference' => $this->references->next(),
                    'category' => $data['category'],
                    'subject' => $data['subject'],
                    'description' => $data['description'],
                    'status' => ComplaintStatus::New->value,
                    'priority' => 'normal',
                    'locale' => $data['preferred_locale'] ?? app()->getLocale(),
                    'related_project' => $data['related_project'] ?? null,
                    'location' => $data['location'] ?? null,
                    'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
                    'complainant_name' => $data['name'] ?? null,
                    'preferred_contact_method' => $data['preferred_contact_method'] ?? null,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'preferred_locale' => $data['preferred_locale'] ?? null,
                    'ip_hash' => hash('sha256', (string) $request->ip()),
                    'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                ]);

                $complaint->statusHistories()->create([
                    'from_status' => null,
                    'to_status' => ComplaintStatus::New->value,
                ]);

                foreach ($media as $record) {
                    $complaint->attachments()->create(['media_id' => $record->id]);
                }

                return $complaint;
            });
        } catch (Throwable $exception) {
            foreach ($media as $record) {
                $this->deleteUpload($record);
            }

            throw $exception;
        }

        $this->audit->record('complaints.submitted', 'complaint', (string) $complaint->id, null, [
            'reference' => $complaint->reference,
            'category' => $complaint->category->value,
            'is_anonymous' => $complaint->isAnonymous(),
            'attachment_count' => count($media),
        ]);

        $this->notifications->submitted($complaint);

        return $complaint;
    }

    public function changeStatus(Complaint $complaint, string $status, User $actor): Complaint
    {
        $target = ComplaintStatus::tryFrom($status);
        if ($target === null) {
            throw ValidationException::withMessages([
                'status' => __('website.cms.complaint_invalid_transition'),
            ]);
        }

        return $this->transition($complaint, $target, $actor, []);
    }

    public function changePriority(Complaint $complaint, string $priority, User $actor): Complaint
    {
        if (! in_array($priority, (array) config('complaints.priorities', []), true)) {
            throw ValidationException::withMessages([
                'priority' => __('website.cms.complaint_invalid_transition'),
            ]);
        }

        $before = ['priority' => $complaint->priority->value];
        $complaint->update(['priority' => $priority]);

        $this->audit->record('complaints.priority_updated', 'complaint', (string) $complaint->id, $before, [
            'reference' => $complaint->reference,
            'priority' => $priority,
        ]);

        return $complaint->refresh();
    }

    public function assign(Complaint $complaint, ?int $userId, User $actor): Complaint
    {
        $before = ['assigned_to' => $complaint->assigned_to];
        $complaint->update(['assigned_to' => $userId]);

        $this->audit->record('complaints.assigned', 'complaint', (string) $complaint->id, $before, [
            'reference' => $complaint->reference,
            'assigned_to' => $userId,
        ]);

        return $complaint->refresh();
    }

    public function addNote(Complaint $complaint, string $body, User $actor): Complaint
    {
        $note = $complaint->notes()->create([
            'user_id' => $actor->id,
            'body' => $body,
            'is_internal' => true,
        ]);

        $this->audit->record('complaints.note_added', 'complaint_notes', (string) $note->id, null, [
            'reference' => $complaint->reference,
            'complaint_id' => $complaint->id,
        ]);

        return $complaint;
    }

    public function resolve(Complaint $complaint, string $details, User $actor): Complaint
    {
        return $this->transition($complaint, ComplaintStatus::Resolved, $actor, [
            'resolution_details' => $details,
        ]);
    }

    /** @return list<string> */
    public function allowedTransitions(Complaint $complaint): array
    {
        $status = $complaint->status;

        return $status instanceof ComplaintStatus ? $status->allowedTransitions() : [];
    }

    /** @param array<string, mixed> $attributes */
    private function transition(Complaint $complaint, ComplaintStatus $target, User $actor, array $attributes): Complaint
    {
        $updated = DB::transaction(function () use ($complaint, $target, $actor, $attributes): Complaint {
            /** @var Complaint $fresh */
            $fresh = Complaint::query()->whereKey($complaint->id)->lockForUpdate()->firstOrFail();
            $current = $fresh->status;

            if (! $current instanceof ComplaintStatus || ! $current->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => __('website.cms.complaint_invalid_transition'),
                ]);
            }

            $values = array_merge($attributes, ['status' => $target->value]);

            if ($target === ComplaintStatus::Resolved) {
                $values['resolved_at'] = now();
            } elseif ($current === ComplaintStatus::Resolved) {
                $values['resolved_at'] = null;
                $values['resolution_details'] = null;
            }

            $fresh->update($values);
            $fresh->statusHistories()->create([
                'from_status' => $current->value,
                'to_status' => $target->value,
                'changed_by' => $actor->id,
            ]);

            return $fresh;
        });

        $this->audit->record(
            $target === ComplaintStatus::Resolved ? 'complaints.resolved' : 'complaints.status_changed',
            'complaint',
            (string) $updated->id,
            ['status' => $complaint->status instanceof ComplaintStatus ? $complaint->status->value : null],
            ['reference' => $updated->reference, 'status' => $target->value],
        );

        $this->notifications->statusChanged($updated, $current->value, $actor);

        return $updated->refresh();
    }

    private function deleteUpload(Media $media): void
    {
        Storage::disk($media->disk)->delete($media->path);
        $media->forceDelete();
    }
}
