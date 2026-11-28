<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Services\CmsAccess;
use App\Support\VideoEmbed;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PreviewFooterVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(CmsAccess::class)->allows($user, 'settings.update');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $provider = $this->string('provider')->lower()->toString();

        return [
            'provider' => ['required', Rule::in(VideoEmbed::providers())],
            'url' => [
                'required',
                'string',
                'max:2000',
                function (string $attribute, mixed $value, Closure $fail) use ($provider): void {
                    if (! is_string($value) || VideoEmbed::resolve($provider, $value) === null) {
                        $fail(__('website.cms.setting_video_url_invalid'));
                    }
                },
            ],
        ];
    }
}
