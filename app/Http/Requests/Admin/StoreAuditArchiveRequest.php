<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\AuditArchive;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class StoreAuditArchiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage', AuditArchive::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'force' => ['sometimes', 'boolean'],
            'reason' => ['required_if:force,true', 'nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['reason.required_if' => __('ui.audit_archives_page.reason_required')];
    }
}
