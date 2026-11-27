<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Enums\ContentStatus;
use App\Services\CmsAccess;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateHomepageStatusRequest extends FormRequest
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
            'status' => ['required', 'string', 'in:'.implode(',', ContentStatus::values())],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
