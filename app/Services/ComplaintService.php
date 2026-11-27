<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\UploadMedia;
use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
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
        $files = $this->attachmentFiles($data);

        /** @var list<Media> $media */
        $media = [];

        try {
            $complaint = DB::transaction(function () use ($data, $request, $files, &$media): Complaint {
                foreach ($files as $file) {
                    try {
                        $media[] = $this->uploads->handle($file, true, null);
                    } catch (InvalidArgumentException $exception) {
                        throw ValidationException::withMessages([
                            'attachments' => __('website.cms.complaint_attachment_invalid'),
                        ]);
                    }
                }

                $anonymous = (bool) ($data['is_anonymous'] ?? false);
                $contactMethod = $data['contact_method'] ?? $data['preferred_contact_method'] ?? null;
                $locale = $data['locale'] ?? $data['preferred_locale'] ?? app()->getLocale();

                $complaint = Complaint::query()->create([
                    'reference' => $this->references->next(),
                    'category' => $data['category'],
                    'subject' => $data['subject'],
                    'description' => $data['body'] ?? $data['description'] ?? '',
                    'status' => ComplaintStatus::New->value,
                    'priority' => ComplaintPriority::Normal->value,
                    'locale' => $locale,
                    'related_project' => $data['related_project'] ?? null,
                    'location' => $data['location'] ?? null,
                    'is_anonymous' => $anonymous,
                    'complainant_name' => $anonymous ? null : ($data['name'] ?? null),
                    'preferred_contact_method' => $anonymous ? null : $contactMethod,
                    'email' => $anonymous ? null : ($data['email'] ?? null),
                    'phone' => $anonymous ? null : ($data['phone'] ?? null),
                    'preferred_locale' => $anonymous ? null : ($data['preferred_locale'] ?? $locale),
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
        if (ComplaintPriority::tryFrom($priority) === null) {
            throw ValidationException::withMessages([
                'priority' => __('website.cms.complaint_invalid_transition'),
            ]);
        }

        $before = ['priority' => $complaint->priority instanceof ComplaintPriority
            ? $complaint->priority->value
            : (string) $complaint->priority];
        $complaint->update(['priority' => $priority]);

        $this->audit->record('complaints.priority_updated', 'complaint', (string) $complaint->id, $before, [
            'reference' => $complaint->reference,
            'priority' => $priority,
            'actor_id' => $actor->id,
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
            'actor_id' => $actor->id,
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

        $this->audit->record('complaints.note_added', 'complaint', (string) $complaint->id, null, [
            'reference' => $complaint->reference,
            'note_id' => $note->id,
            'actor_id' => $actor->id,
        ]);

        return $complaint;
    }

    public function resolve(Complaint $complaint, ?string $details, User $actor): Complaint
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
        $previousStatus = $complaint->status instanceof ComplaintStatus ? $complaint->status->value : null;

        $updated = DB::transaction(function () use ($complaint, $target, $actor, $attributes, &$previousStatus): Complaint {
            /** @var Complaint $fresh */
            $fresh = Complaint::query()->whereKey($complaint->id)->lockForUpdate()->firstOrFail();
            $current = $fresh->status;

            if (! $current instanceof ComplaintStatus || ! $current->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => __('website.cms.complaint_invalid_transition'),
                ]);
            }

            $previousStatus = $current->value;
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
            ['status' => $previousStatus],
            [
                'reference' => $updated->reference,
                'status' => $target->value,
                'actor_id' => $actor->id,
            ],
        );

        $this->notifications->statusChanged($updated, (string) $previousStatus);

        return $updated->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<UploadedFile>
     */
    private function attachmentFiles(array $data): array
    {
        /** @var mixed $raw */
        $raw = $data['attachments'] ?? [];

        if (! is_array($raw)) {
            throw ValidationException::withMessages([
                'attachments' => __('website.cms.complaint_attachment_invalid'),
            ]);
        }

        $files = array_values(array_filter($raw, static fn (mixed $file): bool => $file instanceof UploadedFile));

        if (count($files) !== count($raw)) {
            throw ValidationException::withMessages([
                'attachments' => __('website.cms.complaint_attachment_invalid'),
            ]);
        }

        $maxFiles = (int) config('complaints.attachments.max_files', 0);
        if (count($files) > $maxFiles) {
            throw ValidationException::withMessages([
                'attachments' => __('website.cms.complaint_too_many_attachments'),
            ]);
        }

        return $files;
    }

    private function deleteUpload(Media $media): void
    {
        Storage::disk($media->disk)->delete($media->path);
        $media->forceDelete();
    }
}
