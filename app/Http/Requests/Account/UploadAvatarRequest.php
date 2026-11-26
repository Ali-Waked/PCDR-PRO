<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

final class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'avatar.required' => (string) __('ui.account_profile.avatar_invalid'),
            'avatar.file' => (string) __('ui.account_profile.avatar_invalid'),
            'avatar.mimetypes' => (string) __('ui.account_profile.avatar_invalid'),
            'avatar.max' => (string) __('ui.account_profile.avatar_too_large'),
        ];
    }
}
