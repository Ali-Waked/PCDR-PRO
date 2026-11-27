<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\AuditArchive;
use App\Services\AuditArchiveStatusService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class TransitionAuditArchiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $archive = $this->route('archive');

        return $archive instanceof AuditArchive && Gate::allows('manage', $archive);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'to_status' => ['required', 'string', 'in:'.implode(',', array_keys($this->allowedTransitions()))],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $archive = $this->route('archive');
            $to = $this->string('to_status')->toString();

            if (
                $archive instanceof AuditArchive
                && app(AuditArchiveStatusService::class)->requiresReason($archive, $to)
                && trim((string) $this->input('reason')) === ''
            ) {
                $validator->errors()->add('reason', __('ui.audit_archives_page.reason_required'));
            }
        });
    }

    /** @return array<string, string> */
    private function allowedTransitions(): array
    {
        $archive = $this->route('archive');
        if (! $archive instanceof AuditArchive) {
            return [];
        }

        return collect(app(AuditArchiveStatusService::class)->allowedTransitions($archive))
            ->pluck('to', 'to')
            ->all();
    }
}
