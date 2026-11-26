<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class RequestEmailChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'current_password' => ['required', 'current_password'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $user = $this->user();
            $email = $this->input('email');

            if ($user === null || ! is_string($email)) {
                return;
            }

            if (strtolower($email) === strtolower((string) $user->email)) {
                $message = __('ui.account_profile.email_same');
                $validator->errors()->add(
                    'email',
                    is_string($message) && $message !== 'ui.account_profile.email_same'
                        ? $message
                        : 'The new email address must differ from your current email address.',
                );
            }
        }];
    }
}
