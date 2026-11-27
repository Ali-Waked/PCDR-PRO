<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Services\CmsAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateSectionPresentationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(CmsAccess::class)->allows($user, 'homepage.update');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.ticker_label' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @param Validator<mixed> $validator */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $definition = config('cms_structure.sections.'.(string) $this->route('section'));

            if (! is_array($definition) || ($definition['content'] ?? '') !== 'announcements') {
                $validator->errors()->add('section', 'This section does not manage presentation labels.');
            }
        });
    }
}
