<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PreviewEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'locale' => ['nullable', 'string', Rule::in(config('website.locales', ['ar', 'en']))],
            'template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
            'subject' => ['required_without:template_id', 'nullable', 'string', 'max:255'],
            'body' => ['required_without:template_id', 'nullable', 'string', 'max:10000'],
            'images_blocked' => ['sometimes', 'boolean'],
        ];
    }
}
