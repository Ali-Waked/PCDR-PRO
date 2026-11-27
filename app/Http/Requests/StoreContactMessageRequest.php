<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['required', 'string', 'min:3', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:10000'],
            'honeypot' => ['nullable', 'string', 'size:0'],
            'consent' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', 'string', Rule::in(array_values((array) config('website.locales', ['ar', 'en'])))],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'name',
            'email' => 'email',
            'subject' => 'subject',
            'message' => 'message',
            'honeypot' => 'honeypot',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'honeypot.size' => 'This form could not be submitted.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $locale = $this->route('locale');
        if (! $this->exists('locale') && is_string($locale)) {
            $this->merge(['locale' => $locale]);
        }
    }
}
