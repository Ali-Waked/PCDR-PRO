<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

final class RevokeSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $reference = $this->route('reference');
        if (is_string($reference) && ! $this->has('reference')) {
            $this->merge(['reference' => $reference]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'size:32', 'regex:/^[A-Za-z0-9]{32}$/'],
            'current_password' => ['required', 'current_password'],
        ];
    }
}
