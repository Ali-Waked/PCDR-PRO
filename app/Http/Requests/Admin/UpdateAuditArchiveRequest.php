<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\AuditArchive;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UpdateAuditArchiveRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120', 'regex:/^[\pL\pN._ -]+$/u'],
        ];
    }
}
