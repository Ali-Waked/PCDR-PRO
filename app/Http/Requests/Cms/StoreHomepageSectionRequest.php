<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Enums\ContentStatus;
use App\Services\CmsAccess;
use Illuminate\Foundation\Http\FormRequest;

final class StoreHomepageSectionRequest extends FormRequest
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
            'key' => ['required', 'string', 'max:64', 'unique:homepage_sections,key'],
            'type' => ['required', 'string', 'max:48'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', ContentStatus::values())],
            'is_enabled' => ['sometimes', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }
}
