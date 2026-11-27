<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Services\CmsAccess;
use Illuminate\Foundation\Http\FormRequest;

final class ReorderWebsiteSectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(CmsAccess::class)->allows($user, 'website_structure.update');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'min:1', 'max:40'],
            'order.*.id' => ['required', 'integer', 'exists:homepage_sections,id'],
            'order.*.display_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
