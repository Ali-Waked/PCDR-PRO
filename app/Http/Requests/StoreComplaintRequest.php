<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintContactMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $attachmentRules = $this->attachmentRules();
        $identityRequired = $this->requiresIdentity();
        $contactMethod = $this->string('contact_method')->lower()->toString();

        return [
            'is_anonymous' => ['nullable', 'boolean'],
            'category' => ['required', 'string', Rule::in(ComplaintCategory::values())],
            'subject' => ['required', 'string', 'min:5', 'max:255'],
            'body' => ['required', 'string', 'min:20', 'max:10000'],
            'related_project' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],

            'name' => $identityRequired
                ? ['required', 'string', 'max:255']
                : ['nullable', 'string', 'size:0'],
            'contact_method' => $identityRequired
                ? ['required', 'string', Rule::in(ComplaintContactMethod::values())]
                : ['nullable', 'string', 'size:0'],
            'email' => $identityRequired && $contactMethod !== ComplaintContactMethod::Phone->value
                ? ['required', 'email:rfc', 'max:255']
                : ($this->isAnonymous()
                    ? ['nullable', 'size:0']
                    : ['nullable', 'email:rfc', 'max:255']),
            'phone' => $identityRequired && $contactMethod === ComplaintContactMethod::Phone->value
                ? ['required', 'string', 'max:50']
                : ($this->isAnonymous()
                    ? ['nullable', 'size:0']
                    : ['nullable', 'string', 'max:50']),

            'locale' => ['required', 'string', Rule::in(array_values((array) config('website.locales', ['ar', 'en'])))],
            'preferred_locale' => ['sometimes', 'nullable', 'string', Rule::in(array_values((array) config('website.locales', ['ar', 'en'])))],

            'attachments' => $attachmentRules['attachments'],
            'attachments.*' => $attachmentRules['attachments.*'],
            'honeypot' => ['nullable', 'string', 'size:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => (string) __('website.ui.complaints.name'),
            'email' => (string) __('website.ui.complaints.email'),
            'phone' => (string) __('website.ui.complaints.phone'),
            'category' => (string) __('website.ui.complaints.category'),
            'subject' => (string) __('website.ui.complaints.subject'),
            'body' => (string) __('website.ui.complaints.description'),
            'contact_method' => (string) __('website.ui.complaints.preferred_contact_method'),
            'locale' => (string) __('website.ui.complaints.preferred_locale'),
            'attachments' => (string) __('website.ui.complaints.attachments'),
            'attachments.*' => (string) __('website.ui.complaints.attachments'),
            'honeypot' => 'honeypot',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'honeypot.size' => (string) __('website.cms.complaint_forbidden'),
            'attachments.max' => (string) __('website.cms.complaint_too_many_attachments'),
            'attachments.*.mimetypes' => (string) __('website.cms.complaint_attachment_invalid'),
            'attachments.*.extensions' => (string) __('website.cms.complaint_attachment_invalid'),
            'attachments.*.max' => (string) __('website.cms.complaint_attachment_too_large'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $aliases = [
            'body' => 'description',
            'contact_method' => 'preferred_contact_method',
            'locale' => 'preferred_locale',
        ];

        /** @var array<string, mixed> $merge */
        $merge = [];

        foreach ($aliases as $canonical => $legacy) {
            if (! $this->exists($canonical) && $this->exists($legacy)) {
                $merge[$canonical] = $this->input($legacy);
            }
        }

        if ($this->isAnonymous()) {
            $merge['name'] = '';
            $merge['email'] = '';
            $merge['phone'] = '';
            $merge['contact_method'] = '';
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    private function isAnonymous(): bool
    {
        return $this->boolean('is_anonymous');
    }

    private function requiresIdentity(): bool
    {
        return ! $this->isAnonymous();
    }

    /** @return array<string, list<string>> */
    private function attachmentRules(): array
    {
        /** @var list<string> $mimes */
        $mimes = array_values((array) config('complaints.attachments.allowed_mimes', []));
        /** @var list<string> $extensions */
        $extensions = array_values((array) config('complaints.attachments.allowed_extensions', []));
        $maxFiles = max(0, (int) config('complaints.attachments.max_files', 0));
        $maxSize = max(0, (int) config('complaints.attachments.max_file_size_kb', 0));

        if (! (bool) config('complaints.attachments.enabled', false)) {
            return [
                'attachments' => ['nullable', 'array', 'size:0'],
                'attachments.*' => ['prohibited'],
            ];
        }

        $fileRules = ['file'];
        if ($mimes !== []) {
            $fileRules[] = 'mimetypes:'.implode(',', $mimes);
        }
        if ($extensions !== []) {
            $fileRules[] = 'extensions:'.implode(',', $extensions);
        }
        if ($maxSize > 0) {
            $fileRules[] = 'max:'.$maxSize;
        }

        return [
            'attachments' => ['nullable', 'array', 'max:'.$maxFiles],
            'attachments.*' => $fileRules,
        ];
    }
}
